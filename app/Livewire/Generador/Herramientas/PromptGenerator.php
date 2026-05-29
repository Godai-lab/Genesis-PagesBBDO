<?php

namespace App\Livewire\Generador\Herramientas;

use App\Http\Traits\ValidatesCreditLimit;
use App\Models\Generated;
use App\Services\OpenAiService;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Log;

/**
 * Generador de Prompts con IA
 *
 * Usa OpenAI Chat-Prompt (Responses API) con opción de incluir documentos Genesis como contexto.
 */
class PromptGenerator extends Component
{
    use ValidatesCreditLimit;

    protected string $toolName = 'Generador de Prompts';

    #[Reactive]
    public ?int $accountId = null;

    #[Validate('nullable|string|min:3')]
    public string $promptText = '';

    public bool $isGenerating = false;

    public array $chatHistory = [];

    public array $documentos = [];

    public ?string $documentoSeleccionado = null;

    public ?array $documentoInfo = null;

    private string $chatPromptId = 'pmpt_69554d05263481908663c1ba3d95b26f0f1f0e6e1e403384';

    private string $model = 'gpt-5-mini-2025-08-07';

    public function mount(): void
    {
        $this->chatHistory = session()->get('generador_chat_history', []);
        $this->cargarDocumentosGenesis();

        Log::info('📝 PromptGenerator montado', [
            'accountId' => $this->accountId,
        ]);
    }

    #[On('accountChanged')]
    public function updateAccount(?int $accountId): void
    {
        $this->accountId = $accountId;

        Log::info('🔄 Cuenta actualizada en PromptGenerator', [
            'accountId' => $accountId,
        ]);
    }

    public function cargarDocumentosGenesis(): void
    {
        try {
            $user = auth()->user();

            $query = Generated::select('id', 'name', 'key', 'account_id', 'created_at', 'status')
                ->where('key', 'Genesis')
                ->where('status', 'completed')
                ->orderBy('created_at', 'desc')
                ->limit(30);

            if ($user->roles->pluck('name')->contains(fn ($rol) => in_array($rol, ['Admin', 'Super Admin']))) {
                $documentos = $query->get();
            } else {
                $accountIds = $user->accounts->pluck('id')->toArray();
                $documentos = $query->whereIn('account_id', $accountIds)->get();
            }

            $this->documentos = $documentos->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'texto' => $doc->name,
                    'fecha' => $doc->created_at->format('d/m/Y'),
                ];
            })->toArray();

            Log::info('Documentos Genesis cargados', [
                'count' => count($this->documentos),
                'user_id' => $user->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Error cargando documentos Genesis', [
                'error' => $e->getMessage(),
            ]);
            $this->documentos = [];
        }
    }

    public function seleccionarDocumentoGenesis(): void
    {
        if (!$this->documentoSeleccionado) {
            $this->documentoInfo = null;
            return;
        }

        try {
            $documento = Generated::find($this->documentoSeleccionado);

            if ($documento) {
                $user = auth()->user();
                $puedeAcceder = $user->roles->pluck('name')->contains(fn ($rol) => in_array($rol, ['Admin', 'Super Admin']))
                    || $user->accounts->pluck('id')->contains($documento->account_id);

                if ($puedeAcceder) {
                    $this->documentoInfo = [
                        'id' => $documento->id,
                        'name' => $documento->name,
                        'fecha' => $documento->created_at->format('d/m/Y'),
                        'contenido' => $documento->value,
                    ];

                    Log::info('Documento Genesis seleccionado', [
                        'documentoId' => $documento->id,
                        'documentoName' => $documento->name,
                        'contenidoLength' => strlen($documento->value ?? ''),
                    ]);
                } else {
                    $this->documentoInfo = null;
                    Log::warning('Usuario sin permisos para acceder al documento Genesis', [
                        'documentoId' => $documento->id,
                        'userId' => $user->id,
                    ]);
                }
            } else {
                $this->documentoInfo = null;
            }
        } catch (\Exception $e) {
            Log::error('Error seleccionando documento Genesis', [
                'documentoId' => $this->documentoSeleccionado,
                'error' => $e->getMessage(),
            ]);
            $this->documentoInfo = null;
        }
    }

    public function quitarDocumentoGenesis(): void
    {
        $this->documentoSeleccionado = null;
        $this->documentoInfo = null;
    }

    public function generate(): void
    {
        if (empty(trim($this->promptText)) && empty($this->documentoSeleccionado)) {
            $this->addError('promptText', 'Debe escribir una instrucción o seleccionar un documento Genesis.');
            return;
        }

        if (!empty(trim($this->promptText)) && strlen(trim($this->promptText)) < 3) {
            $this->addError('promptText', 'La instrucción debe tener al menos 3 caracteres.');
            return;
        }

        Log::info('🚀 Iniciando generación de prompt', [
            'prompt' => !empty($this->promptText) ? substr($this->promptText, 0, 50) . '...' : 'Vacío',
            'hasDocumento' => !is_null($this->documentoSeleccionado),
            'accountId' => $this->accountId,
        ]);

        $this->isGenerating = true;
        $this->dispatch('generationStarted');
        $this->dispatch('startPromptGeneration', [
            'prompt' => $this->promptText,
            'documento' => $this->documentoSeleccionado,
        ]);
    }

    #[On('startPromptGeneration')]
    public function executeGeneration($data): void
    {
        Log::info('🔄 Ejecutando generación real de prompt', [
            'prompt' => substr($data['prompt'] ?? '', 0, 50) . '...',
            'documento' => $data['documento'] ?? null,
            'accountId' => $this->accountId,
        ]);

        try {
            if (!$this->accountId) {
                $errorMessage = 'Debes seleccionar una cuenta antes de generar prompts';
                $this->addError('promptText', $errorMessage);
                $this->dispatch('addErrorToList', message: $errorMessage, type: 'validation', tool: 'prompt-generator');
                $this->dispatch('generationError');
                $this->isGenerating = false;
                return;
            }

            $this->validateCreditLimit($this->accountId);

            $this->generarPromptConOpenAI($data['prompt'] ?? '', $data['documento'] ?? null);
        } catch (\App\Exceptions\CreditLimitExceededException $e) {
            Log::warning('Límite de créditos excedido en Generador de Prompts', [
                'message' => $e->getMessage(),
                'accountId' => $this->accountId,
            ]);

            $this->addError('promptText', $e->getMessage());
            $this->dispatch('addErrorToList', message: $e->getMessage(), type: 'credit_limit', tool: 'prompt-generator');
            $this->dispatch('generationError');
            $this->isGenerating = false;
        } catch (\Exception $e) {
            Log::error('💥 Error en Generador de Prompts', [
                'accountId' => $this->accountId,
                'error' => $e->getMessage(),
            ]);

            $errorMessage = 'Ha ocurrido un error al generar el prompt. Por favor, intenta nuevamente.';
            $this->addError('promptText', $errorMessage);
            $this->dispatch('addErrorToList', message: $errorMessage, type: 'system', tool: 'prompt-generator');
            $this->dispatch('generationError');
            $this->isGenerating = false;
        }
    }

    private function generarPromptConOpenAI(string $promptCompleto, ?string $documento): void
    {
        Log::info('🎨 Iniciando generación con OpenAI Chat-Prompt', [
            'prompt' => !empty($promptCompleto) ? substr($promptCompleto, 0, 50) . '...' : 'Vacío',
            'hasDocumento' => !is_null($documento),
        ]);

        try {
            $promptFinal = '';
            $modo = '';

            if (!empty(trim($promptCompleto))) {
                $promptFinal = trim($promptCompleto);
                $modo = 'Instrucción';
            }

            if ($documento) {
                $documentoInfo = Generated::find($documento);

                if ($documentoInfo && $documentoInfo->value) {
                    if (!empty($promptFinal)) {
                        $promptFinal .= "\n\n--- Contenido de referencia (Documento Genesis) ---\n\n" . $documentoInfo->value;
                        $modo = 'Instrucción + Genesis';
                    } else {
                        $promptFinal = "Genera un prompt optimizado basado en el siguiente contenido:\n\n" . $documentoInfo->value;
                        $modo = 'Solo Genesis';
                    }

                    Log::info('📄 Documento Genesis procesado', [
                        'documentoId' => $documento,
                        'documentoName' => $documentoInfo->name,
                        'modo' => $modo,
                    ]);
                }
            }

            if (empty($promptFinal)) {
                $promptFinal = 'Genera un prompt optimizado para uso general';
                $modo = 'Default';
            }

            $options = [
                'prompt' => [
                    'id' => $this->chatPromptId,
                    'variables' => [
                        'userinput' => $promptFinal,
                    ],
                ],
                'tools' => [],
                'background' => false,
            ];

            Log::info('📤 Enviando request a OpenAI', [
                'model' => $this->model,
                'promptId' => $this->chatPromptId,
                'modo' => $modo,
            ]);

            $response = OpenAiService::createModelResponse($options);

            Log::info('📡 Respuesta de OpenAiService::createModelResponse', [
                'hasError' => isset($response['error']),
                'hasData' => isset($response['data']),
            ]);

            if (isset($response['data'])) {
                $responseId = $response['data']['id'] ?? null;

                if (isset($response['data']['usage'])) {
                    $usage = $response['data']['usage'];
                    $inputTokens = $usage['input_tokens'] ?? 0;
                    $outputTokens = $usage['output_tokens'] ?? 0;

                    if ($inputTokens > 0 || $outputTokens > 0) {
                        try {
                            \App\Supports\CostCalculationService::trackUsage(
                                $this->accountId,
                                auth()->id(),
                                $this->model,
                                [
                                    'tokens' => [
                                        'input' => $inputTokens,
                                        'output' => $outputTokens,
                                    ],
                                ],
                                null,
                                'Generador de Prompts',
                                $responseId,
                                null,
                                'generatePrompt',
                                'openai'
                            );
                            Log::info('✅ Uso registrado en Generador de Prompts', [
                                'inputTokens' => $inputTokens,
                                'outputTokens' => $outputTokens,
                            ]);
                        } catch (\Exception $e) {
                            Log::error('Error al registrar uso en Generador de Prompts', [
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }

                $textoGenerado = '';
                if (isset($response['data']['output']) && is_array($response['data']['output'])) {
                    foreach ($response['data']['output'] as $block) {
                        if (
                            ($block['type'] === 'message' || $block['type'] === 'assistant')
                            && isset($block['content'][0]['text'])
                        ) {
                            $textoGenerado = $block['content'][0]['text'];
                            break;
                        }
                    }
                }

                if (empty($textoGenerado)) {
                    throw new \Exception('No se pudo extraer el texto generado de la respuesta de OpenAI');
                }

                $this->chatHistory[] = [
                    'tipo' => 'sistema',
                    'contenido' => $textoGenerado,
                    'tiempo' => now()->format('H:i'),
                ];

                session()->put('generador_chat_history', $this->chatHistory);

                $this->dispatch('addToHistory',
                    type: 'prompt/generate',
                    prompt: !empty(trim($this->promptText)) ? $this->promptText : 'Solo documento Genesis',
                    generatedPrompt: $textoGenerado,
                    documento: $this->documentoSeleccionado,
                    model: 'OpenAI Chat-Prompt (' . $this->model . ')',
                    date: now()->toIso8601String()
                );

                $this->dispatch('generationCompleted');
                $this->dispatch('historialActualizado');
            } elseif (isset($response['error'])) {
                $errorMessage = 'Error generando prompt: ' . $response['error'];
                $this->addError('promptText', $errorMessage);
                $this->dispatch('addErrorToList', message: $errorMessage, type: 'generation', tool: 'prompt-generator');
                $this->dispatch('generationError');
            }
        } catch (\Exception $e) {
            Log::error('💥 Error en generarPromptConOpenAI', [
                'error' => $e->getMessage(),
            ]);

            $errorMessage = 'Error generando prompt: ' . $e->getMessage();
            $this->addError('promptText', $errorMessage);
            $this->dispatch('addErrorToList', message: $errorMessage, type: 'system', tool: 'prompt-generator');
            $this->dispatch('generationError');
        } finally {
            $this->isGenerating = false;
        }
    }

    public function limpiarHistorial(): void
    {
        $this->chatHistory = [];
        session()->forget('generador_chat_history');
        $this->dispatch('historialActualizado');
    }

    #[On('loadPromptFromHistory')]
    public function loadPromptFromHistory($promptData): void
    {
        $this->promptText = $promptData['prompt'] ?? '';

        if (isset($promptData['documento'])) {
            $this->documentoSeleccionado = $promptData['documento'];
            $this->seleccionarDocumentoGenesis();
        }
    }

    public function render()
    {
        return view('livewire.generador.herramientas.prompt-generator');
    }
}

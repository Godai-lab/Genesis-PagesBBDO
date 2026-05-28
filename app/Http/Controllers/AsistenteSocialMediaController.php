<?php

namespace App\Http\Controllers;

use App\Http\Traits\ValidatesCreditLimit;
use App\Models\Account;
use App\Models\Field;
use App\Models\Generated;
use App\Services\OpenAiService;
use App\Supports\ContentCategory;
use App\Supports\CostCalculationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class AsistenteSocialMediaController extends Controller
{
    use ValidatesCreditLimit;
    
    /** Nombre de la herramienta para logging */
    protected string $toolName = 'Asistente Social Media';
    
    // Modelo utilizado (para registro de uso)
    // Este modelo NO se envía al ChatPrompt, solo se usa para trackUsage
    private const MODEL_ASISTENTE_SOCIAL_MEDIA = 'gpt-5.1-2025-11-13';
    
    public function index()
    {
        Gate::authorize('haveaccess','asistentesocialmedia.index');
        $accounts = Account::fullaccess()->get();

        // Categorías con sus vector stores
        $categories = ContentCategory::all();
       

        return view('asistenteSocialMedia.index', compact('accounts', 'categories'));
    }

    public function generarPrompt(Request $request)
{
    try {
        $validator = Validator::make($request->all(), [
            '_token' => 'required',
            'account' => 'required|integer',
            'brief' => 'nullable|required_without:genesis|integer',
            'genesis' => 'nullable|required_without:brief|integer',
            'asistenteSocialMediaPrompt' => 'required|string',
            'categories' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()]);
        }

        ini_set('max_execution_time', 300);

        $accountId = $request->input('account');
        
        // Verificar límite de créditos antes de generar
        $this->validateCreditLimit($accountId);
        
        $briefID = $request->input('brief');
        $genesisID = $request->input('genesis');

        if ($briefID && $genesisID) {
            return response()->json([
                'error' => 'Solo puedes seleccionar un brief o un genesis, no ambos'
            ]);
        }

        $fileGenerated = "";
        if ($briefID) {
            $fileGenerated = Generated::where('id', $briefID)->first()->value;
        }
        if ($genesisID) {
            $fileGenerated = Generated::where('id', $genesisID)->first()->value;
        }

        $asistenteSocialMediaPrompt = $request->input('asistenteSocialMediaPrompt');

        // Mapeo de categorías con sus vector stores
        $categoryVectorStores = ContentCategory::vectorMap();
       
        
        // Procesar categorías seleccionadas
        $selectedCategories = $request->input('categories', []);
        $vectorIds = [];
        
        if (!empty($selectedCategories)) {
            foreach ($selectedCategories as $category) {
                if (isset($categoryVectorStores[$category])) {
                    $vectorIds[] = $categoryVectorStores[$category];
                }
            }
        } else {
            // Si no hay categorías seleccionadas, usar el vector store por defecto
            $vectorIds[] = 'vs_69cb42cda0b08191b54417701027fcd6';
        }

        // Configuración del chat-prompt (el modelo se define en el ChatPrompt del dashboard de OpenAI)
        $options = [
            'prompt' => [
                'id' => 'pmpt_68dc24dc86e881948f48e2696b51af2c03832040671c2ddf',
                'variables' => [
                    'asistentesocialmediaprompt' => $asistenteSocialMediaPrompt,
                    'filegenerated' => $fileGenerated
                ]
            ],
            'tools' => [
                [
                    'type' => 'file_search',
                    'vector_store_ids' => $vectorIds
                ]
            ],
            'background' => false,
        ];

        $response = OpenAiService::createModelResponse($options);

        if (isset($response['error'])) {
            throw new \Exception($response['error']);
        }
        
        // Log del ID de respuesta (si existe)
        $responseId = $response['data']['id'] ?? null;
        if ($responseId) {
            Log::info('ID de respuesta OpenAI (Asistente Social Media)', [
                'account_id' => $accountId,
                'response_id' => $responseId
            ]);
        }
        
        // Log del objeto usage
        if (isset($response['data']['usage'])) {
            $usage = $response['data']['usage'];
            $inputTokens = $usage['input_tokens'] ?? 0;
            $outputTokens = $usage['output_tokens'] ?? 0;
            
            Log::info('Usage tokens OpenAI (Asistente Social Media)', [
                'account_id' => $accountId,
                'response_id' => $responseId,
                'usage' => $usage
            ]);
            
            // Registrar el uso en la base de datos
            if ($inputTokens > 0 || $outputTokens > 0) {
                    try {
                        // Nota: Asistente Social Media crea el Generated después de generar el prompt
                        // Por lo tanto, no hay generated_id disponible en este punto
                        // Si se necesita agrupar, se debería crear el Generated antes de la llamada
                        CostCalculationService::trackUsage(
                            $accountId,
                            auth()->id(),
                            self::MODEL_ASISTENTE_SOCIAL_MEDIA, // Modelo usado
                            [
                                'tokens' => [
                                    'input' => $inputTokens,
                                    'output' => $outputTokens
                                ]
                            ],
                            null,
                            'Asistente Social Media', // request_type simplificado
                            null, // external_request_id
                            null, // generated_id (no disponible en este punto)
                            'generarPrompt', // step
                            'openai' // service_type
                        );
                    Log::info('✅ Uso registrado exitosamente en Asistente Social Media');
                } catch (\Exception $e) {
                    Log::error('Error al registrar uso en Asistente Social Media', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    // No lanzamos la excepción para no interrumpir el flujo
                }
            }
        }

        // Extraer respuesta final
        $textoFinal = '';
        if (isset($response['data']['output']) && is_array($response['data']['output'])) {
            foreach ($response['data']['output'] as $block) {
                if (
                    ($block['type'] === 'message' || $block['type'] === 'assistant') &&
                    isset($block['content'][0]['text'])
                ) {
                    $textoFinal = $block['content'][0]['text'];
                    break;
                }
            }
        }

        return response()->json([
            'success' => 'Datos procesados correctamente.',
            'details' => [
                    'data' => $textoFinal
                ],
            'goto' => 3,
            'function' => 'asistenteSocialMediaGenerate'
        ]);

    } catch (\App\Exceptions\CreditLimitExceededException $e) {
        Log::warning('Límite de créditos excedido en Asistente Social Media', [
            'message' => $e->getMessage(),
            'accountId' => $accountId ?? null
        ]);
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ]);
    } catch (\Exception $e) {
        Log::error('Error en generarPrompt (Asistente Social Media)', [
            'message' => $e->getMessage(),
            'accountId' => $accountId ?? null,
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json([
            'success' => false,
            'error' => 'Ha ocurrido un error al generar el contenido. Por favor, intenta nuevamente.',
        ]);
    }
}

    public function generarPromptold(Request $request){
        try {

            $validator = Validator::make($request->all(), [
                '_token' => 'required',
                'account' => 'required|integer',
                'brief' => 'nullable|required_without:genesis|integer',
                'genesis' => 'nullable|required_without:brief|integer',
                'asistenteSocialMediaPrompt' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()]);
            }

            ini_set('max_execution_time', 300);

            $accountId = $request->input('account');
            //solo puede llegar uno o brief o genesis
            $briefID = $request->input('brief');
            $genesisID = $request->input('genesis');
            if($briefID && $genesisID){
                return response()->json(['error' => 'Solo puedes seleccionar un brief o un genesis, no ambos']);
            }
            $fileGenerated = "";
            if($briefID){
                $fileGenerated = Generated::where('id',$briefID)->first()->value;
            }
            if($genesisID){
                $fileGenerated = Generated::where('id',$genesisID)->first()->value;
            }


            $asistenteSocialMediaPrompt = $request->input('asistenteSocialMediaPrompt');

            $prompt = <<<EOT
Genera las mejores propuestas para: $asistenteSocialMediaPrompt.
Con esta información como lineamiento: 

$fileGenerated
EOT;
            // $assistant_idCreatividad = "asst_L0cQElTHUUyDANBmAfMQQmFk";
            $assistant_idCreatividad = "asst_tQcA7RfVfjt1wnL1uCpjBz1C";

            $response = OpenAiService::CompletionsAssistants($prompt, $assistant_idCreatividad);

            if (isset($response['error'])) throw new Exception($response['error']);

            return response()->json(['success' => 'Datos procesados correctamente.', 'details' => $response, 'goto' => 3, 'function' => 'asistenteSocialMediaGenerate']);

        } catch (Exception $e) {
            return response()->json(['error' => $e]);
        }
    }
public function guardarGenerado(Request $request)
{
    try {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|integer',
            'file_name' => 'required|string',
            'asistenteSocialMediaGenerateInput' => 'required|string',
            'rating' => 'nullable|integer|min:1|max:5',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()]);
        }

        $generated = Generated::create([
            'account_id' => $request->input('account_id'),
            'key' => 'Asistente Social-Media',
            'name' => $request->input('file_name'),
            'value' => $request->input('asistenteSocialMediaGenerateInput'),
            'rating' => $request->input('rating'),
        ]);

        return response()->json(['success' => 'Datos guardados correctamente.']);
    } catch (Exception $e) {
        return response()->json(['error' => $e->getMessage()]);
    }
}
    public function download(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account' => 'required|integer',
            'asistenteSocialMediaGenerateContainer' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()]);
        }

        $fields = [
            'asistenteSocialMediaGenerateContainer' => $request->input('asistenteSocialMediaGenerateContainer'),
        ];

        // Cargar la vista Blade que contiene la plantilla PDF
        $pdf = Pdf::setOptions([
            'defaultFont' => 'sans-serif',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true
        ])->loadView('asistenteSocialMedia.pdf.template', array_merge($fields));
        
        // Obtén la fecha y hora actual
        $now = Carbon::now();

        // Formatea la fecha y hora como una cadena en el formato deseado (por ejemplo, "YYYYMMDD_HHMMSS")
        $timestamp = $now->format('Ymd_His');

        // Descargar el PDF
        return $pdf->download('asistenteSocialMedia_' . $timestamp . '.pdf');

    }
}

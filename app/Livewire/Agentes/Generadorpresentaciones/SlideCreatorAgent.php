<?php

namespace App\Livewire\Agentes\Generadorpresentaciones;

use App\Models\Generated;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Log;

class SlideCreatorAgent extends Component
{
    // Propiedades públicas
    public $conversations = []; // Historial de conversaciones
    public $currentConversationId = null; // ID de la conversación actual
    public $prompt = ''; // Texto del prompt del usuario
    public $selectedGenesisDoc = null; // ID del documento Genesis seleccionado
    public $genesisDocuments = []; // Lista de documentos Genesis disponibles
    public $genesisDocInfo = null; // Información del documento Genesis seleccionado
    public $showGenesisSelector = false; // Mostrar/ocultar selector de documentos
    public $isGenerating = false; // Indicador de generación en proceso
    public $currentPresentation = null; // Presentación actual en vista
    public $sidebarOpen = true; // Estado del sidebar (para móvil)

    // Modo de generación
    public $generationMode = 'scratch'; // 'template' o 'scratch' (por defecto: sin plantilla)
    public $showTemplateOption = false; // 🔧 Cambiar a true cuando tengas plantillas listas
    public $generationModes = [
        'template' => 'Con Plantilla',
        'scratch' => 'Sin Plantilla'
    ];

    // Opciones de Imagen (para modo "Sin Plantilla")
    public $imageModels = [
        'flux-1-quick' => 'Flux 1 Quick',
        'imagen-4-pro' => 'Imagen 4 Pro',
        'flux-1-pro' => 'Flux 1 Pro',
    ];
    public $selectedImageModel = 'flux-1-quick';

    public $imageStyles = [
        'photorealistic' => 'Fotorealista',
        'illustration' => 'Ilustración',
        'minimalist' => 'Minimalista',
    ];
    public $selectedImageStyle = 'photorealistic';

    // Opciones para modo "Sin Plantilla"
    public $textMode = 'generate'; // generate, condense, preserve
    public $textModes = [
        'generate' => 'Generar texto',
        'condense' => 'Condensar texto',
        'preserve' => 'Preservar texto'
    ];
    
    public $numCards = 10; // Número de diapositivas

    // Plantillas disponibles (para modo "Con Plantilla")
    public $templates = [
        'g_zwne8vnfkiyq3i2' => 'Plantilla God-ai',
    ];
    
    // ID de la plantilla seleccionada (por defecto: Plantilla God-ai)
    public $selectedTemplateId = 'g_zwne8vnfkiyq3i2'; 


    // Sistema de Errores
    public $errors = [];

    /**
     * Inicializar el componente
     */
    public function mount()
    {
        $this->loadGenesisDocuments();
        $this->loadConversations();
    }

    // Métodos para gestión de errores
    public function addError($message, $type = 'general', $tool = 'Generador')
    {
        array_unshift($this->errors, [
            'id' => uniqid('err_'),
            'message' => $message,
            'type' => $type,
            'tool' => $tool,
            'date' => now()->toIso8601String()
        ]);
    }

    public function clearErrors()
    {
        $this->errors = [];
    }

    public function dismissError($id)
    {
        $this->errors = array_filter($this->errors, function($error) use ($id) {
            return $error['id'] !== $id;
        });
    }

    // ... (otros métodos) ...

    /**
     * Generar la presentación
     */
    public function generatePresentation()
    {
        // Validar: debe haber Genesis O prompt (no ambos vacíos)
        if (!$this->selectedGenesisDoc && empty(trim($this->prompt))) {
            $this->addError('Por favor, selecciona un documento Genesis o escribe una descripción para tu presentación', 'validación');
            return;
        }

        $this->isGenerating = true;

        try {
            // Preparar el prompt final
            $finalPrompt = '';

            // Si hay documento Genesis, usar su contenido
            if ($this->selectedGenesisDoc && $this->genesisDocInfo) {
                $documento = Generated::find($this->selectedGenesisDoc);
                
                if ($documento && $documento->key === 'Genesis') {
                    $genesisContent = $documento->value;
                    
                    // Construir prompt con el contenido del Genesis
                    $finalPrompt = "Genera una presentación usando EXACTAMENTE el siguiente contenido. No resumas, no interpretes, solo estructura el contenido en diapositivas:\n\n";
                    $finalPrompt .= "CONTENIDO:\n" . $genesisContent;
                    
                    \Log::info('Usando contenido Genesis', [
                        'document_id' => $documento->id,
                        'template' => $this->selectedTemplateId,
                        'content_length' => strlen($genesisContent)
                    ]);
                } else {
                    $this->addError('Error al cargar el documento Genesis seleccionado', 'sistema');
                    $this->isGenerating = false;
                    return;
                }
            } else {
                // Sin Genesis, usar el prompt del usuario
                $finalPrompt = $this->prompt;
            }

            // Ejecutar generación según el modo seleccionado
            \Log::info('Iniciando generación de presentación', [
                'mode' => $this->generationMode,
                'template' => $this->selectedTemplateId,
                'prompt_length' => strlen($finalPrompt),
                'has_genesis' => !empty($this->selectedGenesisDoc)
            ]);

            if ($this->generationMode === 'template') {
                // Generar CON plantilla
                $response = \App\Services\GammaService::generateFromTemplate(
                    $finalPrompt,
                    'pptx',
                    $this->selectedTemplateId,
                    []
                );
            } else {
                // Generar SIN plantilla (desde cero)
                $imageOptions = [
                    'model' => $this->selectedImageModel,
                    'style' => $this->selectedImageStyle
                ];
                
                $response = \App\Services\GammaService::generateFromScratch(
                    $finalPrompt,
                    'pptx',
                    $imageOptions,
                    $this->textMode,
                    $this->numCards
                );
            }

            // Verificar respuesta
            if (isset($response['error'])) {
                \Log::error('Error en API de generación', ['error' => $response['error']]);
                $this->addError('Error al generar la presentación: ' . $response['error'], 'api_error');
                $this->isGenerating = false;
                return;
            }

            if (!isset($response['data'])) {
                \Log::error('Respuesta de Gamma sin data', $response);
                $this->addError('Respuesta inesperada del servicio de generación', 'api_error');
                $this->isGenerating = false;
                return;
            }

            // Obtener el generationId de Gamma
            $gammaData = $response['data'];
            $generationId = $gammaData['generationId'] ?? null;

            if (!$generationId) {
                \Log::error('Respuesta de Gamma sin generationId', $gammaData);
                $this->addError('No se recibió el ID de generación', 'api_error');
                $this->isGenerating = false;
                return;
            }

            // Definir el prompt para mostrar en el historial (solo lo que escribió el usuario)
            $displayPrompt = !empty(trim($this->prompt)) ? $this->prompt : ($this->genesisDocInfo['name'] ?? 'Presentación desde documento Genesis');

            \Log::info('Generación de Gamma iniciada', [
                'generationId' => $generationId,
                'status' => $gammaData['status'] ?? 'unknown'
            ]);

            // 🆕 GUARDAR INMEDIATAMENTE EN BD COMO PENDIENTE
            // Usamos $displayPrompt para que en el historial solo salga lo que pidió el usuario
            $this->savePendingGeneration(
                $generationId,
                $displayPrompt, 
                $this->selectedGenesisDoc,
                $this->genesisDocInfo['name'] ?? null
            );

            // Emitir evento al frontend para iniciar polling
            $this->dispatch('gammaTaskStarted', 
                generationId: $generationId,
                prompt: $this->prompt,
                genesisDocId: $this->selectedGenesisDoc,
                genesisDocName: $this->genesisDocInfo['name'] ?? null
            );

        } catch (\Exception $e) {
            \Log::error('Excepción generando presentación', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->isGenerating = false;
            $this->addError('Error inesperado: ' . $e->getMessage(), 'excepción');
        }
    }

    /**
     * Cargar solo documentos tipo Genesis
     */
    public function loadGenesisDocuments()
    {
        $user = auth()->user();
        
        // Crear la consulta base solo para documentos Genesis
        $query = Generated::select('id', 'name', 'key', 'account_id', 'created_at')
                          ->where('key', 'Genesis')
                          ->where('status', 'completed')
                          ->orderBy('created_at', 'desc')
                          ->limit(30);
        
        // Si el usuario es Super Admin o Admin, puede ver todos los documentos
        if ($user->roles->pluck('name')->contains(fn($rol) => in_array($rol, ['Admin', 'Super Admin']))) {
            $documentos = $query->get();
        } else {
            $accountIds = $user->accounts->pluck('id')->toArray();
            $documentos = $query->whereIn('account_id', $accountIds)->get();
        }
        
        // Transformar los datos para el selector
        $this->genesisDocuments = $documentos->map(function($doc) {
            return [
                'id' => $doc->id,
                'name' => $doc->name,
                'account' => $doc->account ? $doc->account->name : 'Sin cuenta',
                'date' => $doc->created_at->format('d/m/Y H:i')
            ];
        })->toArray();
    }

    /**
     * Cargar conversaciones del usuario desde la sesión
     */
    public function loadConversations()
    {
        $userId = auth()->id();
        
        // Cargar conversaciones de la base de datos
        $dbConversations = \App\Models\ChatConversation::where('user_id', $userId)
            ->where('agent_type', 'slide-creator')
            ->orderBy('last_message_at', 'desc')
            ->with(['messages' => function($query) {
                $query->where('role', 'assistant')
                      // 🆕 Cargar TODOS los mensajes (visibles y pendientes)
                      ->orderBy('created_at', 'desc');
            }])
            ->get();

        // Transformar a la estructura esperada por el frontend
        $this->conversations = $dbConversations->map(function($conv) {
            return [
                'id' => $conv->id,
                'name' => $conv->title,
                'created_at' => $conv->created_at->toIso8601String(),
                'presentations' => $conv->messages->map(function($msg) {
                    $attachments = $msg->attachments ?? [];
                    return [
                        'id' => $msg->id, // ID del mensaje como ID de presentación
                        'title' => $attachments['prompt'] ?? 'Presentación Generada',
                        'prompt' => $attachments['prompt'] ?? '',
                        'genesis_doc_id' => $attachments['genesis_doc_id'] ?? null,
                        'genesis_doc_name' => $attachments['genesis_doc_name'] ?? null,
                        'generation_id' => $attachments['generation_id'] ?? null,
                        'gamma_url' => $attachments['gamma_url'] ?? null,
                        'export_url' => $attachments['export_url'] ?? null,
                        'credits_deducted' => $attachments['credits_deducted'] ?? null,
                        'credits_remaining' => $attachments['credits_remaining'] ?? null,
                        'status' => $attachments['status'] ?? 'completed', // 🆕 Usar status de attachments
                        'created_at' => $msg->created_at->toIso8601String(),
                    ];
                })->values()->toArray()
            ];
        })->toArray();
        
        // Si hay conversaciones, seleccionar la primera
        if (!empty($this->conversations)) {
            // Si no hay conversación seleccionada o la seleccionada no existe en la lista cargada
            $exists = false;
            if ($this->currentConversationId) {
                foreach ($this->conversations as $c) {
                    if ($c['id'] == $this->currentConversationId) {
                        $exists = true;
                        break;
                    }
                }
            }
            
            if (!$this->currentConversationId || !$exists) {
                $this->currentConversationId = $this->conversations[0]['id'];
                
                // Si la conversación tiene presentaciones, mostrar la primera
                if (!empty($this->conversations[0]['presentations'])) {
                    $this->currentPresentation = $this->conversations[0]['presentations'][0];
                }
            }
        } else {
            // Crear conversación inicial (estado temporal)
            $this->createNewConversation();
        }
    }

    /**
     * Seleccionar un documento Genesis
     */
    public function selectGenesisDocument($id)
    {
        $this->selectedGenesisDoc = $id;
        
        // Buscar el documento seleccionado
        $documento = Generated::find($id);
        
        if ($documento && $documento->key === 'Genesis') {
            $user = auth()->user();
            $puedeAcceder = $user->roles->pluck('name')->contains(fn($rol) => in_array($rol, ['Admin', 'Super Admin'])) ||
                $user->accounts->pluck('id')->contains($documento->account_id);
            
            if ($puedeAcceder) {
                $this->genesisDocInfo = [
                    'id' => $documento->id,
                    'name' => $documento->name,
                    'account' => $documento->account ? $documento->account->name : 'Sin cuenta'
                ];
            } else {
                $this->genesisDocInfo = null;
                $this->selectedGenesisDoc = null;
            }
        } else {
            $this->genesisDocInfo = null;
            $this->selectedGenesisDoc = null;
        }
    }

    /**
     * Quitar documento Genesis seleccionado
     */
    public function removeGenesisDocument()
    {
        $this->selectedGenesisDoc = null;
        $this->genesisDocInfo = null;
    }

    /**
     * Mostrar/ocultar selector de documentos Genesis
     */
    public function toggleGenesisSelector()
    {
        $this->showGenesisSelector = !$this->showGenesisSelector;
    }

    /**
     * Confirmar selección de documento Genesis
     */
    public function confirmGenesisSelection()
    {
        $this->showGenesisSelector = false;
    }



    /**
     * Verificar estado de generación de Gamma (llamado por polling desde frontend)
     */
    #[On('verificarEstadoGamma')]
    public function verificarEstadoGamma($generationId, $prompt, $genesisDocId = null, $genesisDocName = null)
    {
        try {
            \Log::info('🔍 Verificando estado de Gamma desde frontend', [
                'generationId' => $generationId,
                'prompt' => substr($prompt, 0, 50) . '...'
            ]);

            // Consultar estado a Gamma
            $result = \App\Services\GammaService::checkGenerationStatus($generationId);

            \Log::info('📡 Respuesta de GammaService::checkGenerationStatus', [
                'generationId' => $generationId,
                'status' => $result['data']['status'] ?? 'unknown',
                'hasError' => isset($result['error']),
                'hasData' => isset($result['data'])
            ]);

            // Si hay error (ahora solo errores reales, no timeouts/conexión)
            if (isset($result['error'])) {
                \Log::error('❌ Error verificando estado de Gamma', [
                    'generationId' => $generationId,
                    'error' => $result['error']
                ]);
                $this->isGenerating = false;
                $this->addError('Error al verificar la generación: ' . $result['error'], 'api_error');
                $this->dispatch('error', message: 'Error al verificar la generación: ' . $result['error']);
                return;
            }

            $data = $result['data'];
            $status = $data['status'] ?? 'unknown';

            switch ($status) {
                case 'completed':
                    // ✅ PRESENTACIÓN LISTA
                    \Log::info('✅ Gamma completado exitosamente', [
                        'generationId' => $generationId,
                        'gammaUrl' => $data['gammaUrl'] ?? null,
                        'exportUrl' => $data['exportUrl'] ?? null
                    ]);

                    // 🆕 BUSCAR MENSAJE PENDIENTE EXISTENTE por generation_id
                    $pendingMessage = \App\Models\ChatMessage::whereJsonContains('attachments->generation_id', $generationId)
                        ->where('is_visible', 0)
                        ->where('role', 'assistant')
                        ->first();
                    
                    if ($pendingMessage) {
                        \Log::info('Mensaje pendiente encontrado, actualizando...', [
                            'message_id' => $pendingMessage->id,
                            'generation_id' => $generationId
                        ]);
                        
                        // ACTUALIZAR mensaje existente
                        $presentationId = uniqid('pres_');
                        
                        // Construir gamma_embed_url desde gamma_url
                        $gammaEmbedUrl = null;
                        if (isset($data['gammaUrl'])) {
                            $parsedUrl = parse_url($data['gammaUrl']);
                            $path = $parsedUrl['path'] ?? '';
                            $gammaSlug = str_replace('/docs/', '', $path);
                            $gammaSlug = trim($gammaSlug, '/');
                            $gammaEmbedUrl = 'https://gamma.app/embed/' . $gammaSlug;
                        }
                        
                        $pendingMessage->update([
                            'is_visible' => 1, // ← Ahora es visible
                            'content' => 'Presentación generada exitosamente.',
                            'attachments' => [
                                'presentation_id' => $presentationId,
                                'generation_id' => $generationId,
                                'prompt' => $pendingMessage->attachments['prompt'] ?? $prompt, // ✅ Mantener el prompt limpio original
                                'gamma_url' => $data['gammaUrl'] ?? null,
                                'gamma_embed_url' => $gammaEmbedUrl, // 🆕 URL para embed
                                'export_url' => $data['exportUrl'] ?? null,
                                'genesis_doc_id' => $genesisDocId,
                                'genesis_doc_name' => $genesisDocName,
                                'credits_deducted' => $data['credits']['deducted'] ?? null,
                                'credits_remaining' => $data['credits']['remaining'] ?? null,
                                'status' => 'completed'
                            ],
                        ]);
                        
                        // Recargar conversaciones para actualizar UI con los datos de DB
                        $this->loadConversations();
                        
                        // Establecer como presentación actual
                        $this->currentConversationId = $pendingMessage->conversation_id;
                        
                        // Buscar la presentación en la lista recargada
                        $found = false;
                        foreach ($this->conversations as $conv) {
                            if ($conv['id'] == $this->currentConversationId) {
                                foreach ($conv['presentations'] as $pres) {
                                    if ($pres['id'] == $pendingMessage->id) {
                                        $this->currentPresentation = $pres;
                                        $found = true;
                                        break 2;
                                    }
                                }
                            }
                        }
                        
                        if (!$found) {
                            $this->viewPresentation($pendingMessage->id);
                        }
                    } else {
                        \Log::warning('No se encontró mensaje pendiente, creando nuevo...', [
                            'generation_id' => $generationId
                        ]);
                        
                        // Fallback: Guardar como nuevo (por si acaso)
                        $savedData = $this->saveGenerationToHistory($prompt, $data, $genesisDocId, $genesisDocName);
                        $this->loadConversations();
                        $this->currentConversationId = $savedData['conversation']->id;
                        $this->currentPresentation = null;
                        
                        foreach ($this->conversations as $conv) {
                            if ($conv['id'] == $this->currentConversationId) {
                                foreach ($conv['presentations'] as $pres) {
                                    if ($pres['id'] == $savedData['message']->id) {
                                        $this->currentPresentation = $pres;
                                        break 2;
                                    }
                                }
                            }
                        }
                    }
                
                    // Limpiar el prompt y Genesis ANTES de finalizar generación
                    $this->prompt = '';
                    $this->selectedGenesisDoc = null;
                    $this->genesisDocInfo = null;
                    
                    // Finalizar generación
                    $this->isGenerating = false;
                    
                    // Notificar éxito al frontend
                    $this->dispatch('gammaCompleted', presentation: $this->currentPresentation);
                    
                    // Disparar evento para scroll automático a la nueva presentación
                    $this->dispatch('scrollToNewest');

                    \Log::info('Presentación completada y actualizada en DB', [
                        'generation_id' => $generationId,
                        'gammaUrl' => $data['gammaUrl'] ?? null
                    ]);
                    break;

                case 'pending':
                    // ⏳ AÚN PENDIENTE - EMITIR AL FRONTEND PARA CONTINUAR POLLING
                    \Log::info('⏳ Gamma aún pendiente, continuando polling...', [
                        'generationId' => $generationId,
                        'message' => $data['message'] ?? 'Generando...'
                    ]);

                    $this->dispatch('gammaStillPending', 
                        generationId: $generationId,
                        prompt: $prompt,
                        genesisDocId: $genesisDocId,
                        genesisDocName: $genesisDocName
                    );

                    \Log::info('🔄 Evento gammaStillPending disparado (siguiente polling en 10s)', [
                        'generationId' => $generationId
                    ]);
                    break;

                case 'failed':
                case 'error':
                    // ❌ ERROR
                    \Log::error('❌ Gamma falló', [
                        'generationId' => $generationId,
                        'status' => $status,
                        'data' => $data
                    ]);

                    $this->isGenerating = false;
                    $this->addError('Error al generar la presentación en Gamma', 'gamma_error');
                    $this->dispatch('error', message: 'Error al generar la presentación en Gamma');
                    break;

                default:
                    \Log::warning('⚠️ Estado desconocido de Gamma', [
                        'generationId' => $generationId,
                        'status' => $status,
                        'data' => $data
                    ]);

                    $this->isGenerating = false;
                    $this->addError('Estado desconocido de la generación: ' . $status, 'gamma_error');
                    $this->dispatch('error', message: 'Estado desconocido de la generación');
                    break;
            }

        } catch (\Exception $e) {
            \Log::error('💥 Excepción verificando estado de Gamma', [
                'generationId' => $generationId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            // En caso de excepción, continuar intentando (el frontend volverá a llamar)
            $this->dispatch('gammaStillPending', 
                generationId: $generationId,
                prompt: $prompt,
                genesisDocId: $genesisDocId,
                genesisDocName: $genesisDocName
            );
        }
    }

    /**
     * Ver detalles de una presentación
     */
    public function viewPresentation($id)
    {
        foreach ($this->conversations as $conversation) {
            $presentation = collect($conversation['presentations'])->firstWhere('id', $id);
            if ($presentation) {
                $this->currentPresentation = $presentation;
                $this->currentConversationId = $conversation['id'];
                return;
            }
        }
    }

    /**
     * Eliminar una presentación del historial
     */
    public function deletePresentation($id)
    {
        // Eliminar de la base de datos (Soft Delete)
        $message = \App\Models\ChatMessage::find($id);
        if ($message && $message->conversation->user_id === auth()->id()) {
            $message->update([
                'is_visible' => false, 
                'is_deleted' => true
            ]);
        }

        // Actualizar estado local
        $currentConversationIndex = array_search($this->currentConversationId, array_column($this->conversations, 'id'));

        if ($currentConversationIndex !== false) {
            $this->conversations[$currentConversationIndex]['presentations'] = array_filter($this->conversations[$currentConversationIndex]['presentations'], function($p) use ($id) {
                return $p['id'] !== $id;
            });
            
            // Re-indexar el array
            $this->conversations[$currentConversationIndex]['presentations'] = array_values($this->conversations[$currentConversationIndex]['presentations']);
            
            // Si era la presentación actual, limpiarla o seleccionar la primera de la conversación
            if ($this->currentPresentation && $this->currentPresentation['id'] === $id) {
                $this->currentPresentation = $this->conversations[$currentConversationIndex]['presentations'][0] ?? null;
            }
        }
    }

    /**
     * Crear nueva presentación (limpiar estado)
     */
    public function newPresentation()
    {
        $this->createNewConversation();
    }

    /**
     * Crear nueva presentación
     */
    public function createNewConversation()
    {
        $newConversation = [
            'id' => uniqid('conv_'),
            'name' => 'Nueva presentación',
            'created_at' => now()->toIso8601String(),
            'presentations' => []
        ];

        // Agregar al inicio
        array_unshift($this->conversations, $newConversation);
        
        // Seleccionar como actual
        $this->currentConversationId = $newConversation['id'];
        $this->currentPresentation = null;
        
        // Limpiar prompt
        $this->prompt = '';
        $this->removeGenesisDocument();
    }

    /**
     * Seleccionar conversación
     */
    public function selectConversation($conversationId)
    {
        $this->currentConversationId = $conversationId;
        
        // Buscar la conversación
        $conversation = collect($this->conversations)->firstWhere('id', $conversationId);
        
        if ($conversation && !empty($conversation['presentations'])) {
            $this->currentPresentation = $conversation['presentations'][0];
        } else {
            $this->currentPresentation = null;
        }
    }

    /**
     * Alternar sidebar
     */
    public function toggleSidebar()
    {
        $this->sidebarOpen = !$this->sidebarOpen;
    }

    /**
     * Reintentar verificación de generación pendiente
     * Se ejecuta cuando el usuario hace clic en "Verificar estado"
     */
    public function retryPendingGeneration($messageId)
    {
        $message = \App\Models\ChatMessage::find($messageId);
        
        if (!$message || $message->conversation->user_id !== auth()->id()) {
            \Log::warning('Intento de retry sin permisos', [
                'message_id' => $messageId,
                'user_id' => auth()->id()
            ]);
            return;
        }
        
        $attachments = $message->attachments ?? [];
        $generationId = $attachments['generation_id'] ?? null;
        $prompt = $attachments['prompt'] ?? '';
        $genesisDocId = $attachments['genesis_doc_id'] ?? null;
        $genesisDocName = $attachments['genesis_doc_name'] ?? null;
        
        if (!$generationId) {
            \Log::error('No se encontró generation_id en attachments', [
                'message_id' => $messageId
            ]);
            $this->addError('No se puede verificar esta presentación', 'retry_error');
            return;
        }
        
        \Log::info('Reintentando verificación manual', [
            'message_id' => $messageId,
            'generation_id' => $generationId
        ]);
        
        // Activar modo generando para mostrar spinner
        $this->isGenerating = true;
        
        // Reiniciar polling manualmente
        $this->verificarEstadoGamma($generationId, $prompt, $genesisDocId, $genesisDocName);
    }

    /**
     * Obtener conversación actual
     */
    private function getCurrentConversation()
    {
        return collect($this->conversations)->firstWhere('id', $this->currentConversationId);
    }

    /**
     * Actualizar nombre de presentación basado en el primer prompt
     */
    private function updateConversationName($prompt)
    {
        $index = array_search($this->currentConversationId, array_column($this->conversations, 'id'));
        
        if ($index !== false && $this->conversations[$index]['name'] === 'Nueva presentación') {
            // Usar las primeras 50 caracteres del prompt como nombre
            $this->conversations[$index]['name'] = substr($prompt, 0, 50) . (strlen($prompt) > 50 ? '...' : '');
        }
    }

    /**
     * Actualizar el título de una conversación
     */
    public function updateConversationTitle($conversationId, $newTitle)
    {
        $conversation = \App\Models\ChatConversation::find($conversationId);
        
        if (!$conversation || $conversation->user_id !== auth()->id()) {
            return;
        }
        
        $newTitle = trim($newTitle);
        if (empty($newTitle)) {
            return;
        }
        
        // Limitar a 100 caracteres
        $newTitle = substr($newTitle, 0, 100);
        
        $conversation->update(['title' => $newTitle]);
        
        // Actualizar en el array local
        foreach ($this->conversations as $index => $conv) {
            if ($conv['id'] == $conversationId) {
                $this->conversations[$index]['name'] = $newTitle;
                break;
            }
        }
    }

    /**
     * Eliminar una conversación completa
     */
    public function deleteConversation($conversationId)
    {
        $conversation = \App\Models\ChatConversation::find($conversationId);
        
        if (!$conversation || $conversation->user_id !== auth()->id()) {
            return;
        }
        
        // Eliminar todos los mensajes de la conversación
        $conversation->messages()->delete();
        
        // Eliminar la conversación
        $conversation->delete();
        
        // Eliminar del array local
        $this->conversations = array_filter($this->conversations, function($conv) use ($conversationId) {
            return $conv['id'] != $conversationId;
        });
        $this->conversations = array_values($this->conversations);
        
        // Si era la conversación actual, seleccionar otra o crear nueva
        if ($this->currentConversationId == $conversationId) {
            if (!empty($this->conversations)) {
                $this->currentConversationId = $this->conversations[0]['id'];
                $this->currentPresentation = $this->conversations[0]['presentations'][0] ?? null;
            } else {
                $this->createNewConversation();
            }
        }
        
        \Log::info('Conversación eliminada', ['conversation_id' => $conversationId]);
    }

    /**
     * Guardar generación pendiente en la BD (is_visible = 0)
     * Se ejecuta apenas se obtiene el generation_id de Gamma
     */
    private function savePendingGeneration($generationId, $prompt, $genesisDocId, $genesisDocName)
    {
        // 1. Crear o recuperar Conversación
        $conversation = null;
        
        if ($this->currentConversationId && !str_starts_with($this->currentConversationId, 'conv_')) {
            $conversation = \App\Models\ChatConversation::find($this->currentConversationId);
        }
        
        // Determinar el título: usar prompt si existe, si no usar nombre del Genesis
        $title = !empty(trim($prompt)) 
            ? $prompt 
            : ($genesisDocName ?? 'Presentación sin título');
        $title = substr($title, 0, 50) . (strlen($title) > 50 ? '...' : '');
        
        if (!$conversation) {
            $conversation = \App\Models\ChatConversation::create([
                'user_id' => auth()->id(),
                'agent_type' => 'slide-creator',
                'model_name' => 'gamma-app',
                'title' => $title,
                'status' => 'active',
                'last_message_at' => now(),
            ]);
            
            // Actualizar ID actual
            $this->currentConversationId = $conversation->id;
        } else {
            // Actualizar timestamp
            $conversation->update(['last_message_at' => now()]);
        }

        // 2. Mensaje Usuario (Prompt)
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $prompt,
            'is_visible' => true,
        ]);

        // 3. Mensaje Asistente PENDIENTE (is_visible = 0)
        $message = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => 'Generando presentación...',
            'is_visible' => 0, // ← PENDIENTE
            'attachments' => [
                'generation_id' => $generationId,
                'prompt' => $prompt,
                'genesis_doc_id' => $genesisDocId,
                'genesis_doc_name' => $genesisDocName,
                'status' => 'pending'
            ],
        ]);
        
        \Log::info('Generación pendiente guardada en BD', [
            'generation_id' => $generationId,
            'message_id' => $message->id,
            'conversation_id' => $conversation->id
        ]);
        
        return [
            'conversation' => $conversation,
            'message' => $message
        ];
    }

    /**
     * Guardar la generación en el historial (Base de Datos)
     */
    private function saveGenerationToHistory($prompt, $gammaData, $genesisDocId, $genesisDocName)
    {
        // 1. Crear o recuperar Conversación
        // Si estamos en una conversación existente y es persistente, la usamos.
        // Si es "Nueva conversación" (temporal), creamos una nueva en DB.
        
        $conversation = null;
        
        if ($this->currentConversationId && !str_starts_with($this->currentConversationId, 'conv_')) {
            $conversation = \App\Models\ChatConversation::find($this->currentConversationId);
        }
        
        // Determinar el título: usar prompt si existe, si no usar nombre del Genesis
        $title = !empty(trim($prompt)) 
            ? $prompt 
            : ($genesisDocName ?? 'Presentación sin título');
        $title = substr($title, 0, 50) . (strlen($title) > 50 ? '...' : '');
        
        if (!$conversation) {
            $conversation = \App\Models\ChatConversation::create([
                'user_id' => auth()->id(),
                'agent_type' => 'slide-creator',
                'model_name' => 'gamma-app',
                'title' => $title,
                'status' => 'active',
                'last_message_at' => now(),
            ]);
            
            // Actualizar ID actual
            $this->currentConversationId = $conversation->id;
        } else {
            // Actualizar timestamp
            $conversation->update(['last_message_at' => now()]);
        }

        // 2. Mensaje Usuario (Prompt)
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $prompt,
            'is_visible' => true,
        ]);

        // 3. Mensaje Asistente (Resultado)
        $presentationId = uniqid('pres_'); // ID para referencia interna si es necesario
        
        $message = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => 'Presentación generada exitosamente.',
            'attachments' => [
                'presentation_id' => $presentationId,
                'prompt' => $prompt, // Guardar prompt para facilitar visualización
                'gamma_url' => $gammaData['gammaUrl'] ?? null,
                'export_url' => $gammaData['exportUrl'] ?? null,
                'generation_id' => $gammaData['generationId'] ?? null,
                'genesis_doc_id' => $genesisDocId,
                'genesis_doc_name' => $genesisDocName,
                'credits_deducted' => $gammaData['credits']['deducted'] ?? null,
                'credits_remaining' => $gammaData['credits']['remaining'] ?? null,
            ],
            'is_visible' => true,
        ]);
        
        return [
            'conversation' => $conversation,
            'message' => $message,
            'presentation_id' => $presentationId
        ];
    }

    #[Layout('layouts.app')]
    public function render()
    {
        $activeConversation = null;
        if ($this->currentConversationId) {
            $activeConversation = collect($this->conversations)->firstWhere('id', $this->currentConversationId);
        }

        return view('livewire.agentes.generadorpresentaciones.slide-creator-agent', [
            'activeConversation' => $activeConversation
        ]);
    }
}

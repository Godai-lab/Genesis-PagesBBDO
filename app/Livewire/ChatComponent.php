<?php

namespace App\Livewire;

use App\AiAgents\ChatOpenaiAgent;
use App\AiAgents\ChatClaudeAgent;
use App\AiAgents\ChatGeminiAgent;
use App\Models\Generated;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ChatComponent extends Component
{
    use WithFileUploads;
    
    public $messages = [];
    public $newMessage = '';
    public $isLoading = false;
    public $selectedAgent = 'openai';
    public $selectedModel = null; // Modelo seleccionado para el agente actual
    public $chatSessions = [];
    public $currentSessionKey = null; // Session key único de la conversación actual
    
    // Propiedades para documentos
    public $documents = [];
    public $documentTypes = [];
    public $selectedDocumentType = '';
    public $selectedDocument = null;
    public $documentInfo = null;
    public $showDocumentSelector = false;
    
    // Propiedades para imágenes
    public $uploadedImages = []; // Array de TemporaryUploadedFile
    public $imagePreviewUrls = []; // URLs temporales para preview en el frontend
    public $isUploadingImages = false; // Control manual del estado de carga de imágenes
    
    // Propiedades para archivos externos (PDF, Word, Excel, etc.)
    public $uploadedFile = null; // TemporaryUploadedFile
    public $uploadedFileInfo = null; // Información del archivo subido
    
    // Propiedades para historial de imágenes subidas
    public $history = []; // Historial de imágenes subidas en el chat
    
    /**
     * Estructura centralizada de modelos disponibles por proveedor
     * Fácil de mantener y extender
     * El PRIMER modelo de cada lista será el modelo por defecto
     */
    public function getAvailableModels()
    {
        return [
            'openai' => [
                'gpt-4o-2024-11-20' => [
                    'name' => 'ChatGPT 4o',
                    'description' => 'Modelo más reciente de GPT-4o'
                ],
                'gpt-5.1-2025-11-13' => [
                'name' => 'GPT-5.1',
                    'description' => 'Modelo más reciente de GPT-5.1'
                ],
                'gpt-5-mini-2025-08-07' => [
                    'name' => 'GPT-5 Mini',
                    'description' => 'Modelo pequeño y rápido'
                ]
            ],
            'claude' => [
                'claude-sonnet-4-5-20250929' => [
                    'name' => 'Claude Sonnet 4.5',
                    'description' => 'Modelo balanceado (por defecto)'
                ],
                'claude-haiku-4-5-20251001' => [
                    'name' => 'Claude Haiku 4.5',
                    'description' => 'Modelo rápido y económico'
                ],
                
                'claude-opus-4-1-20250805' => [
                    'name' => 'Claude Opus 4.1',
                    'description' => 'Modelo más potente y preciso'
                ],
            ],
            'gemini' => [
                'gemini-2.5-pro' => [
                    'name' => 'Gemini 2.5 Pro',
                    'description' => 'Modelo avanzado (por defecto)'
                ],
                'gemini-2.5-flash' => [
                    'name' => 'Gemini 2.5 Flash',
                    'description' => 'Modelo rápido y eficiente'
                ],
            ],
        ];
    }
    
    /**
     * Obtiene el modelo por defecto (primer modelo) para un agente
     * 
     * @param string $agentKey Clave del agente (openai, claude, gemini)
     * @return string|null ID del modelo por defecto o null si no hay modelos
     */
    public function getDefaultModelForAgent($agentKey)
    {
        $availableModels = $this->getAvailableModels();
        $modelsForAgent = $availableModels[$agentKey] ?? [];
        
        if (empty($modelsForAgent)) {
            return null;
        }
        
        // Obtener el primer modelo del array (el primero en la lista)
        $firstModelId = array_key_first($modelsForAgent);
        
        return $firstModelId;
    }
    
    public $availableAgents = [
        'openai' => [
            'name' => 'GPT',
            'description' => 'Modelo OpenAI GPT',
            'agent_class' => ChatOpenaiAgent::class
        ],
        'claude' => [
            'name' => 'Claude',
            'description' => 'Modelo Claude de Anthropic',
            'agent_class' => ChatClaudeAgent::class
        ],
        'gemini' => [
            'name' => 'Gemini',
            'description' => 'Modelo Gemini de Google',
            'agent_class' => ChatGeminiAgent::class
        ]
    ];

    public function mount()
    {
        try {
            $this->isLoading = false; // Asegurar que comience en false
            
            // Inicializar el modelo seleccionado con el modelo por defecto del agente (primer modelo de la lista)
            if (is_null($this->selectedModel)) {
                $this->selectedModel = $this->getDefaultModelForAgent($this->selectedAgent);
            }
            
            // Generar nuevo session_key único al iniciar (nueva conversación por defecto)
            $this->currentSessionKey = $this->generateUniqueSessionKey($this->selectedAgent);
            
            Log::info('🚀 Componente montado - Nueva conversación iniciada', [
                'agent' => $this->selectedAgent,
                'session_key' => $this->currentSessionKey
            ]);
            
            $this->loadChatSessions();
            $this->loadChatHistory();
            $this->loadDocumentTypes();
            $this->loadDocuments();
            $this->loadSelectedDocument();
            $this->loadImageHistory();
        } catch (\Exception $e) {
            Log::error('❌ Error al inicializar ChatComponent:', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Asegurar que el componente sigue funcionando con valores por defecto
            $this->messages = [
                [
                    'role' => 'assistant',
                    'content' => '⚠️ Hubo un problema al cargar el chat. Por favor, recarga la página o inicia sesión nuevamente.',
                    'timestamp' => now()->format('H:i'),
                ]
            ];
            $this->isLoading = false;
        }
    }

    public function sendMessage()
    {
        if (empty(trim($this->newMessage))) {
            return;
        }

        Log::info('🚀 Iniciando sendMessage', [
            'message' => substr($this->newMessage, 0, 50) . '...'
        ]);

        // Guardar el mensaje antes de limpiarlo
        $userMessage = $this->newMessage;

        // 1. ACTIVAR INMEDIATAMENTE el estado de carga
        $this->isLoading = true;
        
        Log::info('✅ Estado de carga activado', [
            'isLoading' => $this->isLoading
        ]);

        // 2. AGREGAR mensaje del usuario al historial (con imágenes si las hay)
        $this->messages[] = [
            'role' => 'user',
            'content' => $userMessage,
            'timestamp' => now()->format('H:i'),
            'images' => !empty($this->imagePreviewUrls) ? $this->imagePreviewUrls : null
        ];

        // 3. LIMPIAR el textarea inmediatamente
        $this->newMessage = '';
        
        Log::info('📡 Disparando evento executeAgentResponse con datos', [
            'message' => substr($userMessage, 0, 50) . '...'
        ]);
        
        // 4. DISPARAR EVENTO para iniciar generación REAL
        // wire:loading wire:target="executeAgentResponse" mostrará el indicador automáticamente
        $this->dispatch('executeAgentResponse', [
            'message' => $userMessage
        ]);
    }

    /**
     * Ejecuta la llamada real al agente (separado para permitir actualización de UI)
     */
    #[On('executeAgentResponse')]
    public function executeAgentResponse($data): void
    {
        Log::info('🔄 Ejecutando respuesta del agente', [
            'message' => substr($data['message'], 0, 50) . '...',
            'timestamp' => now()->toIso8601String()
        ]);

        $userMessage = $data['message'];

        try {
            $agentClass = $this->availableAgents[$this->selectedAgent]['agent_class'];
            $sessionKey = $this->getSessionKey();
            
            Log::info('Configuración del agente:', [
                'agent_class' => $agentClass,
                'selected_agent' => $this->selectedAgent,
                'session_key' => $sessionKey,
            ]);
            
            $agent = $agentClass::for($sessionKey);
            
            // ✅ Validar que el modelo seleccionado sea válido para el agente actual
            $availableModels = $this->getAvailableModels();
            $validModels = $availableModels[$this->selectedAgent] ?? [];
            
            // Si el modelo seleccionado no es válido para este agente, usar el modelo por defecto (primer modelo)
            if ($this->selectedModel && !isset($validModels[$this->selectedModel])) {
                $defaultModel = $this->getDefaultModelForAgent($this->selectedAgent);
                Log::warning('Modelo inválido para el agente, usando modelo por defecto:', [
                    'selected_model' => $this->selectedModel,
                    'agent' => $this->selectedAgent,
                    'default_model' => $defaultModel
                ]);
                $this->selectedModel = $defaultModel;
            }
            
            // ✅ Aplicar modelo seleccionado usando withModel()
            // Esto permite cambiar el modelo dinámicamente incluso si es el por defecto
            if ($this->selectedModel) {
                $agent = $agent->withModel($this->selectedModel);
                Log::info('Modelo aplicado:', [
                    'selected_model' => $this->selectedModel,
                    'agent' => $this->selectedAgent,
                    'is_valid' => isset($validModels[$this->selectedModel])
                ]);
            }
            
            // Log adicional para debuggear la instancia del agente
            Log::info('Agente instanciado:', [
                'agent_class' => get_class($agent),
                'provider' => $agent->provider ?? 'no provider set',
                'model' => $agent->model ?? 'no model set',
                'history' => $agent->history ?? 'no history set',
                'selected_model' => $this->selectedModel,
            ]);

            // ✅ Preparar imágenes si hay imágenes subidas
            // Gemini ahora soporta imágenes con nuestro driver personalizado NativeGeminiDriver
            $imageUrls = $this->prepareImagesForAgent();
            if (!empty($imageUrls)) {
                Log::info('🖼️ Agregando imágenes al agente', [
                    'count' => count($imageUrls),
                    'urls' => $imageUrls,
                    'agent' => $this->selectedAgent
                ]);
                $agent = $agent->withImages($imageUrls);
                
                // ✅ Actualizar el último mensaje del usuario con las URLs finales de S3
                if (!empty($this->messages)) {
                    $lastIndex = count($this->messages) - 1;
                    if ($this->messages[$lastIndex]['role'] === 'user') {
                        $this->messages[$lastIndex]['images'] = $imageUrls;
                        // Guardar en sesión actualizada
                        $sessionKey = 'chat_messages_' . $this->selectedAgent;
                        session()->put($sessionKey, $this->messages);
                    }
                }
            }

            // ✅ Preparar archivo externo si hay uno subido
            if ($this->uploadedFile && $this->uploadedFileInfo) {
                // Agregar información del archivo al mensaje para que el agente lo detecte
                $userMessage .= "\n\n[El usuario ha subido un archivo: {$this->uploadedFileInfo['name']} ({$this->uploadedFileInfo['type']}). Por favor, usa la herramienta read_external_file con file_path=\"{$this->uploadedFileInfo['path']}\" y file_type=\"{$this->uploadedFileInfo['type']}\" para leer su contenido.]";
                
                Log::info('📄 Archivo externo disponible para el agente', [
                    'name' => $this->uploadedFileInfo['name'],
                    'type' => $this->uploadedFileInfo['type'],
                    'path' => $this->uploadedFileInfo['path'],
                    'agent' => $this->selectedAgent
                ]);
            }

            Log::info('Enviando mensaje:', [
                'selected_agent' => $this->selectedAgent,
                'user_message' => $userMessage,
                'session_key' => $sessionKey,
                'has_images' => !empty($imageUrls),
                'image_count' => count($imageUrls)
            ]);

            // Manejar cada agente de forma específica según sus necesidades
           // DESPUÉS - Todos los agentes usan el mismo método
            try {
                $response = $agent->respond($userMessage);
            } catch (\Exception $agentError) {
                Log::error('Error específico del agente:', [
                    'error' => $agentError->getMessage(),
                    'file' => $agentError->getFile(),
                    'line' => $agentError->getLine(),
                    'agent' => $this->selectedAgent,
                ]);
                throw $agentError;
            }

            // Verificar que el historial se guardó
            $history = $agent->chatHistory();
            $messages = $history->getMessages();
            
            // Forzar el guardado del historial si es necesario
            if (method_exists($history, 'writeToMemory')) {
                $history->writeToMemory();
            }
            
            Log::info('Respuesta recibida:', [
                'response_length' => is_array($response) ? count($response) : strlen($response),
                'response_preview' => is_array($response) ? json_encode($response) : substr($response, 0, 200) . '...',
                'response_type' => gettype($response),
                'agent' => $this->selectedAgent,
                'session_key' => $sessionKey,
                'history_messages_count' => count($messages),
            ]);
            
            // ✅ Normalizar respuesta a formato estructurado JSON (text + images)
            $normalizedResponse = $this->normalizeAgentResponse($response);
            
            Log::info('📦 Respuesta normalizada', [
                'text_length' => strlen($normalizedResponse['text']),
                'has_images' => !empty($normalizedResponse['images']),
                'image_count' => count($normalizedResponse['images'] ?? [])
            ]);
            
            // Agregar mensaje normalizado
            $this->messages[] = [
                'role' => 'assistant',
                'content' => $normalizedResponse['text'],
                'timestamp' => now()->format('H:i'),
                'images' => $normalizedResponse['images']
            ];

            Log::info('✅ Respuesta del agente completada');
            // wire:loading se ocultará automáticamente al terminar el método
        } catch (\Exception $e) {
            Log::error('Error en sendMessage:', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'selected_agent' => $this->selectedAgent,
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Mensaje de error específico según el agente
            $errorMessage = '⚠️ Lo siento, hubo un error al procesar tu mensaje.';
            if ($this->selectedAgent === 'gemini') {
                $errorMessage = '⚠️ Error con Gemini. Verifica tu API key de Google AI.';
            } elseif ($this->selectedAgent === 'claude') {
                $errorMessage = '⚠️ Error con Claude. Verifica tu API key de Anthropic.';
            }

            $this->messages[] = [
                'role' => 'assistant',
                'content' => $errorMessage,
                'timestamp' => now()->format('H:i')
            ];

            Log::info('❌ Error manejado, wire:loading se ocultará automáticamente');
        } finally {
            $this->isLoading = false;
            
            // ✅ Limpiar imágenes después de enviar (éxito o error)
            $this->clearUploadedImages();
            
            // ✅ Limpiar archivo después de procesarlo
            $this->clearUploadedFile();
            
            // ✅ Actualizar sidebar de forma segura (no bloquea porque es el último paso)
            $this->loadChatSessions();
            
            Log::info('🏁 Finalizando executeAgentResponse', [
                'isLoading' => $this->isLoading
            ]);
        }
    }

    public function clearChat()
    {
        try {
            if (!auth()->check()) {
                return;
            }
            
            // Obtener conversación actual
            $conversation = $this->getCurrentConversation();
            
            if ($conversation) {
                // Marcar mensajes como eliminados (soft delete)
                ChatMessage::where('conversation_id', $conversation->id)
                    ->update(['is_deleted' => true]);
                
                Log::info('🧹 Mensajes limpiados en BD', [
                    'conversation_id' => $conversation->id,
                    'agent' => $this->selectedAgent
                ]);
            }

            // Limpiar los mensajes del componente
            $this->messages = [];
            
            // Agregar mensaje de bienvenida
            $this->messages[] = [
                'role' => 'assistant',
                'content' => '¡Hola! Soy ' . $this->availableAgents[$this->selectedAgent]['name'] . '. ¿En qué puedo ayudarte hoy?',
                'timestamp' => now()->format('H:i'),
            ];

            // Actualizar la lista de conversaciones
            $this->loadChatSessions();
            
            Log::info('✅ Chat limpiado exitosamente', [
                'agent' => $this->selectedAgent,
                'user_id' => auth()->id()
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Error al limpiar chat:', [
                'error' => $e->getMessage(),
                'agent' => $this->selectedAgent,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function switchAgent($agentKey)
    {
        if (isset($this->availableAgents[$agentKey])) {
            $oldAgent = $this->selectedAgent;
            $this->selectedAgent = $agentKey;
            $this->isLoading = false;
            
            // Actualizar el modelo seleccionado al modelo por defecto del nuevo agente
            $this->selectedModel = $this->getDefaultModelForAgent($agentKey);
            
            Log::info('🔄 Cambiando de agente', [
                'from' => $oldAgent,
                'to' => $agentKey,
                'user_id' => auth()->id()
            ]);
            
            // Cargar historial del nuevo agente desde BD
            // Esto buscará una conversación existente o creará una nueva automáticamente
            $this->loadChatHistory();
            
            // Si no hay conversación para este agente, el historial ya incluirá mensaje de bienvenida
            // No necesitamos hacer nada adicional
            
            // Actualizar la lista de conversaciones en el sidebar
            $this->loadChatSessions();
            
            Log::info('✅ Agente cambiado exitosamente', [
                'agent' => $agentKey,
                'message_count' => count($this->messages)
            ]);
        }
    }
    
    /**
     * Hook de Livewire: Se ejecuta automáticamente cuando cambia selectedAgent
     * Útil como respaldo cuando se cambia el agente desde otros lugares
     */
    public function updatedSelectedAgent()
    {
        // Cuando cambia el agente, generar nuevo session_key único
        // Esto asegura que cada agente tenga su propia conversación nueva
        $this->currentSessionKey = $this->generateUniqueSessionKey($this->selectedAgent);
        
        // Actualizar el modelo al por defecto (primer modelo)
        if (isset($this->availableAgents[$this->selectedAgent])) {
            $this->selectedModel = $this->getDefaultModelForAgent($this->selectedAgent);
            
            Log::info('🔄 Agente actualizado - Nueva conversación iniciada', [
                'agent' => $this->selectedAgent,
                'model_set' => $this->selectedModel,
                'session_key' => $this->currentSessionKey
            ]);
        }
        
        // Limpiar mensajes y mostrar bienvenida del nuevo agente
        $this->messages = [];
        $this->messages[] = [
            'role' => 'assistant',
            'content' => '¡Hola! Soy ' . $this->availableAgents[$this->selectedAgent]['name'] . '. ¿En qué puedo ayudarte hoy?',
            'timestamp' => now()->format('H:i'),
        ];
        
        // Recargar conversaciones del nuevo agente
        $this->loadChatSessions();
    }
    
    /**
     * Cambia el modelo seleccionado para el agente actual
     */
    public function switchModel($modelId)
    {
        $availableModels = $this->getAvailableModels();
        
        if (isset($availableModels[$this->selectedAgent][$modelId])) {
            $this->selectedModel = $modelId;
            
            Log::info('Modelo cambiado:', [
                'agent' => $this->selectedAgent,
                'model' => $modelId
            ]);
        }
    }
    
    /**
     * Obtiene los modelos disponibles para el agente actual
     * Método helper para la vista
     */
    public function getModelsForCurrentAgent()
    {
        $availableModels = $this->getAvailableModels();
        return $availableModels[$this->selectedAgent] ?? [];
    }

    private function loadChatSessions()
    {
        $this->chatSessions = [];
        
        if (!auth()->check()) {
            return;
        }
        
        try {
            // ✅ Cargar SOLO conversaciones de chat (agent_type = 'chat-agent')
            // Excluir conversaciones de otros agentes como 'slide-creator' (Gamma)
            $conversations = ChatConversation::where('user_id', auth()->id())
                ->where('agent_type', 'chat-agent') // ← Filtrar solo conversaciones de chat
                ->where('status', 'active')
                ->orderBy('last_message_at', 'desc')
                ->limit(10) // Últimas 10 conversaciones
                ->get();
            
            foreach ($conversations as $conversation) {
                // ✅ Obtener el último mensaje visible directamente desde BD
                $lastMessage = ChatMessage::where('conversation_id', $conversation->id)
                    ->where('is_deleted', false)
                    ->where('is_visible', true)
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                // Obtener contenido del último mensaje
                $lastContent = 'Sin mensajes';
                if ($lastMessage && !empty($lastMessage->content)) {
                    $lastContent = substr($lastMessage->content, 0, 100);
                    if (strlen($lastMessage->content) > 100) {
                        $lastContent .= '...';
                    }
                }
                
                // ✅ Obtener el agente real desde context_metadata o model_name
                // El model_name puede indicar qué agente se usó (gpt-*, claude-*, gemini-*)
                $realAgentKey = $this->detectAgentFromConversation($conversation);
                $agentName = $this->availableAgents[$realAgentKey]['name'] ?? 'Chat';
                
                $this->chatSessions[] = [
                    'conversation_id' => $conversation->id,
                    'agent_key' => $realAgentKey, // Agente real (openai, claude, gemini)
                    'agent_name' => $agentName,
                    'session_id' => $conversation->id, // Usar ID de conversación
                    'title' => $conversation->title,
                    'last_message' => $lastContent,
                    'message_count' => $conversation->message_count,
                    'last_activity' => $conversation->last_message_at?->format('H:i') ?? now()->format('H:i'),
                    'is_current' => $realAgentKey === $this->selectedAgent && 
                                   $conversation->id === $this->getCurrentConversationId()
                ];
            }
            
            Log::info('✅ Conversaciones cargadas desde BD', [
                'count' => count($this->chatSessions),
                'user_id' => auth()->id()
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Error al cargar conversaciones desde BD:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Obtener el ID de la conversación actual
     */
    private function getCurrentConversationId(): ?int
    {
        $conversation = $this->getCurrentConversation();
        return $conversation?->id;
    }
    
    /**
     * Detecta el agente real (openai, claude, gemini) desde una conversación
     * basándose en el model_name o context_metadata
     * 
     * @param ChatConversation $conversation
     * @return string Agente detectado (openai, claude, gemini)
     */
    private function detectAgentFromConversation(ChatConversation $conversation): string
    {
        // ✅ Primero intentar desde context_metadata (más confiable)
        $metadata = $conversation->context_metadata ?? [];
        if (isset($metadata['original_agent_type']) && in_array($metadata['original_agent_type'], ['openai', 'claude', 'gemini'])) {
            return $metadata['original_agent_type'];
        }
        
        // Intentar detectar desde model_name
        $modelName = strtolower($conversation->model_name ?? '');
        
        if (str_starts_with($modelName, 'gpt-') || str_starts_with($modelName, 'gpt')) {
            return 'openai';
        }
        
        if (str_starts_with($modelName, 'claude-') || str_starts_with($modelName, 'claude')) {
            return 'claude';
        }
        
        if (str_starts_with($modelName, 'gemini-') || str_starts_with($modelName, 'gemini')) {
            return 'gemini';
        }
        
        // Fallback: usar el agente seleccionado actualmente
        return $this->selectedAgent;
    }


    private function loadChatHistory()
    {
        if (!auth()->check()) {
            $this->messages = [
                [
                    'role' => 'assistant',
                    'content' => '¡Hola! Por favor inicia sesión para usar el chat.',
                    'timestamp' => now()->format('H:i'),
                ]
            ];
            return;
        }

        try {
            // Obtener conversación actual desde la BD
            $conversation = $this->getCurrentConversation();
            
            if (!$conversation) {
                // No hay conversación, mostrar mensaje de bienvenida
                $this->messages = [
                    [
                        'role' => 'assistant',
                        'content' => '¡Hola! Soy ' . $this->availableAgents[$this->selectedAgent]['name'] . '. ¿En qué puedo ayudarte hoy?',
                        'timestamp' => now()->format('H:i'),
                    ]
                ];
                return;
            }
            
            // Cargar solo mensajes visibles (excluir tools y system)
            $dbMessages = ChatMessage::where('conversation_id', $conversation->id)
                ->where('is_deleted', false)
                ->where('is_visible', true) // ← Solo visibles para el usuario
                ->orderBy('created_at', 'asc')
                ->get();
            
            $this->messages = [];
            
            if ($dbMessages->isEmpty()) {
                // Conversación existe pero sin mensajes, mostrar bienvenida
                $this->messages[] = [
                    'role' => 'assistant',
                    'content' => '¡Hola! Soy ' . $this->availableAgents[$this->selectedAgent]['name'] . '. ¿En qué puedo ayudarte hoy?',
                    'timestamp' => now()->format('H:i'),
                ];
            } else {
                // Cargar mensajes existentes
                foreach ($dbMessages as $dbMessage) {
                    $this->messages[] = [
                        'role' => $dbMessage->role,
                        'content' => $dbMessage->content,
                        'timestamp' => $dbMessage->created_at->format('H:i'),
                        'images' => $dbMessage->attachments['images'] ?? null,
                    ];
                }
            }
            
            Log::info('✅ Historial cargado desde BD', [
                'conversation_id' => $conversation->id,
                'message_count' => count($this->messages),
                'agent' => $this->selectedAgent
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Error al cargar historial desde BD:', [
                'error' => $e->getMessage(),
                'agent' => $this->selectedAgent,
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->messages = [
                [
                    'role' => 'assistant',
                    'content' => '¡Hola! Soy ' . $this->availableAgents[$this->selectedAgent]['name'] . '. ¿En qué puedo ayudarte hoy?',
                    'timestamp' => now()->format('H:i'),
                ]
            ];
        }
    }

    private function extractContent($messageArray)
    {
        $content = $messageArray['content']
            ?? $messageArray['message']
            ?? $messageArray['text']
            ?? '';

        // Manejar respuestas estructuradas de LarAgent
        if (is_array($content)) {
            // Si es un array de objetos con estructura de LarAgent (como [{"type": "text", "text": "contenido"}])
            if (isset($content[0]['type']) && isset($content[0]['text'])) {
                // Extraer solo el texto de la estructura
                $textContent = '';
                foreach ($content as $item) {
                    if (isset($item['type']) && $item['type'] === 'text' && isset($item['text'])) {
                        $textContent .= $item['text'];
                    }
                }
                $content = $textContent;
            } else {
                // Si es un array simple, convertir a JSON legible
                $content = json_encode($content, JSON_UNESCAPED_UNICODE);
            }
        }

        // Limpiar caracteres de control y devolver string
        return preg_replace('/[\x00-\x1F\x7F]/', '', (string) $content);
    }


    private function extractRole($messageArray)
    {
        $role = $messageArray['role']
            ?? $messageArray['type']
            ?? $messageArray['sender']
            ?? 'assistant';

        return in_array($role, ['user', 'assistant']) ? $role : 'assistant';
    }

    private function isAgentInstruction($content, $role)
    {
        // Filtrar prompts modificados que aparecen como mensajes del usuario
        if ($role === 'user') {
            $promptPatterns = [
                'Tienes acceso a un documento seleccionado',
                'El usuario puede hacer consultas específicas sobre este documento',
                'El usuario tiene un documento seleccionado',
                'El usuario necesita ayuda con un documento que ha seleccionado',
                'Puedes usar la herramienta get_document_context',
                'para obtener el contenido del documento y responder sus preguntas',
            ];

            foreach ($promptPatterns as $pattern) {
                if (strpos($content, $pattern) !== false) {
                    return true;
                }
            }
        }

        // Filtrar output de tools que aparecen como mensajes del asistente
        if ($role === 'assistant') {
            $toolOutputPatterns = [
                '{"document_id":',
                '{"type":"tool_use"',
                '{"type":"tool_result"',
                'get_document_context',
                'document_name',
                'document_type',
                'truncated":true',
            ];

            foreach ($toolOutputPatterns as $pattern) {
                if (strpos($content, $pattern) !== false) {
                    return true;
                }
            }
        }

        // Solo filtrar si es un mensaje del asistente
        if ($role !== 'assistant') {
            return false;
        }

        // Lista de instrucciones conocidas de los agentes
        $knownInstructions = [
            "Eres un asistente de chat experto y amigable. Mantén un tono profesional, útil y en español.",
            "Eres un moderador de chat experto y amigable. Mantén un tono profesional, útil y en español.",
        ];

        // Verificar si el contenido coincide exactamente con alguna instrucción
        foreach ($knownInstructions as $instruction) {
            if (trim($content) === trim($instruction)) {
                return true;
            }
        }

        // Verificar si contiene patrones típicos de instrucciones
        $instructionPatterns = [
            'Eres un asistente',
            'Mantén un tono profesional',
            'Eres un moderador',
        ];

        foreach ($instructionPatterns as $pattern) {
            if (strpos($content, $pattern) !== false && strlen($content) < 200) {
                return true;
            }
        }

        return false;
    }

    private function getSessionKey()
    {
        return $this->getSessionKeyForAgent($this->selectedAgent);
    }

    private function getSessionKeyForAgent($agentKey)
    {
        // Si ya hay un session_key único para este agente, usarlo
        if ($this->currentSessionKey && $this->selectedAgent === $agentKey) {
            return $this->currentSessionKey;
        }
        
        // Si no hay session_key, generar uno nuevo único
        // Esto permite múltiples conversaciones por agente
        return $this->generateUniqueSessionKey($agentKey);
    }
    
    /**
     * Genera un session_key único para una nueva conversación
     */
    private function generateUniqueSessionKey($agentKey): string
    {
        if (auth()->check()) {
            $userId = auth()->id();
            // Usar timestamp + random para garantizar unicidad
            $uniqueId = uniqid('', true) . '-' . time();
            return 'chat-' . $agentKey . '-user-' . $userId . '-' . $uniqueId;
        } else {
            $sessionId = session()->getId();
            $uniqueId = uniqid('', true) . '-' . time();
            return 'chat-' . $agentKey . '-' . $sessionId . '-' . $uniqueId;
        }
    }

    public function loadSession($conversationId)
    {
        if (!auth()->check()) {
            return;
        }
        
        try {
            // Cargar conversación desde BD usando el método helper
            $this->loadConversationById($conversationId);
            
            // Actualizar lista de conversaciones
            $this->loadChatSessions();
            
            Log::info('✅ Sesión cargada', [
                'conversation_id' => $conversationId,
                'agent' => $this->selectedAgent
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Error al cargar sesión:', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function createNewSession()
    {
        // Generar nuevo session_key único para esta conversación
        $this->currentSessionKey = $this->generateUniqueSessionKey($this->selectedAgent);
        
        Log::info('🆕 Nueva sesión creada', [
            'agent' => $this->selectedAgent,
            'session_key' => $this->currentSessionKey
        ]);
        
        // Limpiar mensajes
        $this->messages = [];
        $this->messages[] = [
            'role' => 'assistant',
            'content' => '¡Hola! Soy ' . $this->availableAgents[$this->selectedAgent]['name'] . '. ¿En qué puedo ayudarte hoy?',
            'timestamp' => now()->format('H:i'),
        ];
        
        // Recargar lista de conversaciones
        $this->loadChatSessions();
    }

    public function deleteSession($conversationId)
    {
        try {
            if (!auth()->check()) {
                return;
            }
            
            // Usar el método helper que ya teníamos
            $this->deleteConversationById($conversationId);
            
            Log::info('✅ Conversación eliminada', [
                'conversation_id' => $conversationId,
                'user_id' => auth()->id()
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Error al eliminar conversación:', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Envía mensaje a Claude normalizando el historial al formato esperado
     * 
     * Claude espera que cada mensaje tenga la estructura:
     * ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'contenido']]]
     * 
     * @param object $agent Instancia del agente Claude
     * @param string $userMessage Mensaje del usuario
     * @return string Respuesta de Claude
     */
    private function sendToClaudeWithHistory($agent, $userMessage)
    {
        // Limpiar historial para evitar errores
        $agent->clear();
        
        // Enviar mensaje simple sin historial
        return $agent->respond($userMessage);
    }

    /**
     * Carga los tipos de documentos disponibles
     */
    public function loadDocumentTypes()
    {
        $this->documentTypes = [
            '' => 'Todos los tipos',
            'Brief' => 'Brief',
            'Genesis' => 'Génesis',
            'Investigacion' => 'Investigación',
            'Creatividad' => 'Asistente Creativo',
            'Grafica' => 'Asistente Gráfico',
            'SocialMedia' => 'Social Media',
            'Innovacion' => 'Innovación'
        ];
    }

    /**
     * Carga los documentos según los permisos del usuario y filtros
     */
    public function loadDocuments()
    {
        $user = auth()->user();
        
        // Si no hay usuario autenticado, retornar array vacío
        if (!$user) {
            $this->documents = [];
            return;
        }
        
        // Crear la consulta base
        $query = Generated::select('id', 'name', 'key', 'account_id', 'created_at')
                          ->where('status', 'completed') // Solo documentos completados
                          ->orderBy('created_at', 'desc')
                          ->limit(50); // Limitamos a 50 documentos recientes
        
        // Aplicar filtro por tipo si está seleccionado
        if (!empty($this->selectedDocumentType)) {
            $query->where('key', $this->selectedDocumentType);
        }
        
        // Si el usuario tiene acceso completo, puede ver todos los documentos
        if ($user->haveFullAccess()) {
            $documents = $query->get();
        } else {
            $accountIds = $user->accounts->pluck('id')->toArray();
            $documents = $query->whereIn('account_id', $accountIds)->get();
        }
        
        // Transformar los datos para la vista
        $this->documents = $documents->map(function($doc) {
            $tipo = $this->getDocumentTypeName($doc->key);
            return [
                'id' => $doc->id,
                'name' => $doc->name,
                'type' => $doc->key,
                'type_name' => $tipo,
                'account' => $doc->account ? $doc->account->name : 'Sin cuenta',
                'date' => $doc->created_at->format('d/m/Y H:i'),
                'date_short' => $doc->created_at->format('d/m/Y')
            ];
        })->toArray();
    }

    /**
     * Obtiene un nombre amigable para el tipo de documento
     */
    private function getDocumentTypeName($key)
    {
        $tipos = [
            'Brief' => 'Brief',
            'Genesis' => 'Génesis',
            'Investigacion' => 'Investigación',
            'Creatividad' => 'Asistente Creativo',
            'Grafica' => 'Asistente Gráfico',
            'SocialMedia' => 'Social Media',
            'Innovacion' => 'Innovación'
        ];
        
        return $tipos[$key] ?? $key;
    }

    /**
     * Filtra documentos por tipo
     */
    public function filterByType($type = '')
    {
        $this->selectedDocumentType = $type;
        $this->loadDocuments();
    }

    /**
     * Selecciona un documento para consultar
     */
    public function selectDocument($documentId)
    {
        $document = Generated::find($documentId);
        
        if ($document) {
            $user = auth()->user();
            
            // Si no hay usuario autenticado, no permitir selección
            if (!$user) {
                $this->selectedDocument = null;
                $this->documentInfo = null;
                return;
            }
            
            $puedeAcceder = $user->haveFullAccess() || $user->accounts->pluck('id')->contains($document->account_id);

            if ($puedeAcceder) {
                $this->selectedDocument = $documentId;
                $this->documentInfo = [
                    'id' => $document->id,
                    'name' => $document->name,
                    'type' => $this->getDocumentTypeName($document->key),
                    'account' => $document->account ? $document->account->name : 'Sin cuenta'
                ];
                
                // Guardar el ID en la sesión para futuras consultas
                session()->put('chat_document', $document->id);
            } else {
                $this->selectedDocument = null;
                $this->documentInfo = null;
                session()->forget('chat_document');
            }
        } else {
            $this->selectedDocument = null;
            $this->documentInfo = null;
            session()->forget('chat_document');
        }
    }

    /**
     * Quita el documento seleccionado
     */
    public function removeSelectedDocument()
    {
        $this->selectedDocument = null;
        $this->documentInfo = null;
        session()->forget('chat_document');
    }

    /**
     * Toggle del selector de documentos
     */
    public function toggleDocumentSelector()
    {
        $this->showDocumentSelector = !$this->showDocumentSelector;
    }

    /**
     * Confirma la selección del documento
     */
    public function confirmDocumentSelection()
    {
        $this->showDocumentSelector = false;
    }

    /**
     * Carga el documento seleccionado desde la sesión
     */
    public function loadSelectedDocument()
    {
        $documentId = session()->get('chat_document');
        if ($documentId) {
            $document = Generated::find($documentId);
            if ($document) {
                $user = auth()->user();
                
                // Si no hay usuario autenticado, limpiar selección
                if (!$user) {
                    $this->selectedDocument = null;
                    $this->documentInfo = null;
                    session()->forget('chat_document');
                    return;
                }
                
                $puedeAcceder = $user->haveFullAccess() || $user->accounts->pluck('id')->contains($document->account_id);
                
                if ($puedeAcceder) {
                    $this->selectedDocument = $documentId;
                    $this->documentInfo = [
                        'id' => $document->id,
                        'name' => $document->name,
                        'type' => $this->getDocumentTypeName($document->key),
                        'account' => $document->account ? $document->account->name : 'Sin cuenta'
                    ];
                } else {
                    // Si no tiene permisos, limpiar la selección
                    $this->selectedDocument = null;
                    $this->documentInfo = null;
                    session()->forget('chat_document');
                }
            } else {
                // Si el documento no existe, limpiar la selección
                $this->selectedDocument = null;
                $this->documentInfo = null;
                session()->forget('chat_document');
            }
        }
    }

    public function render()
    {
        return view('livewire.chat-component');
    }

    // ============================================================================
    // MÉTODOS HELPER PARA MANEJO DE IMÁGENES
    // ============================================================================
    // Estos métodos pueden ser extraídos a un Trait o Servicio si se necesita
    // reutilizarlos en otros componentes. Por ejemplo: app/Traits/HandlesImageUploads.php
    // ============================================================================

    /**
     * ✅ Prepara las imágenes para enviar al agente (sube a S3 y retorna URLs)
     * 
     * Este método:
     * 1. Recorre todas las imágenes subidas
     * 2. Las sube a S3 en la carpeta 'chat-images/'
     * 3. Retorna un array de URLs públicas
     * 
     * @return array Array de URLs de imágenes en S3
     */
    private function prepareImagesForAgent(): array
    {
        if (empty($this->uploadedImages)) {
            return [];
        }

        $imageUrls = [];
        
        foreach ($this->uploadedImages as $index => $image) {
            if (!$image) {
                continue;
            }
            
            try {
                $url = $this->uploadImageToS3($image, $index);
                if ($url) {
                    $imageUrls[] = $url;
                }
            } catch (\Exception $e) {
                Log::error('Error preparando imagen para agente:', [
                    'index' => $index,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                // Continuar con la siguiente imagen
            }
        }
        
        return $imageUrls;
    }

    /**
     * ✅ Sube una imagen a S3 y retorna la URL pública
     * 
     * Patrón usado en:
     * - app/Livewire/VideoEditor.php
     * - app/Livewire/Generador/Herramientas/ImageEditor.php
     * - app/Livewire/Generador/Herramientas/ImageGenerator.php
     * 
     * @param \Livewire\TemporaryUploadedFile $image Imagen a subir
     * @param int $index Índice de la imagen (para logging)
     * @return string|null URL pública de la imagen en S3, o null si falla
     */
    private function uploadImageToS3($image, int $index = 0): ?string
    {
        try {
            // Generar nombre único para el archivo
            $extension = $image->getClientOriginalExtension();
            $fileName = 'genesis/input-images/' 
                . now()->format('Ymd_His') 
                . '_chat_' 
                . uniqid("img_{$index}_") 
                . '.' . $extension;
            
            // Leer el contenido del archivo
            $imageContent = file_get_contents($image->getRealPath());
            
            // Subir a S3
            Storage::disk('s3')->put($fileName, $imageContent);
            
            // Obtener URL pública (Laravel maneja la configuración automáticamente)
            $url = Storage::disk('s3')->url($fileName);
            
            Log::info('📤 Imagen subida a S3 exitosamente', [
                'index' => $index,
                'fileName' => $fileName,
                'url' => $url,
                'size' => strlen($imageContent),
                'extension' => $extension
            ]);
            
            return $url;
            
        } catch (\Exception $e) {
            Log::error('💥 Error subiendo imagen a S3:', [
                'index' => $index,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return null;
        }
    }

    /**
     * ✅ Livewire hook: Se ejecuta cuando se suben imágenes
     * 
     * Este método:
     * 1. Valida el tipo de archivo (solo imágenes)
     * 2. Valida el tamaño (máximo 10MB por imagen)
     * 3. Genera URLs temporales para preview en el frontend
     * 4. Limita a máximo 4 imágenes
     */
    public function updatedUploadedImages()
    {
        // Activar el indicador de carga
        $this->isUploadingImages = true;
        
        // Limpiar URLs previas para evitar acumulación
        $this->imagePreviewUrls = [];
        
        if (empty($this->uploadedImages)) {
            $this->isUploadingImages = false;
            return;
        }
        
        // Limitar a máximo 4 imágenes
        if (count($this->uploadedImages) > 4) {
            $this->addError('uploadedImages', 'Máximo 4 imágenes permitidas');
            $this->uploadedImages = array_slice($this->uploadedImages, 0, 4);
        }
        
        // Validar cada imagen
        $validImages = [];
        foreach ($this->uploadedImages as $key => $image) {
            if (!$image) {
                continue;
            }
            
            try {
                // Validar tipo de archivo
                $mimeType = $image->getMimeType();
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/jpg'];
                
                if (!in_array($mimeType, $allowedTypes)) {
                    $this->addError('uploadedImages', 'Solo se permiten imágenes (JPG, PNG, GIF, WEBP)');
                    continue;
                }
                
                // Validar tamaño (10MB máximo)
                $maxSize = 10 * 1024 * 1024; // 10MB en bytes
                if ($image->getSize() > $maxSize) {
                    $this->addError('uploadedImages', 'La imagen es demasiado grande (máximo 10MB)');
                    continue;
                }
                
                // Generar URL temporal para preview
                $tempUrl = $image->temporaryUrl();
                
                if ($tempUrl) {
                    $this->imagePreviewUrls[] = $tempUrl;
                    $validImages[] = $image;
                    
                    Log::info('✅ Preview generado exitosamente', [
                        'index' => $key,
                        'url_length' => strlen($tempUrl),
                        'mime' => $mimeType,
                        'size' => $image->getSize()
                    ]);
                }
                
            } catch (\Exception $e) {
                Log::error('❌ Error procesando imagen:', [
                    'index' => $key,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                $this->addError('uploadedImages', 'Error al procesar una imagen');
            }
        }
        
        // Actualizar con solo las imágenes válidas
        $this->uploadedImages = array_values($validImages);
        
        Log::info('✅ Procesamiento de imágenes completado', [
            'valid_images' => count($this->uploadedImages),
            'preview_urls' => count($this->imagePreviewUrls)
        ]);
        
        // Desactivar el indicador de carga
        $this->isUploadingImages = false;
    }

    /**
     * ✅ Remueve una imagen específica del array
     * 
     * @param int $index Índice de la imagen a remover
     */
    public function removeImage(int $index)
    {
        Log::info('🗑️ Intentando remover imagen', [
            'index' => $index,
            'total_images' => count($this->uploadedImages),
            'total_previews' => count($this->imagePreviewUrls)
        ]);
        
        // Verificar que el índice existe
        if (isset($this->uploadedImages[$index])) {
            // Remover imagen
            unset($this->uploadedImages[$index]);
            $this->uploadedImages = array_values($this->uploadedImages);
            
            // Remover preview correspondiente
            if (isset($this->imagePreviewUrls[$index])) {
                unset($this->imagePreviewUrls[$index]);
                $this->imagePreviewUrls = array_values($this->imagePreviewUrls);
            }
            
            Log::info('✅ Imagen removida exitosamente', [
                'index' => $index,
                'remaining_images' => count($this->uploadedImages),
                'remaining_previews' => count($this->imagePreviewUrls)
            ]);
        } else {
            Log::warning('⚠️ Índice de imagen no encontrado', ['index' => $index]);
        }
    }

    /**
     * ✅ Limpia todas las imágenes subidas
     * 
     * Este método se llama después de enviar el mensaje al agente
     * para limpiar el estado y liberar memoria
     */
    private function clearUploadedImages()
    {
        if (!empty($this->uploadedImages)) {
            Log::info('🧹 Limpiando imágenes subidas', [
                'count' => count($this->uploadedImages)
            ]);
        }
        
        $this->uploadedImages = [];
        $this->imagePreviewUrls = [];
    }

    // ============================================================================
    // MÉTODOS HELPER PARA MANEJO DE ARCHIVOS EXTERNOS
    // ============================================================================

    /**
     * ✅ Livewire hook: Se ejecuta cuando se sube un archivo
     * 
     * Este método:
     * 1. Valida el tipo de archivo (PDF, Word, Excel, CSV, TXT) - por extensión y MIME type
     * 2. Valida el tamaño (máximo 15MB)
     * 3. Guarda la información del archivo para procesarlo después
     * 
     * ⚡ Validación rápida: Primero valida por extensión (más rápido) y luego por MIME type
     */
    public function updatedUploadedFile()
    {
        // Limpiar estado previo inmediatamente
        $this->resetErrorBag('uploadedFile');
        
        if (!$this->uploadedFile) {
            $this->uploadedFileInfo = null;
            return;
        }

        try {
            // ✅ VALIDACIÓN RÁPIDA: Primero por extensión (más rápido que MIME type)
            $extension = strtolower($this->uploadedFile->getClientOriginalExtension());
            $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt'];
            
            if (!in_array($extension, $allowedExtensions)) {
                $this->addError('uploadedFile', '❌ Formato no permitido. Solo se aceptan: PDF, Word (.doc, .docx), Excel (.xls, .xlsx), CSV, TXT');
                $this->uploadedFile = null;
                $this->uploadedFileInfo = null;
                $this->dispatch('file-upload-error', message: 'Formato no permitido');
                return;
            }

            // Validar tamaño ANTES de procesar MIME type (más rápido)
            $maxSize = 15 * 1024 * 1024; // 15MB en bytes
            if ($this->uploadedFile->getSize() > $maxSize) {
                $this->addError('uploadedFile', '❌ El archivo es demasiado grande. Tamaño máximo: 15MB');
                $this->uploadedFile = null;
                $this->uploadedFileInfo = null;
                $this->dispatch('file-upload-error', message: 'Archivo demasiado grande');
                return;
            }

            // Validar MIME type (validación adicional de seguridad)
            $mimeType = $this->uploadedFile->getMimeType();
            $allowedMimeTypes = [
                'application/pdf',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
                'application/msword', // .doc
                'application/vnd.ms-excel', // .xls
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
                'text/csv',
                'text/plain', // .txt
                'application/csv', // CSV alternativo
            ];

            if (!in_array($mimeType, $allowedMimeTypes)) {
                // Si la extensión es válida pero el MIME type no, aún así rechazar
                $this->addError('uploadedFile', '❌ Tipo de archivo no válido. Solo se permiten: PDF, Word (.doc, .docx), Excel (.xls, .xlsx), CSV, TXT');
                $this->uploadedFile = null;
                $this->uploadedFileInfo = null;
                $this->dispatch('file-upload-error', message: 'Tipo de archivo no válido');
                return;
            }

            // ✅ Archivo válido: Guardar información
            $this->uploadedFileInfo = [
                'name' => $this->uploadedFile->getClientOriginalName(),
                'size' => $this->uploadedFile->getSize(),
                'type' => $mimeType,
                'path' => $this->uploadedFile->getRealPath(),
            ];

            Log::info('📄 Archivo subido exitosamente:', [
                'name' => $this->uploadedFileInfo['name'],
                'size' => $this->uploadedFileInfo['size'],
                'type' => $this->uploadedFileInfo['type'],
                'extension' => $extension,
                'size_mb' => round($this->uploadedFileInfo['size'] / 1024 / 1024, 2)
            ]);

            // Disparar evento de éxito para el frontend
            $this->dispatch('file-upload-success');

        } catch (\Exception $e) {
            Log::error('❌ Error procesando archivo:', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'extension' => $extension ?? 'unknown'
            ]);
            $this->addError('uploadedFile', '❌ Error al procesar el archivo. Por favor, intenta con otro archivo.');
            $this->uploadedFile = null;
            $this->uploadedFileInfo = null;
            $this->dispatch('file-upload-error', message: 'Error al procesar el archivo');
        }
    }

    /**
     * ✅ Limpia el archivo subido
     * 
     * Este método se llama después de enviar el mensaje al agente
     * para limpiar el estado y liberar memoria
     */
    public function clearUploadedFile()
    {
        if ($this->uploadedFileInfo) {
            Log::info('🧹 Limpiando archivo subido', [
                'name' => $this->uploadedFileInfo['name']
            ]);
        }
        
        $this->uploadedFile = null;
        $this->uploadedFileInfo = null;
    }

    /**
     * ✅ Normaliza la respuesta del agente a formato estructurado
     * 
     * Maneja 3 casos:
     * 1. Respuesta estructurada de OpenAI/Claude (array con 'text' e 'images')
     * 2. JSON string de Gemini que necesita parsearse
     * 3. Respuesta de texto plano (fallback con extracción de URLs)
     * 
     * @param mixed $response Respuesta del agente
     * @return array ['text' => string, 'images' => array|null]
     */
    private function normalizeAgentResponse($response): array
    {
        // ✅ Caso 1: Respuesta ya estructurada (OpenAI con responseSchema)
        if (is_array($response) && isset($response['text'])) {
            Log::info('✅ Respuesta estructurada detectada (OpenAI)', [
                'has_text' => !empty($response['text']),
                'has_images' => !empty($response['images']),
                'image_count' => !empty($response['images']) ? count($response['images']) : 0
            ]);
            
            return [
                'text' => $response['text'],
                'images' => !empty($response['images']) ? $response['images'] : null
            ];
        }
        
        // ✅ Caso 2: JSON string (Gemini siguiendo instrucciones)
        if (is_string($response)) {
            $trimmed = trim($response);
            
            // Intentar extraer JSON si está envuelto en markdown ```json ... ```
            if (preg_match('/```json\s*(\{.*?\})\s*```/s', $trimmed, $matches)) {
                $trimmed = $matches[1];
            }
            
            // Intentar extraer JSON del texto (puede estar mezclado con texto)
            // Buscar objeto JSON completo que contenga "text" e "images"
            // Usar un patrón más robusto que maneje JSON anidado
            if (preg_match('/\{[^{}]*(?:"text"|"images")[^{}]*\}/s', $trimmed, $jsonMatches)) {
                // Intentar parsear para verificar que es JSON válido
                $testJson = json_decode($jsonMatches[0], true);
                if (json_last_error() === JSON_ERROR_NONE && isset($testJson['text'])) {
                    $trimmed = $jsonMatches[0];
                }
            }
            
            // Intentar parsear JSON
            if (!empty($trimmed) && $trimmed[0] === '{') {
                $decoded = json_decode($trimmed, true);
                
                if (json_last_error() === JSON_ERROR_NONE && isset($decoded['text'])) {
                    Log::info('✅ JSON parseado exitosamente', [
                        'has_text' => !empty($decoded['text']),
                        'has_images' => !empty($decoded['images']),
                        'image_count' => !empty($decoded['images']) ? count($decoded['images']) : 0
                    ]);
                    
                    return [
                        'text' => $decoded['text'],
                        'images' => !empty($decoded['images']) ? $decoded['images'] : null
                    ];
                }
            }
        }
        
        // ✅ Caso 3: Fallback - texto plano (extraer imágenes de URLs en texto)
        $text = is_string($response) ? $response : (is_array($response) ? json_encode($response, JSON_UNESCAPED_UNICODE) : (string) $response);
        
        Log::info('⚠️ Usando fallback: extracción de imágenes del texto', [
            'text_preview' => substr($text, 0, 100)
        ]);
        
        // Extraer imágenes del contenido
        $images = $this->extractImagesFromContent($text);
        
        // Si no encontró imágenes en el contenido, buscar en mensajes recientes
        if (empty($images)) {
            $images = $this->extractImagesFromRecentToolMessages();
        }
        
        Log::info('📦 Respuesta normalizada (fallback)', [
            'text_length' => strlen($text),
            'has_images' => !empty($images),
            'image_count' => !empty($images) ? count($images) : 0
        ]);
        
        return [
            'text' => $text,
            'images' => $images
        ];
    }
    
    /**
     * ✅ Extrae URLs de imágenes del contenido de texto
     * 
     * Captura URLs tanto en texto plano como en markdown ![](url)
     * 
     * @param string $content Contenido de texto
     * @return array|null Array de URLs o null
     */
    private function extractImagesFromContent(string $content): ?array
    {
        // Patrón que captura URLs en texto plano o dentro de markdown ![](url)
        $pattern = '/(https:\/\/[^\s\)\]]+\.s3\.[^\s\)\]]+\.amazonaws\.com\/genesis\/(output-images|agent-generated-images)\/[^\s\)\]]+\.(?:png|jpg|jpeg|webp|gif))/i';
        preg_match_all($pattern, $content, $matches);
        
        if (!empty($matches[0])) {
            $imageUrls = array_unique($matches[0]);
            Log::info('🖼️ Imágenes extraídas del contenido de respuesta', [
                'count' => count($imageUrls),
                'urls' => $imageUrls,
                'content_preview' => substr($content, 0, 200)
            ]);
            return array_values($imageUrls);
        }
        
        Log::info('⚠️ No se encontraron URLs de imágenes en el contenido', [
            'content_preview' => substr($content, 0, 200)
        ]);
        return null;
    }

    /**
     * ✅ Extrae URLs de imágenes de los mensajes 'tool' y 'assistant' recientes guardados en attachments
     * 
     * Busca en attachments (donde DatabaseChatHistory guarda las imágenes automáticamente).
     * 
     * @return array|null Array de URLs de imágenes o null si no se encuentran
     */
    private function extractImagesFromRecentToolMessages(): ?array
    {
        try {
            if (!auth()->check()) {
                return null;
            }
            
            // Intentar obtener conversación actual
            $conversation = $this->getCurrentConversation();
            
            // Si no hay conversación actual, buscar la más reciente del usuario y agente
            if (!$conversation) {
                $conversation = ChatConversation::where('user_id', auth()->id())
                    ->where('agent_type', 'chat-agent') // ← Filtrar solo conversaciones de chat
                    ->where('status', 'active')
                    ->latest('updated_at')
                    ->first();
            }
            
            if (!$conversation) {
                Log::info('⚠️ No hay conversación actual');
                return null;
            }
            
            // Buscar SOLO el mensaje tool MÁS RECIENTE (últimos 30 segundos) con attachments
            // Esto evita que acumule imágenes de mensajes anteriores
            $latestToolMessage = ChatMessage::where('conversation_id', $conversation->id)
                ->where('role', 'tool')
                ->where('is_deleted', false)
                ->where('created_at', '>=', now()->subSeconds(30))
                ->whereNotNull('attachments')
                ->orderBy('created_at', 'desc')
                ->first();
            
            Log::info('🔍 Buscando imagen en el tool message MÁS RECIENTE', [
                'conversation_id' => $conversation->id,
                'message_found' => $latestToolMessage ? true : false,
                'message_id' => $latestToolMessage?->id
            ]);
            
            $imageUrls = [];
            
            if ($latestToolMessage && !empty($latestToolMessage->attachments['images'])) {
                $imageUrls = $latestToolMessage->attachments['images'];
            }
            
            if (!empty($imageUrls)) {
                Log::info('✅ Total de imágenes extraídas de mensajes recientes', [
                    'count' => count($imageUrls),
                    'urls' => $imageUrls
                ]);
                return array_values($imageUrls);
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('❌ Error extrayendo imágenes de mensajes:', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }


    // ============================================================================
    // MÉTODOS HELPER PARA MANEJO DE HISTORIAL DE IMÁGENES
    // ============================================================================

    /**
     * ✅ Carga el historial de imágenes desde la sesión
     */
    private function loadImageHistory(): void
    {
        $this->history = session('chat.image_history', []);
    }

    /**
     * ✅ Agrega una entrada al historial de imágenes
     * 
     * Sigue el mismo patrón que GeneradorMain::addToHistory()
     * 
     * @param string $type Tipo de entrada (ej: 'image/upload', 'image/generate')
     * @param array|null $images Array de imágenes con url, name, size
     * @param string|null $generationId ID único de la generación
     * @param string|null $prompt Prompt o descripción
     * @param string|null $model Modelo usado
     * @param string|null $ratio Ratio de la imagen
     * @param int|null $count Cantidad de imágenes
     * @param string|null $date Fecha (opcional, por defecto now())
     */
    public function addToHistory(
        string $type,
        ?array $images = null,
        ?string $generationId = null,
        ?string $prompt = null,
        ?string $model = null,
        ?string $ratio = null,
        ?int $count = null,
        ?string $date = null
    ): void
    {
        $entry = [
            'type' => $type,
            'date' => $date ?: now()->toIso8601String(),
        ];

        // Si es una generación múltiple de imágenes
        if ($images && $generationId) {
            $entry['images'] = $images;
            $entry['generationId'] = $generationId;
            $entry['prompt'] = $prompt;
            $entry['model'] = $model;
            $entry['ratio'] = $ratio;
            $entry['count'] = $count;
        }
        // Si es una imagen individual (compatibilidad hacia atrás)
        elseif (isset($images[0]['url'])) {
            $entry['url'] = $images[0]['url'];
        }

        // Agregar al historial
        $this->history[] = $entry;
        
        // Guardar en sesión
        session(['chat.image_history' => $this->history]);
        
        // ✅ FORZAR RE-RENDER: Re-leer desde sesión para asegurar reactividad
        $this->history = session('chat.image_history', []);
        
        // ✅ FORZAR RE-RENDER: Disparar refresh para actualizar DOM
        $this->dispatch('$refresh');
        
        Log::info('📸 Imagen agregada al historial del chat', [
            'type' => $type,
            'count' => $count,
            'generationId' => $generationId
        ]);
    }

    /**
     * ✅ Limpia el historial de imágenes
     */
    public function clearImageHistory(): void
    {
        $this->history = [];
        session()->forget('chat.image_history');
        
        Log::info('🧹 Historial de imágenes del chat limpiado');
    }

    // ============================================================================
    // MÉTODOS HELPER PARA INTEGRACIÓN CON BASE DE DATOS
    // ============================================================================

    /**
     * ✅ Obtener todas las conversaciones del usuario actual
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserConversations()
    {
        if (!auth()->check()) {
            return collect([]);
        }
        
        // ✅ Filtrar solo conversaciones de chat
        return ChatConversation::where('user_id', auth()->id())
            ->where('agent_type', 'chat-agent') // ← Filtrar solo conversaciones de chat
            ->where('status', 'active')
            ->orderBy('last_message_at', 'desc')
            ->with(['messages' => function($query) {
                $query->where('is_deleted', false)
                      ->orderBy('created_at', 'desc')
                      ->limit(1);
            }])
            ->get();
    }

    /**
     * ✅ Obtener conversación actual basada en el agente seleccionado
     * 
     * @return ChatConversation|null
     */
    public function getCurrentConversation(): ?ChatConversation
    {
        if (!auth()->check()) {
            return null;
        }
        
        $sessionKey = $this->getSessionKey();
        
        // ✅ Buscar conversaciones de chat (agent_type = 'chat-agent')
        return ChatConversation::where('user_id', auth()->id())
            ->where('agent_type', 'chat-agent') // ← Filtrar solo conversaciones de chat
            ->where('status', 'active')
            ->whereRaw("JSON_EXTRACT(context_metadata, '$.session_key') = ?", [$sessionKey])
            ->latest('updated_at')
            ->first();
    }

    /**
     * ✅ Obtener estadísticas de uso del usuario actual
     * 
     * @return array Estadísticas agregadas
     */
    public function getUserChatStats(): array
    {
        if (!auth()->check()) {
            return [
                'total_conversations' => 0,
                'total_messages' => 0,
                'total_tokens' => 0,
                'estimated_cost' => 0.0,
            ];
        }
        
        // ✅ Filtrar solo conversaciones de chat
        $conversations = ChatConversation::where('user_id', auth()->id())
            ->where('agent_type', 'chat-agent') // ← Filtrar solo conversaciones de chat
            ->where('status', 'active')
            ->get();
        
        $totalMessages = ChatMessage::whereIn('conversation_id', $conversations->pluck('id'))
            ->where('is_deleted', false)
            ->count();
        
        $totalTokens = ChatMessage::whereIn('conversation_id', $conversations->pluck('id'))
            ->where('is_deleted', false)
            ->sum('total_tokens') ?? 0;
        
        // Costo estimado (promedio de $5 por 1M tokens)
        $estimatedCost = ($totalTokens / 1_000_000) * 5.00;
        
        return [
            'total_conversations' => $conversations->count(),
            'total_messages' => $totalMessages,
            'total_tokens' => $totalTokens,
            'estimated_cost' => round($estimatedCost, 2),
        ];
    }

    /**
     * ✅ Cargar una conversación específica por ID
     * 
     * @param int $conversationId ID de la conversación
     */
    public function loadConversationById(int $conversationId): void
    {
        if (!auth()->check()) {
            return;
        }
        
        // ✅ Buscar conversación de chat
        $conversation = ChatConversation::where('id', $conversationId)
            ->where('user_id', auth()->id())
            ->where('agent_type', 'chat-agent') // ← Filtrar solo conversaciones de chat
            ->where('status', 'active')
            ->first();
        
        if (!$conversation) {
            Log::warning('❌ Conversación no encontrada o sin acceso', [
                'conversation_id' => $conversationId,
                'user_id' => auth()->id()
            ]);
            return;
        }
        
        // ✅ Detectar el agente real desde la conversación
        $realAgentKey = $this->detectAgentFromConversation($conversation);
        $this->selectedAgent = $realAgentKey;
        $this->selectedModel = $conversation->model_name;
        
        // ✅ Establecer el session_key de esta conversación
        $contextMetadata = $conversation->context_metadata ?? [];
        $this->currentSessionKey = $contextMetadata['session_key'] ?? $this->generateUniqueSessionKey($this->selectedAgent);
        
        Log::info('📂 Conversación cargada desde BD', [
            'conversation_id' => $conversationId,
            'agent' => $this->selectedAgent,
            'session_key' => $this->currentSessionKey
        ]);
        
        // Cargar solo mensajes visibles (excluir tools y system)
        $this->messages = [];
        $dbMessages = ChatMessage::where('conversation_id', $conversationId)
            ->where('is_deleted', false)
            ->where('is_visible', true) // ← Solo visibles
            ->orderBy('created_at', 'asc')
            ->get();
        
        foreach ($dbMessages as $dbMessage) {
            $this->messages[] = [
                'role' => $dbMessage->role,
                'content' => $dbMessage->content,
                'timestamp' => $dbMessage->created_at->format('H:i'),
                'images' => $dbMessage->attachments['images'] ?? null,
            ];
        }
        
        // Guardar en sesión
        $sessionKey = 'chat_messages_' . $this->selectedAgent;
        session()->put($sessionKey, $this->messages);
        
        Log::info('📂 Conversación cargada desde BD', [
            'conversation_id' => $conversationId,
            'message_count' => count($this->messages)
        ]);
    }

    /**
     * ✅ Archivar conversación actual
     */
    public function archiveCurrentConversation(): void
    {
        $conversation = $this->getCurrentConversation();
        
        if ($conversation) {
            $conversation->update(['status' => 'archived']);
            
            Log::info('📦 Conversación archivada', [
                'conversation_id' => $conversation->id
            ]);
            
            // Limpiar chat actual
            $this->clearChat();
        }
    }

    /**
     * ✅ Eliminar conversación por ID (eliminación física de la BD)
     * 
     * Elimina la conversación y todos sus mensajes asociados (cascade delete)
     * 
     * @param int $conversationId ID de la conversación
     */
    public function deleteConversationById(int $conversationId): void
    {
        if (!auth()->check()) {
            return;
        }
        
        $conversation = ChatConversation::where('id', $conversationId)
            ->where('user_id', auth()->id())
            ->first();
        
        if ($conversation) {
            // Guardar información para logs antes de eliminar
            $agentType = $conversation->agent_type;
            $messageCount = ChatMessage::where('conversation_id', $conversationId)->count();
            
            // ✅ ELIMINACIÓN FÍSICA: Eliminar la conversación (los mensajes se eliminan automáticamente por cascade)
            $conversation->delete();
            
            Log::info('🗑️ Conversación eliminada físicamente de la BD', [
                'conversation_id' => $conversationId,
                'agent_type' => $agentType,
                'messages_deleted' => $messageCount,
                'user_id' => auth()->id()
            ]);
            
            // Si es la conversación actual, limpiar y crear nueva
            if ($agentType === $this->selectedAgent) {
                // Generar nuevo session_key para nueva conversación
                $this->currentSessionKey = $this->generateUniqueSessionKey($this->selectedAgent);
                
                // Limpiar mensajes
                $this->messages = [];
                $this->messages[] = [
                    'role' => 'assistant',
                    'content' => '¡Hola! Soy ' . $this->availableAgents[$this->selectedAgent]['name'] . '. ¿En qué puedo ayudarte hoy?',
                    'timestamp' => now()->format('H:i'),
                ];
            }
            
            // Actualizar lista de sesiones
            $this->loadChatSessions();
        } else {
            Log::warning('⚠️ Conversación no encontrada o sin permisos para eliminar', [
                'conversation_id' => $conversationId,
                'user_id' => auth()->id()
            ]);
        }
    }
}
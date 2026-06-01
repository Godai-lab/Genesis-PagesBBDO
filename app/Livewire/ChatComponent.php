<?php

namespace App\Livewire;

use App\AiAgents\ChatOpenaiAgent;
use App\AiAgents\ChatClaudeAgent;
use App\AiAgents\ChatGeminiAgent;
use App\Http\Traits\ValidatesCreditLimit;
use App\Models\Account;
use App\Models\Generated;
use App\Supports\CostCalculationService;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ChatComponent
 * 
 * Componente Livewire para chat con agentes de IA (OpenAI, Claude, Gemini)
 * 
 * Funcionalidades principales:
 * - Chat en tiempo real con múltiples modelos LLM
 * - Historial persistente en caché (LarAgent)
 * - Soporte para documentos Genesis
 * - Carga de archivos externos (PDF, Word, Excel, CSV, TXT)
 * - Preparado para guardar en base de datos
 * 
 * @property array $messages Historial de mensajes del chat
 * @property string $newMessage Mensaje actual del usuario
 * @property bool $isLoading Estado de carga durante respuesta del agente
 * @property string|null $selectedModel Modelo LLM seleccionado
 * @property string|null $sessionKey Clave única de sesión para LarAgent
 */
class ChatComponent extends Component
{
    use WithFileUploads;
    use ValidatesCreditLimit;
    
    // ============================================================================
    // CONSTANTES
    // ============================================================================
    
    /** @var int Tamaño máximo de archivo en bytes (15MB) */
    private const MAX_FILE_SIZE = 15 * 1024 * 1024;
    
    /** @var array Extensiones de archivo permitidas (documentos) */
    private const ALLOWED_FILE_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt'];
    
    /** @var array Extensiones de imagen permitidas */
    private const ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    /** @var array MIME types permitidos para archivos (documentos) */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
        'application/msword', // .doc
        'application/vnd.ms-excel', // .xls
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
        'text/csv',
        'text/plain', // .txt
        'application/csv', // CSV alternativo
    ];
    
    /** @var array MIME types permitidos para imágenes */
    private const ALLOWED_IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];
    
    /** 
     * @deprecated Ya no se usa - El mensaje de bienvenida ahora está en la vista (chat-component.blade.php)
     * @var string Mensaje de bienvenida por defecto 
     */
    private const WELCOME_MESSAGE = '¿Por dónde empezamos?';
    
    // ============================================================================
    // PROPIEDADES PÚBLICAS (Livewire)
    // ============================================================================
    
    /** @var array Historial de mensajes del chat */
    public $messages = [];
    
    /** @var string Mensaje nuevo del usuario */
    public $newMessage = '';
    
    /** @var bool Estado de carga durante respuesta del agente */
    public $isLoading = false;
    
    /** @var string Proveedor de IA seleccionado (openai, claude) */
    public $selectedProvider = 'openai';
    
    /** @var string|null Modelo LLM seleccionado */
    public $selectedModel = null;

    /** @var string Esfuerzo de razonamiento para modelos que lo soportan (ej. GPT-5.2). Valores: none, low, medium, high */
    public $reasoningEffort = 'none';
    
    /** @var string|null Clave de sesión única para LarAgent (identifica la conversación actual) */
    public $sessionKey = null;

    /** @var array Lista de conversaciones del usuario para el sidebar (session_key, title, last_message_at) */
    public $conversations = [];
    
    // Propiedades para documentos Genesis
    public $documents = [];
    public $selectedDocument = null;
    public $documentInfo = null;
    public $showDocumentSelector = false;
    
    // Cuenta seleccionada para registrar uso y validar créditos
    public ?int $selectedAccountId = null;

    /** Cuentas disponibles para el usuario actual */
    public array $availableAccounts = [];

    /** Indica si el usuario es super admin */
    public bool $isSuperAdmin = false;

    // Propiedades para archivos externos
    public $uploadedFile = null;
    public $uploadedFileInfo = null;
    public $isUploadingFile = false;
    
    // ============================================================================
    // LIFECYCLE HOOKS
    // ============================================================================
    
    /**
     * Inicializa el componente al montarse
     * 
     * Este método se ejecuta una vez cuando el componente se carga:
     * 1. Configura el modelo por defecto
     * 2. Genera session key única para el usuario
     * 3. Carga historial de mensajes desde LarAgent
     * 4. Carga documentos Genesis disponibles
     * 
     * @return void
     */
    public function mount(): void
    {
        try {
            $this->isLoading = false;
            
            // Proveedor por defecto
            $this->selectedProvider = 'openai';
            
            // Configurar modelo por defecto si no hay uno seleccionado
            $this->selectedModel ??= $this->getDefaultModel();
            $models = $this->getAvailableModels();
            $this->reasoningEffort = $models[$this->selectedModel]['reasoning_effort_default'] ?? 'none';
            
            // Inicializar selección de cuenta
            $this->initializeAccountSelection();

            // Al entrar al chat (desde dashboard o cualquier ruta) mostrar siempre pantalla de bienvenida.
            // Session key nuevo (ULID) para no cargar ninguna conversación existente.
            $userId = auth()->id() ?? 'guest';
            $this->sessionKey = "chat_{$this->selectedProvider}_user_{$userId}_" . Str::ulid();
            $this->messages = [];

            Log::info('🚀 Chat iniciado', [
                'user_id' => $userId,
                'model' => $this->selectedModel,
                'session_key' => $this->sessionKey
            ]);

            // Cargar lista de conversaciones para el sidebar (desde laragent_messages)
            $this->loadConversations();
            
            // Cargar documentos Genesis disponibles
            $this->loadGenesisDocuments();
            $this->loadSelectedDocument();
            
        } catch (\Exception $e) {
            $this->handleError('inicializar ChatComponent', $e);
            $this->messages = $this->createErrorMessage('Hubo un problema al cargar el chat. Por favor, recarga la página.');
        }
    }
    
    /**
     * Renderiza la vista del componente
     * 
     * @return \Illuminate\View\View
     */
    public function render()
    {
        return view('livewire.chat-component');
    }

    /**
     * Obtiene modelos disponibles según el agente actual
     * 
     * @return array
     */
    public function getAvailableProviders(): array
    {
        return ['openai', 'claude', 'gemini'];
    }

    public function getAvailableModels(): array
    {
        $allModels = [
            'openai' => [
                'gpt-5.2-2025-12-11' => [
                    'name' => 'GPT-5.2',
                    'description' => 'Modelo con razonamiento configurable',
                    'supports_reasoning_effort' => true,
                    'reasoning_effort_options' => ['none'],
                    'reasoning_effort_default' => 'none',
                ],
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
                // Oculto: Claude Opus
                // 'claude-opus-4-1-20250805' => [
                //     'name' => 'Claude Opus 4.1',
                //     'description' => 'Modelo más potente y preciso'
                // ],
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
                'gemini-3.1-pro-preview' => [
                    'name' => 'Gemini 3.1 Pro',
                    'description' => 'Modelo avanzado (por defecto)',
                    'supports_reasoning_effort' => true,
                    'reasoning_effort_options' => ['none'],
                    'reasoning_effort_default' => 'none',
                ],
            ],
        ];
        
        // Retornar solo los modelos del proveedor actual
        return $allModels[$this->selectedProvider] ?? $allModels['openai'];
    }

    /**
     * Indica si el modelo actual soporta el parámetro reasoning_effort (ej. GPT-5.2).
     */
    public function currentModelSupportsReasoningEffort(): bool
    {
        $models = $this->getAvailableModels();
        $config = $models[$this->selectedModel] ?? [];
        return !empty($config['supports_reasoning_effort']);
    }

    /**
     * Opciones de esfuerzo de razonamiento para el modelo actual (ej. none, low, medium, high).
     */
    public function getReasoningEffortOptions(): array
    {
        $models = $this->getAvailableModels();
        $config = $models[$this->selectedModel] ?? [];
        return $config['reasoning_effort_options'] ?? [];
    }

    /**
     * Nombre del modelo actual para mostrar en el dropdown (sin usar @php en la vista).
     */
    public function getCurrentModelDisplayName(): string
    {
        $models = $this->getAvailableModels();
        $cur = $models[$this->selectedModel] ?? [];
        return $cur['name'] ?? $this->selectedModel ?? '';
    }

    /**
     * Etiqueta del nivel de razonamiento actual (Ninguno, Bajo, Medio, Alto).
     */
    public function getCurrentReasoningLabel(): string
    {
        if (!$this->currentModelSupportsReasoningEffort()) {
            return '';
        }
        return match ($this->reasoningEffort) {
            'low' => 'Bajo',
            'medium' => 'Medio',
            'high' => 'Alto',
            default => 'Ninguno',
        };
    }

    /**
     * Indica si un modelo tiene submenú de razonamiento (para no usar @php en la vista).
     */
    public function modelHasReasoningMenu(array $modelInfo): bool
    {
        $opts = $modelInfo['reasoning_effort_options'] ?? [];
        return !empty($modelInfo['supports_reasoning_effort']) && count($opts) > 0;
    }

    /**
     * Opciones de razonamiento para un modelo (array de strings: none, low, medium, high).
     */
    public function getReasoningOptionsForModel(array $modelInfo): array
    {
        return $modelInfo['reasoning_effort_options'] ?? [];
    }

    /**
     * Opciones de razonamiento para el modelo actualmente seleccionado.
     */
    public function getReasoningOptionsForCurrentModel(): array
    {
        $models = $this->getAvailableModels();
        $currentModel = $models[$this->selectedModel] ?? [];
        return $this->getReasoningOptionsForModel($currentModel);
    }

    /**
     * Etiqueta legible de una opción de razonamiento (none -> Ninguno (rápido), etc.).
     */
    public function getReasoningOptionLabel(string $opt): string
    {
        return match ($opt) {
            'none' => 'Ninguno (rápido)',
            'low' => 'Bajo',
            'medium' => 'Medio',
            'high' => 'Alto',
            default => $opt,
        };
    }

    /**
     * Indica si la opción de razonamiento está seleccionada para el modelo dado (para no usar @php en la vista).
     */
    public function isReasoningOptionSelected(string $modelId, string $opt): bool
    {
        return $this->selectedModel === $modelId && $this->reasoningEffort === $opt;
    }
    
    /**
     * Obtiene el modelo por defecto (primer modelo de la lista)
     * 
     * @return string
     */
    public function getDefaultModel(): string
    {
        // Modelos por defecto (alineados con la lista reducida: GPT-5.2, Claude Sonnet, Gemini 2.5 Pro)
        return match($this->selectedProvider) {
            'claude' => 'claude-sonnet-4-5-20250929',
            'gemini' => 'gemini-2.5-pro',
            default => 'gpt-5.2-2025-12-11', // OpenAI GPT-5.2
        };
    }
    
    
    // ============================================================================
    // MÉTODOS PÚBLICOS - CHAT
    // ============================================================================
    
    /**
     * Envía un mensaje del usuario al agente
     * 
     * Flujo:
     * 1. Valida que el mensaje no esté vacío
     * 2. Agrega el mensaje a la UI con metadata de archivos adjuntos
     * 3. Dispara evento para ejecutar la respuesta del agente (asíncrono)
     * 
     * @return void
     */
    public function sendMessage(): void
    {
        $trimmedMessage = trim($this->newMessage);
        
        if (empty($trimmedMessage)) {
            return;
        }

        $this->resetErrorBag('newMessage');

        if (!$this->selectedAccountId) {
            $this->addError(
                'newMessage',
                $this->isSuperAdmin
                    ? 'Debes seleccionar una cuenta antes de chatear.'
                    : 'No tienes una cuenta asignada para chatear.'
            );
            return;
        }

        try {
            $this->validateCreditLimit($this->selectedAccountId);
        } catch (\App\Exceptions\CreditLimitExceededException $e) {
            Log::warning('Límite de créditos excedido en Chat', [
                'message' => $e->getMessage(),
                'account_id' => $this->selectedAccountId,
            ]);
            $this->addError('newMessage', $e->getMessage());
            return;
        }

        Log::info('📨 Usuario envió mensaje', [
            'message_preview' => substr($trimmedMessage, 0, 50) . '...',
            'has_attachment' => $this->uploadedFile !== null
        ]);

        // Activar estado de carga
        $this->isLoading = true;
        
        // Preparar mensaje para la UI con metadata limpia
        $messageForUI = $this->buildUserMessageForUI($trimmedMessage);
        
        // Agregar mensaje del usuario al historial (solo UI)
        $this->messages[] = $messageForUI;

        // Limpiar input
        $this->newMessage = '';
        
        // Disparar evento para ejecutar agente (permite actualización de UI)
        $this->dispatch('executeAgentResponse', [
            'message' => $trimmedMessage
        ]);
    }

    /**
     * Ejecuta la respuesta del agente (evento asíncrono)
     * 
     * Este método se ejecuta después de actualizar la UI para que el usuario
     * vea su mensaje inmediatamente mientras el agente procesa.
     * 
     * Flujo:
     * 1. Crea instancia del agente con el session key
     * 2. Aplica el modelo seleccionado
     * 3. Agrega instrucciones de archivos adjuntos al mensaje (si hay)
     * 4. Envía mensaje al agente (LarAgent guarda automáticamente en caché)
     * 5. Normaliza la respuesta y la agrega a la UI
     * 
     * @param array $data Datos del evento con el mensaje del usuario
     * @return void
     */
    #[On('executeAgentResponse')]
    public function executeAgentResponse(array $data): void
    {
        $userMessage = $data['message'];

        // Detectar si hay una imagen adjunta
        $hasImage = $this->uploadedFileInfo && ($this->uploadedFileInfo['is_image'] ?? false);
        
        Log::info('🤖 Ejecutando agente', [
            'provider' => $this->selectedProvider,
            'model' => $this->selectedModel,
            'has_image' => $hasImage,
            'message_preview' => substr($userMessage, 0, 50) . '...'
        ]);

        try {
            // Crear instancia del agente con session key
            $agent = $this->createAgent();
            
            // Agregar instrucciones de archivo adjunto si existe (documentos)
            $messageForAgent = $this->buildMessageForAgent($userMessage);

            // Si hay una imagen adjunta, usar withImages() de LarAgent
            // Documentación: https://docs.laragent.ai/v1/responses/overview#images
            if ($hasImage && (isset($this->uploadedFileInfo['s3_url']) || isset($this->uploadedFileInfo['preview']))) {
                // Para Gemini nativo, usar base64 (el driver puede no soportar URLs externas)
                // Para OpenAI y Claude, usar URL de S3 (más eficiente)
                $imageData = $this->uploadedFileInfo['s3_url'];
                $imageSource = 'S3 URL';
                
                // Si es Gemini y tenemos base64, usarlo (mejor compatibilidad)
                if ($this->selectedProvider === 'gemini' && isset($this->uploadedFileInfo['preview'])) {
                    $imageData = $this->uploadedFileInfo['preview'];
                    $imageSource = 'Base64';
                }
                
                Log::info('🖼️ Enviando imagen al agente', [
                    'name' => $this->uploadedFileInfo['name'],
                    'type' => $this->uploadedFileInfo['type'],
                    'provider' => $this->selectedProvider,
                    'source' => $imageSource
                ]);
                
                $response = $agent
                    ->withImages([$imageData])
                    ->respond($messageForAgent);
            } else {
                // Sin imagen, enviar mensaje normal
                $response = $agent->respond($messageForAgent);
            }
            
            Log::info('✅ Respuesta recibida', [
                'type' => gettype($response),
                'preview' => is_string($response) ? substr($response, 0, 100) : json_encode($response)
            ]);

            // Registrar uso en usage_records (prompt_tokens → input, completion_tokens → output)
            $lastMessage = $agent->lastMessage();
            if ($lastMessage && method_exists($lastMessage, 'getUsage')) {
                $usage = $lastMessage->getUsage();
                if ($usage !== null && ($usage->promptTokens > 0 || $usage->completionTokens > 0)) {
                    $serviceType = match ($this->selectedProvider) {
                        'claude' => 'anthropic',
                        default => $this->selectedProvider,
                    };
                    CostCalculationService::trackChatUsage(
                        $this->selectedAccountId,
                        auth()->id(),
                        $this->selectedModel,
                        [
                            'tokens' => [
                                'input' => $usage->promptTokens,
                                'output' => $usage->completionTokens,
                            ],
                        ],
                        $this->sessionKey,
                        $serviceType
                    );
                }
            }
            
            // Normalizar respuesta a texto plano
            $responseText = $this->normalizeAgentResponse($response);
            
            // Agregar respuesta del asistente a la UI
            $this->messages[] = $this->buildAssistantMessage($responseText);

            Log::info('✅ Respuesta completada');
            
        } catch (\Exception $e) {
            // Determinar el mensaje de error según el tipo
            $errorMessage = 'Lo siento, hubo un problema al procesar tu mensaje. Por favor, intenta de nuevo.';
            
            // Si es un error de conexión con Claude
            if (str_contains($e->getMessage(), 'api.anthropic.com') || str_contains($e->getMessage(), 'cURL')) {
                $errorMessage = '❌ No se pudo conectar con Claude. Verifica tu API key de Anthropic en el archivo .env';
            }
            
            // Si es un error de Gemini
            if (str_contains($e->getMessage(), 'generativelanguage.googleapis.com') || str_contains($e->getMessage(), 'GEMINI')) {
                $errorMessage = '❌ No se pudo conectar con Gemini. Verifica tu API key de Google en el archivo .env';
            }
            
            // Error específico de Gemini con function_response en historial
            // Este error ocurre cuando hay tools en el historial y Gemini no puede procesarlas
            if (str_contains($e->getMessage(), 'function_response.name')) {
                $errorMessage = '⚠️ Gemini tuvo un problema con el historial. Si el error persiste, limpia el chat manualmente.';
                Log::warning('⚠️ Error de Gemini con function_response - el historial tiene tools que causan conflicto');
            }
            
            $this->handleError('procesar mensaje con el agente', $e);
            $this->messages[] = $this->createErrorMessage($errorMessage);
        } finally {
            $this->isLoading = false;
            
            // Actualizar lista del sidebar (nueva conversación o título actualizado)
            $this->loadConversations();
            
            // Limpiar archivos adjuntos después de enviar el mensaje
            $this->clearUploadedFile();
            $this->clearDocumentSelection();
        }
    }

    /**
     * Limpia el chat actual
     * 
     * Elimina el historial de LarAgent y resetea los mensajes a estado inicial
     * También limpia la selección de documentos
     * 
     * @return void
     */
    public function clearChat(): void
    {
        try {
            // Limpiar historial de LarAgent (usa el agente correcto según proveedor)
            $agent = $this->createAgent();
            $agent->clear();
            
            // Resetear mensajes (vacío para mostrar pantalla de bienvenida)
            $this->messages = [];
            
            $this->loadConversations();
            
            // Limpiar selección de documentos
            $this->selectedDocument = null;
            $this->documentInfo = null;
            
            // Limpiar archivo subido si hay uno
            $this->uploadedFile = null;
            $this->uploadedFileInfo = null;
            
            Log::info('✅ Chat y documentos limpiados');
        } catch (\Exception $e) {
            $this->handleError('limpiar chat', $e);
        }
    }
    
    /**
     * Cambia el modelo LLM seleccionado
     * 
     * @param string $modelId Identificador del modelo
     * @return void
     */
    public function switchModel(string $modelId): void
    {
        $availableModels = $this->getAvailableModels();
        
        if (isset($availableModels[$modelId])) {
            $this->selectedModel = $modelId;
            $modelConfig = $availableModels[$modelId] ?? [];
            $this->reasoningEffort = $modelConfig['reasoning_effort_default'] ?? 'none';
            
            Log::info('🔄 Modelo cambiado', ['model' => $modelId, 'reasoning_effort' => $this->reasoningEffort]);
        }
    }
    
    /**
     * Establece modelo y nivel de razonamiento a la vez (desde el dropdown de modelos).
     *
     * @param string $modelId
     * @param string $reasoningValue none|low|medium|high
     * @return void
     */
    public function setReasoningAndModel(string $modelId, string $reasoningValue): void
    {
        $models = $this->getAvailableModels();
        if (!isset($models[$modelId])) {
            return;
        }
        $config = $models[$modelId];
        $opts = $config['reasoning_effort_options'] ?? [];
        if (!in_array($reasoningValue, $opts, true)) {
            return;
        }
        $this->selectedModel = $modelId;
        $this->reasoningEffort = $reasoningValue;
    }
    
    /**
     * Cambia el proveedor de IA (OpenAI, Claude)
     * 
     * IMPORTANTE: Al cambiar de proveedor se limpia el chat completo
     * para evitar problemas de compatibilidad entre proveedores
     * 
     * @param string $provider Proveedor (openai, claude)
     * @return void
     */
    public function switchProvider(string $provider): void
    {
        if (!in_array($provider, ['openai', 'claude', 'gemini'])) {
            return;
        }
        
        Log::info('🔄 Cambiando proveedor', [
            'from' => $this->selectedProvider,
            'to' => $provider
        ]);
        
        
        if ($this->selectedProvider === $provider) {
            Log::info('⏭️ Proveedor ya es el mismo, ignorando');
            return;
        }

        // Cambiar proveedor
        $this->selectedProvider = $provider;
        $this->selectedModel = $this->getDefaultModel();
        $models = $this->getAvailableModels();
        $this->reasoningEffort = $models[$this->selectedModel]['reasoning_effort_default'] ?? 'none';

        $userId = auth()->id() ?? 'guest';

        // Siempre usar un session_key nuevo (ULID) al cambiar de proveedor:
        // - No cargamos ninguna conversación existente (evita "ir a una existente").
        // - La conversación solo aparece en el sidebar cuando el usuario envía el primer mensaje.
        $this->sessionKey = "chat_{$this->selectedProvider}_user_{$userId}_" . Str::ulid();
            
            $this->messages = [];
        $this->loadMessagesFromCache();
        $this->loadConversations();
        
        Log::info('✅ Proveedor cambiado', [
            'provider' => $provider,
            'model' => $this->selectedModel,
            'session_key' => $this->sessionKey,
        ]);
    }

    /**
     * Carga la lista de conversaciones del usuario desde laragent_messages (para el sidebar).
     */
    public function loadConversations(): void
    {
        $userId = auth()->id();
        if ($userId === null) {
            $this->conversations = [];
            return;
        }

        $pattern = '%_user_' . $userId . '%';
        $rows = DB::table('laragent_messages')
            ->where('session_key', 'like', $pattern)
            ->select('session_key')
            ->selectRaw('MAX(created_at) as last_message_at')
            ->groupBy('session_key')
            ->orderByDesc('last_message_at')
            ->limit(50)
            ->get();

        $this->conversations = $rows->map(function ($row) {
            $title = $this->getConversationTitle($row->session_key);
            $agentKey = $this->getAgentKeyFromSessionKey($row->session_key);
            $agentName = match ($agentKey) {
                'openai' => 'OpenAI',
                'claude' => 'Claude',
                'gemini' => 'Gemini',
                default => 'Chat',
            };
            return [
                'session_key' => $row->session_key,
                'title' => $title,
                'agent_key' => $agentKey,
                'agent_name' => $agentName,
                'last_message_at' => $row->last_message_at,
                'last_message_at_formatted' => \Carbon\Carbon::parse($row->last_message_at)->diffForHumans(),
                'last_message' => $title,
                'last_activity' => \Carbon\Carbon::parse($row->last_message_at)->diffForHumans(),
                'is_current' => $row->session_key === $this->sessionKey,
            ];
        })->toArray();
    }

    /**
     * Genera un título para la conversación (preview del primer mensaje de usuario).
     */
    private function getConversationTitle(string $sessionKey): string
    {
        $first = DB::table('laragent_messages')
            ->where('session_key', $sessionKey)
            ->where('role', 'user')
            ->orderBy('position')
            ->value('content');
        if ($first === null) {
            return 'Conversación nueva';
        }
        $text = $first;
        if (is_string($first) && (str_starts_with(trim($first), '[') || str_starts_with(trim($first), '{'))) {
            $decoded = json_decode($first, true);
            if (is_array($decoded)) {
                $text = $decoded['text'] ?? $decoded['content'] ?? (isset($decoded[0]['text']) ? $decoded[0]['text'] : 'Conversación');
            }
        }
        return Str::limit(is_string($text) ? $text : json_encode($text), 35) ?: 'Conversación';
    }

    /**
     * Extrae el proveedor (agent_key) del session_key.
     * Soporta:
     * - Formato LarAgent en BD: chatHistory_ChatOpenaiAgent_chat_openai_user_1
     * - Formato corto: chat_openai_user_1 o chat_gemini_user_1_01XXX
     */
    private function getAgentKeyFromSessionKey(string $sessionKey): string
    {
        // Formato LarAgent: chatHistory_Chat[Openai|Claude|Gemini]Agent_chat_...
        if (preg_match('/Chat(Openai|Claude|Gemini)Agent/', $sessionKey, $m)) {
            return strtolower($m[1]);
        }
        // Formato corto: chat_openai_user_1 o chat_gemini_user_1_ulid
        if (preg_match('/_chat_(openai|claude|gemini)_user_/', $sessionKey, $m)) {
            return $m[1];
        }
        if (preg_match('/^chat_(openai|claude|gemini)_/', $sessionKey, $m)) {
            return $m[1];
        }
        return 'openai';
    }

    /**
     * Devuelve la clave corta que hay que pasar a Agent::for() para que LarAgent
     * construya la clave de BD correcta (chatHistory_ChatXxxAgent_chat_...).
     * Si pasamos la clave completa de la BD, LarAgent generaría otra clave y no encontraría mensajes.
     */
    private function getShortSessionKeyForAgent(string $sessionKey): string
    {
        // Formato LarAgent en BD: chatHistory_ChatOpenaiAgent_chat_openai_user_1
        if (preg_match('/Chat(?:Openai|Claude|Gemini)Agent_(.+)$/', $sessionKey, $m)) {
            return $m[1];
        }
        return $sessionKey;
    }

    /**
     * Inicia una nueva conversación (nuevo session_key con ULID).
     */
    public function startNewChat(): void
    {
        $userId = auth()->id() ?? 'guest';
        $this->sessionKey = "chat_{$this->selectedProvider}_user_{$userId}_" . Str::ulid();
        $this->messages = [];
        $this->loadConversations();
    }

    /**
     * Selecciona una conversación del sidebar y carga sus mensajes.
     * Actualiza selectedProvider y selectedModel según el session_key para que
     * se use el agente correcto y se muestren los mensajes.
     */
    public function selectConversation(string $sessionKey): void
    {
        if ($sessionKey === $this->sessionKey) {
            return;
        }
        $this->sessionKey = $sessionKey;
        // Usar el proveedor de esta conversación para cargar el agente correcto y el header
        $this->selectedProvider = $this->getAgentKeyFromSessionKey($sessionKey);
        $this->selectedModel = $this->getDefaultModel();
        $this->loadMessagesFromCache();
        $this->loadConversations();
    }

    /**
     * Elimina una conversación (mensajes en laragent_messages) y actualiza el sidebar.
     * Si se elimina la conversación actual, se inicia una nueva y se limpian los mensajes en pantalla.
     */
    public function deleteConversation(string $sessionKey): void
    {
        DB::table('laragent_messages')->where('session_key', $sessionKey)->delete();

        // Comparar con clave normalizada: el sidebar pasa la clave de BD (larga), $this->sessionKey puede ser corta
        $deletedShort = $this->getShortSessionKeyForAgent($sessionKey);
        $currentShort = $this->getShortSessionKeyForAgent($this->sessionKey);
        if ($deletedShort === $currentShort) {
            $this->startNewChat();
                return;
            }
        $this->loadConversations();
    }

    // ============================================================================
    // MÉTODOS PÚBLICOS - DOCUMENTOS GENESIS
    // ============================================================================
    
    /**
     * Carga documentos Genesis según permisos del usuario
     * 
     * Filtra documentos por:
     * - Status: completed
     * - Type: Genesis
     * - Permisos del usuario (fullAccess o accounts específicos)
     * 
     * @return void
     */
    public function loadGenesisDocuments(): void
    {
        $user = auth()->user();
        
        if (!$user) {
            $this->documents = [];
            return;
        }
        
        // Query base para documentos Genesis
        $query = Generated::select('id', 'name', 'key', 'account_id', 'created_at')
                          ->where('status', 'completed')
                          ->where('key', 'Genesis')
                          ->orderBy('created_at', 'desc')
                          ->limit(50);
        
        // Aplicar filtro de permisos
        if (!$user->haveFullAccess()) {
            $accountIds = $user->accounts->pluck('id')->toArray();
            $query->whereIn('account_id', $accountIds);
        }
        
        // Transformar para la vista
        $this->documents = $query->get()->map(function($doc) {
            return [
                'id' => $doc->id,
                'name' => $doc->name,
                'account' => $doc->account?->name ?? 'Sin cuenta',
                'date' => $doc->created_at->format('d/m/Y H:i'),
            ];
        })->toArray();
    }

    /**
     * Selecciona un documento Genesis para el contexto del chat
     * 
     * @param int $documentId ID del documento
     * @return void
     */
    public function selectDocument(int $documentId): void
    {
        $document = Generated::find($documentId);
        
        if (!$document || !$this->userCanAccessDocument($document)) {
            $this->clearDocumentSelection();
                return;
            }
            
                $this->selectedDocument = $documentId;
                $this->documentInfo = [
                    'id' => $document->id,
                    'name' => $document->name,
            'type' => 'Genesis',
            'account' => $document->account?->name ?? 'Sin cuenta'
        ];
        
        // Guardar en sesión para persistencia
        session()->put('chat_document', $documentId);
    }

    /**
     * Remueve el documento seleccionado
     * 
     * @return void
     */
    public function removeSelectedDocument(): void
    {
        $this->clearDocumentSelection();
    }

    /**
     * Toggle del modal selector de documentos
     * 
     * @return void
     */
    public function toggleDocumentSelector(): void
    {
        $this->showDocumentSelector = !$this->showDocumentSelector;
    }

    /**
     * Confirma la selección y cierra el modal
     * 
     * @return void
     */
    public function confirmDocumentSelection(): void
    {
        $this->showDocumentSelector = false;
    }

    /**
     * Carga el documento seleccionado desde la sesión al montar
     * 
     * @return void
     */
    public function loadSelectedDocument(): void
    {
        $documentId = session()->get('chat_document');
        
        if (!$documentId) {
                    return;
                }
                
        $document = Generated::find($documentId);
        
        if (!$document || !$this->userCanAccessDocument($document)) {
            $this->clearDocumentSelection();
            return;
        }
        
                    $this->selectedDocument = $documentId;
                    $this->documentInfo = [
                        'id' => $document->id,
                        'name' => $document->name,
            'type' => 'Genesis',
            'account' => $document->account?->name ?? 'Sin cuenta'
        ];
    }

    // ============================================================================
    // MÉTODOS PÚBLICOS - ARCHIVOS EXTERNOS
    // ============================================================================

    /**
     * Hook de Livewire: Se ejecuta cuando se sube un archivo
     * 
     * Valida:
     * 1. Extensión del archivo
     * 2. Tamaño (máx 15MB)
     * 3. MIME type para seguridad
     * 
     * @return void
     */
    public function updatedUploadedFile(): void
    {
        $this->resetErrorBag('uploadedFile');
        
        if (!$this->uploadedFile) {
            $this->uploadedFileInfo = null;
            $this->isUploadingFile = false;
            return;
        }

        // Activar estado de carga
        $this->isUploadingFile = true;

        try {
            // Validar extensión
            $extension = strtolower($this->uploadedFile->getClientOriginalExtension());
            $mimeType = $this->uploadedFile->getMimeType();
            
            // Determinar si es imagen o documento
            $isImage = in_array($extension, self::ALLOWED_IMAGE_EXTENSIONS) && 
                       in_array($mimeType, self::ALLOWED_IMAGE_MIME_TYPES);
            $isDocument = in_array($extension, self::ALLOWED_FILE_EXTENSIONS) && 
                          in_array($mimeType, self::ALLOWED_MIME_TYPES);
            
            if (!$isImage && !$isDocument) {
                $this->isUploadingFile = false;
                $this->handleFileUploadError('Formato no permitido. Permitidos: Imágenes (JPG, PNG, GIF, WebP) o Documentos (PDF, Word, Excel, CSV, TXT)');
                return;
            }

            // Validar tamaño
            if ($this->uploadedFile->getSize() > self::MAX_FILE_SIZE) {
                $this->isUploadingFile = false;
                $this->handleFileUploadError('Archivo demasiado grande. Máximo: 15MB');
                return;
            }

            // Guardar información del archivo
            $this->uploadedFileInfo = [
                'name' => $this->uploadedFile->getClientOriginalName(),
                'size' => $this->uploadedFile->getSize(),
                'type' => $mimeType,
                'path' => $this->uploadedFile->getRealPath(),
                'is_image' => $isImage,
                'extension' => $extension,
            ];
            
            // Si es imagen, subir a S3 y guardar URL
            if ($isImage) {
                $imageContent = file_get_contents($this->uploadedFile->getRealPath());
                
                // Subir a S3 y obtener URL
                $s3Url = $this->subirImagenChatAS3($imageContent, $extension);
                
                if ($s3Url) {
                    $this->uploadedFileInfo['s3_url'] = $s3Url;
                    // También guardamos base64 para preview local (más rápido que cargar de S3)
                    $this->uploadedFileInfo['preview'] = 'data:' . $mimeType . ';base64,' . base64_encode($imageContent);
                    
                    Log::info('🖼️ Imagen subida a S3', [
                        'name' => $this->uploadedFileInfo['name'],
                        's3_url' => $s3Url
                    ]);
                } else {
                    $this->isUploadingFile = false;
                    $this->handleFileUploadError('Error al subir imagen a S3');
                    return;
                }
            }

            Log::info($isImage ? '🖼️ Imagen procesada' : '📄 Archivo subido', [
                'name' => $this->uploadedFileInfo['name'],
                'size_mb' => round($this->uploadedFileInfo['size'] / 1024 / 1024, 2),
                'is_image' => $isImage,
                's3_url' => $this->uploadedFileInfo['s3_url'] ?? null
            ]);

            $this->dispatch('file-upload-success');

        } catch (\Exception $e) {
            $this->handleError('procesar archivo', $e);
            $this->handleFileUploadError('Error al procesar el archivo');
        } finally {
            // Desactivar estado de carga
            $this->isUploadingFile = false;
        }
    }
    
    /**
     * Sube una imagen del chat a S3
     * 
     * Almacena las imágenes en: genesis/chat-input/
     * 
     * @param string $imageContent Contenido binario de la imagen
     * @param string $extension Extensión del archivo (jpg, png, etc)
     * @return string|null URL de S3 o null si falla
     */
    private function subirImagenChatAS3(string $imageContent, string $extension): ?string
    {
        try {
            // Generar nombre de archivo único
            $userId = auth()->check() ? auth()->id() : 'guest';
            $fileName = 'genesis/chat-input/' . now()->format('Ymd_His') . '_' . uniqid("user{$userId}_") . '.' . $extension;
            
            Log::info('📤 Subiendo imagen de chat a S3', [
                'fileName' => $fileName,
                'imageSize' => strlen($imageContent)
            ]);
            
            // Subir a S3
            Storage::disk('s3')->put($fileName, $imageContent);
            
            // Obtener la URL de S3
            $url = Storage::disk('s3')->url($fileName);
            
            Log::info('✅ Imagen de chat subida exitosamente a S3', [
                'url' => $url
            ]);
            
            return $url;
            
        } catch (\Exception $e) {
            Log::error('❌ Error subiendo imagen de chat a S3: ' . $e->getMessage(), [
                'error' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Limpia el archivo subido después de enviarlo al agente
     * 
     * @return void
     */
    public function clearUploadedFile(): void
    {
        if ($this->uploadedFileInfo) {
            Log::info('🧹 Limpiando archivo', ['name' => $this->uploadedFileInfo['name']]);
        }
        
        $this->uploadedFile = null;
        $this->uploadedFileInfo = null;
    }
    
    // ============================================================================
    // MÉTODOS PRIVADOS - AGENTES
    // ============================================================================

    /**
     * Crea una instancia del agente configurada con el modelo seleccionado
     * 
     * Este método es el punto central para crear agentes.
     * Cuando agregues Claude o Gemini, modificar aquí.
     * 
     * @return ChatOpenaiAgent
     */
    private function createAgent()
    {
        $keyForAgent = $this->getShortSessionKeyForAgent($this->sessionKey);
        Log::info('🔨 Creando agente', [
            'provider' => $this->selectedProvider,
            'model' => $this->selectedModel,
            'session_key' => $this->sessionKey,
            'key_for_agent' => $keyForAgent,
        ]);

        // LarAgent construye la clave de BD como chatHistory_ChatXxxAgent_[keyForAgent].
        // Hay que pasar la clave corta (ej. chat_gemini_user_1) para que coincida con lo guardado.
        $agent = match($this->selectedProvider) {
            'claude' => ChatClaudeAgent::for($keyForAgent),
            'gemini' => ChatGeminiAgent::for($keyForAgent),
            default => ChatOpenaiAgent::for($keyForAgent), // OpenAI
        };
        
        // Aplicar modelo personalizado (IMPORTANTE: esto sobrescribe el modelo del agente)
        if ($this->selectedModel) {
            $agent = $agent->withModel($this->selectedModel);
            Log::info('✅ Modelo aplicado al agente', ['model' => $this->selectedModel]);
        }

        // Esfuerzo de razonamiento solo para modelos que lo soportan (ej. GPT-5.2)
        // GPT-5.2 solo acepta temperature = 1; sobrescribimos en ejecución para evitar error de API
        $models = $this->getAvailableModels();
        $modelConfig = $models[$this->selectedModel] ?? [];
        if (!empty($modelConfig['supports_reasoning_effort']) && $this->reasoningEffort !== '') {
            $agent = $agent
                ->temperature(1)
                ->withConfigs(['reasoning_effort' => $this->reasoningEffort]);
        }
        
        return $agent;
    }
    

    // ============================================================================
    // MÉTODOS PRIVADOS - MENSAJES
    // ============================================================================

    /**
     * Construye un mensaje de usuario para mostrar en la UI
     * 
     * Incluye metadata de:
     * - Imágenes adjuntas (JPG, PNG, GIF, WebP)
     * - Archivos externos adjuntos (PDFs, Word, etc.)
     * - Documentos Genesis seleccionados
     * 
     * @param string $content Contenido del mensaje
     * @return array
     */
    private function buildUserMessageForUI(string $content): array
    {
        $message = [
            'role' => 'user',
            'content' => $content,
            'timestamp' => now()->format('H:i'),
        ];
        
        $attachments = [];
        
        // 1. Agregar metadata de archivo externo o imagen si existe
        if ($this->uploadedFile && $this->uploadedFileInfo) {
            $isImage = $this->uploadedFileInfo['is_image'] ?? false;
            
            $attachment = [
                'type' => $isImage ? 'image' : 'external_file',
                'name' => $this->uploadedFileInfo['name'],
                'file_type' => $this->uploadedFileInfo['type'],
            ];
            
            // Si es imagen, incluir URL de S3 para persistencia
            if ($isImage && isset($this->uploadedFileInfo['s3_url'])) {
                // Guardamos la URL de S3 (persiste al recargar)
                $attachment['preview'] = $this->uploadedFileInfo['s3_url'];
                $attachment['s3_url'] = $this->uploadedFileInfo['s3_url'];
            } else {
                $attachment['path'] = $this->uploadedFileInfo['path'];
            }
            
            $attachments[] = $attachment;
        }
        
        // 2. Agregar metadata de documento Genesis si existe
        if ($this->selectedDocument && $this->documentInfo) {
            $attachments[] = [
                'type' => 'genesis',
                'id' => $this->documentInfo['id'],
                'name' => $this->documentInfo['name'],
                'account' => $this->documentInfo['account'],
            ];
        }
        
        // Agregar attachments al mensaje si hay alguno
        if (!empty($attachments)) {
            $message['attachments'] = $attachments;
        }
        
        return $message;
    }
    
    /**
     * Construye el mensaje completo para enviar al agente
     * 
     * Agrega instrucciones para:
     * - Archivos externos adjuntos (PDFs, Word, etc.)
     * - Documentos Genesis seleccionados
     * 
     * @param string $userMessage Mensaje del usuario
     * @return string Mensaje con instrucciones adicionales
     */
    private function buildMessageForAgent(string $userMessage): string
    {
        $instructions = [];
        
        // 1. Instrucciones para documento Genesis (si hay uno seleccionado)
        if ($this->selectedDocument && $this->documentInfo) {
            $instructions[] = sprintf(
                "[El usuario ha seleccionado un documento Genesis: %s (ID: %s). Por favor, usa la herramienta get_document_context con document_id=\"%s\" para leer su contenido.]",
                $this->documentInfo['name'],
                $this->documentInfo['id'],
                $this->documentInfo['id']
            );
            
            Log::info('📄 Documento Genesis agregado al contexto', [
                'name' => $this->documentInfo['name'],
                'id' => $this->documentInfo['id']
            ]);
        }
        
        // 2. Instrucciones para archivo externo (si hay uno subido)
        // NOTA: NO agregar instrucciones para IMÁGENES - estas se envían con withImages()
        if ($this->uploadedFile && $this->uploadedFileInfo) {
            $isImage = $this->uploadedFileInfo['is_image'] ?? false;
            
            // Solo agregar instrucciones de tool para documentos (no imágenes)
            if (!$isImage) {
                $instructions[] = sprintf(
                    "[El usuario ha subido un archivo externo: %s (%s). Por favor, usa la herramienta read_external_file con file_path=\"%s\" y file_type=\"%s\" para leer su contenido.]",
                    $this->uploadedFileInfo['name'],
                    $this->uploadedFileInfo['type'],
                    $this->uploadedFileInfo['path'],
                    $this->uploadedFileInfo['type']
                );
                
                Log::info('📎 Documento externo agregado al contexto', [
                    'name' => $this->uploadedFileInfo['name']
                ]);
            } else {
                Log::info('🖼️ Imagen detectada - se enviará con withImages()', [
                    'name' => $this->uploadedFileInfo['name']
                ]);
            }
        }
        
        // Combinar mensaje con instrucciones si hay alguna
        if (!empty($instructions)) {
            return $userMessage . "\n\n" . implode("\n\n", $instructions);
        }
        
        return $userMessage;
    }
    
    /**
     * Construye un mensaje del asistente para la UI
     * 
     * @param string $content Contenido de la respuesta
     * @return array
     */
    private function buildAssistantMessage(string $content): array
    {
                    return [
            'role' => 'assistant',
            'content' => $content,
            'timestamp' => now()->format('H:i'),
        ];
    }
    
    /**
     * Crea un mensaje de bienvenida personalizado
     * 
     * @deprecated Ya no se usa - La pantalla de bienvenida ahora se muestra en la vista cuando $messages está vacío
     * @return array
     */
    private function createWelcomeMessage(): array
    {
        // NOTA: Este método ya no se usa pero se mantiene por compatibilidad
        // La pantalla de bienvenida ahora está en chat-component.blade.php
        
        // Obtener nombre del usuario si está autenticado
        $userName = '';
        if (auth()->check() && auth()->user()->name) {
            $userName = ', ' . auth()->user()->name;
        }
        
        // Reemplazar {name} con el nombre del usuario o vacío
        $message = str_replace('{name}', $userName, self::WELCOME_MESSAGE);
        
        return [
            'role' => 'assistant',
            'content' => $message,
            'timestamp' => now()->format('H:i'),
        ];
    }
    
    /**
     * Crea un mensaje de error
     * 
     * @param string $message Texto del error
     * @return array
     */
    private function createErrorMessage(string $message): array
    {
        return [
            'role' => 'assistant',
            'content' => "⚠️ {$message}",
            'timestamp' => now()->format('H:i'),
        ];
    }
    
    /**
     * Normaliza la respuesta del agente a texto plano
     * 
     * Compatible con múltiples formatos:
     * - Texto plano (string)
     * - OpenAI structured content: [{"type": "text", "text": "..."}]
     * - Legacy structured output: {"text": "...", "images": [...]}
     * 
     * @param mixed $response Respuesta del agente
     * @return string Texto plano
     */
    private function normalizeAgentResponse($response): string
    {
        // Caso 1: String directo
        if (is_string($response)) {
            return trim($response);
        }
        
        // Caso 2: Array
        if (is_array($response)) {
            // OpenAI structured content: [{"type": "text", "text": "..."}]
            if (isset($response[0]['type'], $response[0]['text'])) {
                return trim($response[0]['text']);
            }
            
            // Legacy structured output: {"text": "...", "images": [...]}
            if (isset($response['text'])) {
                return trim($response['text']);
            }
            
            // Array simple de strings
            if (isset($response[0]) && is_string($response[0])) {
                return trim($response[0]);
            }
            
            // Fallback: JSON pretty print
            return json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        
        // Fallback final: cast a string
        return (string) $response;
    }

    // ============================================================================
    // MÉTODOS PRIVADOS - HISTORIAL Y CACHÉ
    // ============================================================================

    /**
     * Carga mensajes del historial guardado por LarAgent
     * 
     * Extrae automáticamente metadata de archivos adjuntos de mensajes viejos
     * 
     * @return void
     */
    private function loadMessagesFromCache(): void
    {
        try {
            $agent = $this->createAgent();
            $chatHistory = $agent->chatHistory();
            // getMessages() devuelve MessageArray (objetos); toArray() da arrays para role/content
            $historyMessages = $chatHistory->getMessages()->toArray();
            
            if (empty($historyMessages)) {
                $this->messages = []; // Dejar vacío para mostrar estado de bienvenida en la vista
                Log::info('📝 Sin historial previo');
                return;
            }
            
            // Convertir mensajes de LarAgent a formato UI
            $this->messages = [];
            
            foreach ($historyMessages as $msg) {
                // Asegurar array (por si viene como objeto con toArray)
                if (is_object($msg) && method_exists($msg, 'toArray')) {
                    $msg = $msg->toArray();
                }
                if (!is_array($msg) || !isset($msg['role']) || !isset($msg['content'])) {
                    Log::warning('📝 Mensaje con formato inválido', [
                        'message_type' => gettype($msg),
                        'message_keys' => is_array($msg) ? array_keys($msg) : 'N/A'
                    ]);
                    continue;
                }
                
                // Solo mostrar mensajes de user y assistant (ignorar system, tool, etc.)
                if (!in_array($msg['role'], ['user', 'assistant'])) {
                    Log::info('📝 Ignorando mensaje con role', ['role' => $msg['role']]);
                    continue;
                }
                
                $content = $this->normalizeAgentResponse($msg['content']);
                
                // Si el contenido normalizado está vacío, ignorar este mensaje
                if (empty(trim($content))) {
                    Log::info('📝 Ignorando mensaje vacío', [
                        'role' => $msg['role'],
                        'original_content_type' => gettype($msg['content'])
                    ]);
                    continue;
                }
                
                $message = [
                    'role' => $msg['role'],
                    'content' => $content,
                    'timestamp' => now()->format('H:i'),
                ];
                
                // Extraer metadata de documentos adjuntos si es mensaje de usuario
                if ($msg['role'] === 'user' && is_string($content)) {
                    $extracted = $this->extractAttachmentFromMessage($content);
                    
                    if ($extracted) {
                        $message['content'] = $extracted['clean_message'];
                        $message['attachments'] = $extracted['attachments'];
                    }
                }
                
                // Verificar nuevamente que el mensaje final tenga contenido
                if (!empty(trim($message['content']))) {
                    $this->messages[] = $message;
                } else {
                    Log::info('📝 Mensaje quedó vacío después de extraer attachments', [
                        'role' => $msg['role'],
                        'attachments' => $message['attachments'] ?? []
                    ]);
                }
            }
            
            // Si después de filtrar no quedan mensajes, dejar vacío
            if (empty($this->messages)) {
                Log::info('✅ Historial filtrado quedó vacío, mostrando pantalla de bienvenida');
            } else {
                Log::info('✅ Historial cargado', [
                    'message_count' => count($this->messages)
                ]);
            }
            
        } catch (\Exception $e) {
            $this->handleError('cargar historial', $e);
            $this->messages = []; // Dejar vacío en caso de error
        }
    }
    
    /**
     * Extrae información de documentos adjuntos de un mensaje con instrucciones
     * 
     * Detecta los patrones de instrucciones agregados por buildMessageForAgent():
     * - Documentos Genesis: [El usuario ha seleccionado un documento Genesis...]
     * - Archivos externos (nuevo): [El usuario ha subido un archivo externo...]
     * - Archivos externos (viejo): [El usuario ha subido un archivo: ...] (sin "externo")
     * 
     * @param string $content Contenido del mensaje
     * @return array|null ['clean_message' => string, 'attachments' => array] o null
     */
    private function extractAttachmentFromMessage(string $content): ?array
    {
        $attachments = [];
        $cleanMessage = $content;
        
        // 1. Detectar documento Genesis
        $genesisPattern = '/\[El usuario ha seleccionado un documento Genesis: (.+?) \(ID: (\d+)\)\. Por favor, usa la herramienta get_document_context con document_id="(\d+)" para leer su contenido\.\]/s';
        
        if (preg_match($genesisPattern, $content, $matches)) {
            $attachments[] = [
                'type' => 'genesis',
                'name' => $matches[1],
                'id' => $matches[2],
            ];
            
            // Limpiar mensaje quitando estas instrucciones
            $cleanMessage = trim(preg_replace($genesisPattern, '', $cleanMessage));
        }
        
        // 2. Detectar archivo externo (formato NUEVO con palabra "externo")
        $filePatternNew = '/\[El usuario ha subido un archivo externo: (.+?) \((.+?)\)\. Por favor, usa la herramienta read_external_file con file_path="(.+?)" y file_type="(.+?)" para leer su contenido\.\]/s';
        
        if (preg_match($filePatternNew, $content, $matches)) {
            $attachments[] = [
                'type' => 'external_file',
                'name' => $matches[1],
                'file_type' => $matches[2],
                'path' => $matches[3],
            ];
            
            // Limpiar mensaje quitando estas instrucciones
            $cleanMessage = trim(preg_replace($filePatternNew, '', $cleanMessage));
        }
        
        // 3. Detectar archivo externo (formato VIEJO sin palabra "externo" - para retrocompatibilidad)
        $filePatternOld = '/\[El usuario ha subido un archivo: (.+?) \((.+?)\)\. Por favor, usa la herramienta read_external_file con file_path="(.+?)" y file_type="(.+?)" para leer su contenido\.\]/s';
        
        if (preg_match($filePatternOld, $content, $matches)) {
            $attachments[] = [
                'type' => 'external_file',
                'name' => $matches[1],
                'file_type' => $matches[2],
                'path' => $matches[3],
            ];
            
            // Limpiar mensaje quitando estas instrucciones
            $cleanMessage = trim(preg_replace($filePatternOld, '', $cleanMessage));
        }
        
        // Si no se encontraron attachments, retornar null
        if (empty($attachments)) {
            return null;
        }
        
        // Limpiar el mensaje de espacios en blanco
        $cleanMessage = trim($cleanMessage);
        
        // Si el mensaje quedó vacío o solo tiene whitespace, usar texto por defecto
        if (empty($cleanMessage)) {
            $cleanMessage = count($attachments) > 1 
                ? '📎 Documentos adjuntos' 
                : '📎 Documento adjunto';
        }
        
        return [
            'clean_message' => $cleanMessage,
            'attachments' => $attachments,
        ];
    }
    
    // ============================================================================
    // MÉTODOS PRIVADOS - HELPERS
    // ============================================================================
    
    /**
     * Verifica si el usuario puede acceder a un documento
     * 
     * @param Generated $document
     * @return bool
     */
    private function userCanAccessDocument(Generated $document): bool
    {
        $user = auth()->user();
        
        if (!$user) {
            return false;
        }
        
        if ($user->haveFullAccess()) {
            return true;
        }
        
        return $user->accounts->pluck('id')->contains($document->account_id);
    }
    
    /**
     * Limpia la selección de documento actual
     * 
     * @return void
     */
    private function clearDocumentSelection(): void
    {
        if ($this->selectedDocument) {
            Log::info('🧹 Limpiando documento Genesis seleccionado', ['id' => $this->selectedDocument]);
        }
        
        $this->selectedDocument = null;
        $this->documentInfo = null;
        session()->forget('chat_document');
    }
    
    /**
     * Maneja errores de forma consistente
     * 
     * @param string $context Contexto donde ocurrió el error
     * @param \Exception $e Excepción capturada
     * @return void
     */
    // ============================================================================
    // CUENTA
    // ============================================================================

    /**
     * Inicializa la selección de cuenta según el tipo de usuario.
     * Super admin ve todas las cuentas; usuario normal solo las suyas.
     */
    private function initializeAccountSelection(): void
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $this->isSuperAdmin = $user->haveFullAccess();

        if ($this->isSuperAdmin) {
            $this->availableAccounts = Account::select('id', 'name')
                ->where('status', 1)
                ->orderBy('name')
                ->get()
                ->map(fn($a) => ['id' => $a->id, 'name' => $a->name])
                ->toArray();

            $sessionId = session('chat.selectedAccountId');
            if ($sessionId && collect($this->availableAccounts)->pluck('id')->contains($sessionId)) {
                $this->selectedAccountId = $sessionId;
            }
        } else {
            $this->availableAccounts = $user->accounts()
                ->select('id', 'name')
                ->where('status', 1)
                ->orderBy('name')
                ->get()
                ->map(fn($a) => ['id' => $a->id, 'name' => $a->name])
                ->toArray();

            if (!empty($this->availableAccounts)) {
                $sessionId = session('chat.selectedAccountId');
                if ($sessionId && collect($this->availableAccounts)->pluck('id')->contains($sessionId)) {
                    $this->selectedAccountId = $sessionId;
                } else {
                    $this->selectedAccountId = $this->availableAccounts[0]['id'];
                }
            }
        }

        if ($this->selectedAccountId) {
            session(['chat.selectedAccountId' => $this->selectedAccountId]);
        }
    }

    /**
     * Hook de Livewire: se ejecuta cuando el usuario cambia la cuenta en el selector.
     */
    public function updatedSelectedAccountId($value): void
    {
        if ($value) {
            $hasAccess = $this->isSuperAdmin ||
                collect($this->availableAccounts)->pluck('id')->contains((int) $value);

            if (!$hasAccess) {
                $this->selectedAccountId = session('chat.selectedAccountId');
                return;
            }
        }

        session(['chat.selectedAccountId' => $value]);
        $this->resetErrorBag('newMessage');
    }

    private function handleError(string $context, \Exception $e): void
    {
        Log::error("❌ Error al {$context}", [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
    }
    
    /**
     * Maneja errores de carga de archivos
     * 
     * @param string $message Mensaje de error
     * @return void
     */
    private function handleFileUploadError(string $message): void
    {
        $this->addError('uploadedFile', "❌ {$message}");
        $this->uploadedFile = null;
        $this->uploadedFileInfo = null;
        $this->dispatch('file-upload-error', message: $message);
    }
}

<?php

namespace App\AiAgents\History;

use LarAgent\Core\Abstractions\ChatHistory;
use LarAgent\Core\Contracts\ChatHistory as ChatHistoryInterface;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Support\Facades\Log;

/**
 * Custom Chat History implementation que persiste conversaciones en base de datos
 * 
 * Similar a FileChatHistory pero usando base de datos en lugar de archivos
 */
class DatabaseChatHistory extends ChatHistory implements ChatHistoryInterface
{
    protected $userId;
    protected $accountId;
    protected $agentType;
    protected $originalAgentType; // Guardar el tipo original del agente (openai, claude, gemini)
    protected $modelName;
    protected $conversationId;
    protected $conversation;
    protected $lastSavedMessageCount = 0; // Evita guardados múltiples en el mismo ciclo
    
    public function __construct(string $name, array $options = [])
    {
        $this->userId = auth()->id() ?? $options['user_id'] ?? null;
        $this->accountId = $options['account_id'] ?? null;
        
        // ✅ Si es un agente de chat (openai, claude, gemini), usar 'chat-agent' como agent_type
        $this->originalAgentType = $options['agent_type'] ?? 'unknown';
        if (in_array($this->originalAgentType, ['openai', 'claude', 'gemini'])) {
            $this->agentType = 'chat-agent';
        } else {
            $this->agentType = $this->originalAgentType;
        }
        
        $this->modelName = $options['model_name'] ?? 'unknown';
        
        parent::__construct($name, $options);
        
        $this->loadOrCreateConversation();
        $this->readFromMemory();
    }
    
    /**
     * Leer mensajes desde la base de datos
     * Carga solo los últimos 20 mensajes visibles para optimizar tokens
     */
    public function readFromMemory(): void
    {
        if (!$this->conversationId) {
            $this->setMessages([]);
            return;
        }
        
        try {
            $dbMessages = ChatMessage::where('conversation_id', $this->conversationId)
                ->where('is_deleted', false)
                ->whereIn('role', ['user', 'assistant'])
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get()
                ->reverse();
            
            $messages = [];
            
            foreach ($dbMessages as $dbMessage) {
                $messageData = [
                    'role' => $dbMessage->role,
                    'content' => $dbMessage->content,
                ];
                
                // ✅ RECONSTRUIR FORMATO LARAGENT PARA MENSAJES CON IMÁGENES
                // Si el mensaje tiene imágenes en attachments, reconstruir el formato LarAgent
                if (!empty($dbMessage->attachments['images']) && is_array($dbMessage->attachments['images'])) {
                    $contentArray = [];
                    
                    // Agregar texto si existe
                    if (!empty($dbMessage->content)) {
                        $contentArray[] = [
                            'type' => 'text',
                            'text' => $dbMessage->content
                        ];
                    }
                    
                    // Agregar imágenes en formato LarAgent
                    foreach ($dbMessage->attachments['images'] as $imageUrl) {
                        $contentArray[] = [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => $imageUrl
                            ]
                        ];
                    }
                    
                    $messageData['content'] = $contentArray;
                    
                    Log::info('🖼️ Reconstruyendo mensaje con imágenes desde BD', [
                        'role' => $dbMessage->role,
                        'message_id' => $dbMessage->id,
                        'image_count' => count($dbMessage->attachments['images'])
                    ]);
                }
                
                $messages[] = $messageData;
            }
            
            // Usar el método del padre para construir los mensajes
            $this->setMessages($this->buildMessages($messages));
            
            Log::info('📖 Historial cargado (ventana optimizada)', [
                'conversation_id' => $this->conversationId,
                'messages_loaded' => count($messages),
                'limit' => 20,
                'roles_loaded' => array_unique(array_column($messages, 'role'))
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Error leyendo mensajes de BD', [
                'conversation_id' => $this->conversationId,
                'error' => $e->getMessage()
            ]);
            $this->setMessages([]);
        }
    }
    /**
     * Escribir mensajes a la base de datos (guardado incremental)
     */
    public function writeToMemory(): void
    {
        if (!$this->conversationId) {
            return;
        }
        
        try {
            $allMessages = $this->toArrayForStorage();
            
            $messagesToSave = array_values(array_filter($allMessages, function($msg) {
                $role = $msg['role'] ?? '';
                return in_array($role, ['user', 'assistant', 'tool']);
            }));
            
            $currentCount = count($messagesToSave);
            if ($currentCount === $this->lastSavedMessageCount) {
                return;
            }
            
            $dbMessageCount = ChatMessage::where('conversation_id', $this->conversationId)->count();
            
            if ($dbMessageCount === 0) {
                foreach ($messagesToSave as $messageData) {
                    $this->saveMessage($messageData);
                }
                
                $this->lastSavedMessageCount = $currentCount;
                
                Log::info('💾 Mensajes iniciales guardados', [
                    'conversation_id' => $this->conversationId,
                    'count' => count($messagesToSave)
                ]);
                
                $this->conversation->update(['last_message_at' => now()]);
                return;
            }
            
            $newMessagesCount = $currentCount - $dbMessageCount;
            
            if ($newMessagesCount > 0) {
                $newMessages = array_slice($messagesToSave, -$newMessagesCount);
                
                foreach ($newMessages as $messageData) {
                    $this->saveMessage($messageData);
                }
                
                $this->lastSavedMessageCount = $currentCount;
                
                Log::info('💾 Nuevos mensajes guardados', [
                    'conversation_id' => $this->conversationId,
                    'new_count' => $newMessagesCount,
                    'total_in_memory' => $currentCount,
                    'total_in_db_before' => $dbMessageCount
                ]);
                
                $this->conversation->update(['last_message_at' => now()]);
            }
            
        } catch (\Exception $e) {
            Log::error('❌ Error escribiendo mensajes a BD', [
                'conversation_id' => $this->conversationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Guardar un mensaje individual en BD
     * 
     * Maneja:
     * 1. Detección de tool calls de Claude (convertir a role='tool')
     * 2. Respuestas estructuradas (array con 'text' e 'images') - OpenAI
     * 3. JSON string estructurado - Gemini
     * 4. Texto plano con URLs - fallback
     */
    protected function saveMessage(array $messageData): void
    {
        $originalContent = $messageData['content'] ?? '';
        $role = $messageData['role'] ?? 'user';
        
        $contentType = 'text';
        $metadata = [];
        $attachments = null;
        $imageUrls = [];
        $text = '';
        $isClaudeToolCall = false;
        $isGeminiToolCall = false;
        
        // ✅ DETECTAR tool calls de Claude
        if (is_array($originalContent) && !empty($originalContent)) {
            // Detectar formato Claude: [{"type":"tool_use",...}] o [{"type":"tool_result",...}]
            if (isset($originalContent[0]['type'])) {
                $firstItemType = $originalContent[0]['type'] ?? '';
                if ($firstItemType === 'tool_use' || $firstItemType === 'tool_result') {
                    $isClaudeToolCall = true;
                    $role = 'tool';
                    
                    Log::info('🔧 Tool call de Claude detectado, convirtiendo a role=tool', [
                        'original_role' => $messageData['role'] ?? 'unknown',
                        'tool_type' => $firstItemType
                    ]);
                }
            }
        }
        
        // ✅ DETECTAR tool calls/results de Gemini (driver oficial LarAgent)
        // Formato tool call: parts=[{functionCall: {name, args}}]
        // Formato tool result: parts=[{functionResponse: {name, response: {content}}}]
        if (isset($messageData['parts']) && is_array($messageData['parts'])) {
            foreach ($messageData['parts'] as $part) {
                // Tool call (assistant llamando una herramienta)
                if (isset($part['functionCall'])) {
                    $isGeminiToolCall = true;
                    $role = 'tool';
                    
                    $toolName = $part['functionCall']['name'] ?? 'unknown';
                    $toolArgs = $part['functionCall']['args'] ?? [];
                    $text = "Tool call: {$toolName}(" . json_encode($toolArgs, JSON_UNESCAPED_UNICODE) . ")";
                    
                    Log::info('🔧 Tool call de Gemini detectado', [
                        'original_role' => $messageData['role'] ?? 'unknown',
                        'tool_name' => $toolName
                    ]);
                }
                // Tool result (respuesta de la herramienta)
                elseif (isset($part['functionResponse'])) {
                    $isGeminiToolCall = true;
                    $role = 'tool';
                    
                    // Extraer URLs del contenido del tool result
                    $responseContent = $part['functionResponse']['response']['content'] ?? '';
                    if (is_string($responseContent)) {
                        $pattern = '/(https:\/\/[^\s\)\]]+\.s3\.[^\s\)\]]+\.amazonaws\.com\/genesis\/(output-images|agent-generated-images)\/[^\s\)\]]+\.(?:png|jpg|jpeg|webp|gif))/i';
                        preg_match_all($pattern, $responseContent, $matches);
                        
                        if (!empty($matches[0])) {
                            $imageUrls = array_merge($imageUrls, array_unique($matches[0]));
                        }
                        
                        // Guardar el contenido del tool result
                        $text = "Tool result: " . ($part['functionResponse']['name'] ?? 'unknown') . " - " . $responseContent;
                    }
                    
                    Log::info('🔧 Tool result de Gemini detectado con imágenes', [
                        'original_role' => $messageData['role'] ?? 'unknown',
                        'tool_name' => $part['functionResponse']['name'] ?? 'unknown',
                        'image_count' => count($imageUrls)
                    ]);
                }
            }
        }
        
        // ✅ Caso 1: Contenido estructurado (array con 'text' e 'images') - OpenAI
        if (!$isGeminiToolCall && is_array($originalContent) && isset($originalContent['text'])) {
            $text = $originalContent['text'];
            
            if (!empty($originalContent['images'])) {
                $imageUrls = is_array($originalContent['images']) 
                    ? $originalContent['images'] 
                    : [$originalContent['images']];
                
                Log::info('✅ Imágenes extraídas de respuesta estructurada', [
                    'role' => $role,
                    'image_count' => count($imageUrls)
                ]);
            }
        }
        // ✅ Caso 2: JSON string estructurado - Gemini
        elseif (!$isGeminiToolCall && is_string($originalContent)) {
            $trimmed = trim($originalContent);
            
            // Extraer JSON si está envuelto en markdown ```json ... ```
            if (preg_match('/```json\s*(\{.*?\})\s*```/s', $trimmed, $matches)) {
                $trimmed = $matches[1];
            }
            
            // Parsear JSON
            if (!empty($trimmed) && $trimmed[0] === '{') {
                $decoded = json_decode($trimmed, true);
                
                if (json_last_error() === JSON_ERROR_NONE && isset($decoded['text'])) {
                    $text = $decoded['text'];
                    
                    if (!empty($decoded['images'])) {
                        $imageUrls = is_array($decoded['images']) 
                            ? $decoded['images'] 
                            : [$decoded['images']];
                        
                        Log::info('✅ Imágenes extraídas de JSON string', [
                            'role' => $role,
                            'image_count' => count($imageUrls)
                        ]);
                    }
                } else {
                    $text = $originalContent;
                }
            } else {
                $text = $originalContent;
            }
        }
        // ✅ Caso 3: Array complejo (Claude tool calls, contenido estructurado, o mensajes con imágenes)
        elseif (!$isGeminiToolCall && is_array($originalContent)) {
            if ($isClaudeToolCall) {
                // Guardar tool call como JSON completo
                $text = json_encode($originalContent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                // ✅ DETECTAR IMÁGENES EN MENSAJES DE USUARIO
                // Formato LarAgent: [{'type': 'text', 'text': '...'}, {'type': 'image_url', 'image_url': {'url': '...'}}]
                if ($role === 'user' && !empty($originalContent) && is_array($originalContent[0] ?? null)) {
                    $extractedImages = [];
                    $textParts = [];
                    
                    foreach ($originalContent as $item) {
                        // Extraer texto
                        if (isset($item['type']) && $item['type'] === 'text' && isset($item['text'])) {
                            $textParts[] = $item['text'];
                        }
                        // Extraer imágenes (formato LarAgent)
                        elseif (isset($item['type']) && $item['type'] === 'image_url') {
                            $imageUrl = $item['image_url']['url'] ?? $item['image_url'] ?? null;
                            if ($imageUrl && filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                                $extractedImages[] = $imageUrl;
                            }
                        }
                    }
                    
                    $text = implode("\n", $textParts);
                    
                    if (!empty($extractedImages)) {
                        $imageUrls = array_merge($imageUrls, $extractedImages);
                        
                        Log::info('✅ Imágenes extraídas de mensaje de usuario', [
                            'role' => $role,
                            'image_count' => count($extractedImages),
                            'urls' => $extractedImages
                        ]);
                    }
                } else {
                    // Para otros casos, solo extraer texto
                    $text = $this->extractTextFromContent($originalContent);
                }
            }
        }
        elseif (!$isGeminiToolCall) {
            $text = (string) $originalContent;
        }
        
        // Asegurar que $text es string
        if (!is_string($text)) {
            $text = (string) $text;
        }
        
        // ✅ Caso 4: Fallback - Buscar URLs en el texto (solo para assistant y tool)
        if (empty($imageUrls) && in_array($role, ['assistant', 'tool'])) {
            $pattern = '/(https:\/\/[^\s\)\]]+\.s3\.[^\s\)\]]+\.amazonaws\.com\/genesis\/(output-images|agent-generated-images|input-images)\/[^\s\)\]]+\.(?:png|jpg|jpeg|webp|gif))/i';
            preg_match_all($pattern, $text, $matches);
            
            if (!empty($matches[0])) {
                $imageUrls = array_unique($matches[0]);
                
                Log::info('✅ Imágenes extraídas del texto (fallback)', [
                    'role' => $role,
                    'image_count' => count($imageUrls)
                ]);
            }
        }
        
        // Guardar imágenes en attachments
        if (!empty($imageUrls)) {
            $attachments = ['images' => array_values($imageUrls)];
            
            // Limpiar URLs del texto para mensajes assistant
            if ($role === 'assistant') {
                // Guardar el texto original antes de limpiar
                $originalText = $text;
                
                // Remover URLs individualmente
                foreach ($imageUrls as $url) {
                    $text = str_replace($url, '', $text);
                }
                
                // Limpiar líneas que contienen solo URLs o patrones comunes
                $lines = explode("\n", $text);
                $cleanedLines = [];
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    
                    // Saltar líneas vacías, solo URLs, o patrones comunes
                    if (empty($line)) {
                        continue;
                    }
                    
                    // Saltar líneas que son solo URLs
                    if (preg_match('/^https?:\/\//', $line)) {
                        continue;
                    }
                    
                    // Saltar líneas con patrones como "Gato durmiendo:", "Perro durmiendo:", etc.
                    if (preg_match('/^(Gato|Perro|Imagen).*?:?\s*$/i', $line)) {
                        continue;
                    }
                    
                    // Saltar líneas que dicen "Las imágenes están listas..."
                    if (preg_match('/Las imágenes están listas/i', $line)) {
                        continue;
                    }
                    
                    $cleanedLines[] = $line;
                }
                
                $text = implode("\n", $cleanedLines);
                $text = preg_replace('/\n{3,}/', "\n\n", $text);
                $text = trim($text);
                
                // Si el texto quedó vacío después de limpiar, usar un mensaje por defecto
                if (empty($text)) {
                    $text = "Aquí tienes " . (count($imageUrls) > 1 ? "las imágenes" : "la imagen") . " que pediste.";
                }
            }
        }
        
        // No guardar mensajes vacíos (excepto tool calls/results estructurados o mensajes con attachments)
        if (empty(trim($text)) && !$isClaudeToolCall && !$isGeminiToolCall && empty($attachments)) {
            Log::warning('⚠️ Mensaje vacío, no se guardará', [
                'role' => $role
            ]);
            return;
        }
        
        // Determinar visibilidad: tools y system NO son visibles
        $isVisible = !in_array($role, ['tool', 'system']);
        
        // ✅ Si es un mensaje assistant sin attachments, buscar si hay un tool message reciente con imágenes
        // y copiarlas al assistant para que persistan después de recargar
        if ($role === 'assistant' && empty($attachments)) {
            $latestToolMessage = ChatMessage::where('conversation_id', $this->conversationId)
                ->where('role', 'tool')
                ->where('is_deleted', false)
                ->whereNotNull('attachments')
                ->orderBy('created_at', 'desc')
                ->first();
            
            if ($latestToolMessage && !empty($latestToolMessage->attachments['images'])) {
                // Solo copiar si es muy reciente (últimos 10 segundos)
                $timeDiff = now()->diffInSeconds($latestToolMessage->created_at);
                if ($timeDiff < 10) {
                    $attachments = $latestToolMessage->attachments;
                    
                    Log::info('📎 Imágenes copiadas del tool message al assistant', [
                        'tool_message_id' => $latestToolMessage->id,
                        'image_count' => count($attachments['images'] ?? [])
                    ]);
                }
            }
        }
        
        ChatMessage::create([
            'conversation_id' => $this->conversationId,
            'role' => $role,
            'content' => $text,
            'content_type' => $contentType,
            'model_used' => $this->modelName,
            'metadata' => !empty($metadata) ? $metadata : null,
            'attachments' => $attachments,
            'is_visible' => $isVisible,
        ]);
        
        Log::info('💾 Mensaje guardado', [
            'role' => $role,
            'has_attachments' => !empty($attachments),
            'is_visible' => $isVisible,
            'is_claude_tool_call' => $isClaudeToolCall
        ]);
    }
    
    /**
     * Guardar la clave de la conversación
     */
    public function saveKeyToMemory(): void
    {
        // No necesario en BD, las conversaciones ya están guardadas
    }
    
    /**
     * Cargar todas las claves de conversaciones
     */
    public function loadKeysFromMemory(): array
    {
        if (!$this->userId) {
            return [];
        }
        
        try {
            $conversations = ChatConversation::where('user_id', $this->userId)
                ->where('status', 'active')
                ->whereNotNull('context_metadata')
                ->get();
            
            $keys = [];
            foreach ($conversations as $conversation) {
                if (isset($conversation->context_metadata['session_key'])) {
                    $keys[] = $conversation->context_metadata['session_key'];
                }
            }
            
            return $keys;
        } catch (\Exception $e) {
            Log::error('Error cargando claves de BD', [
                'user_id' => $this->userId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Eliminar un chat de la memoria
     */
    public function removeChatFromMemory(string $key): void
    {
        if (!$this->userId) {
            return;
        }
        
        try {
            $conversation = ChatConversation::where('user_id', $this->userId)
                ->whereRaw("JSON_EXTRACT(context_metadata, '$.session_key') = ?", [$key])
                ->first();
            
            if ($conversation) {
                $conversation->update(['status' => 'deleted']);
                
                Log::info('🗑️ Chat eliminado', [
                    'conversation_id' => $conversation->id,
                    'session_key' => $key
                ]);
            }
            
            $this->removeChatKey($key);
        } catch (\Exception $e) {
            Log::error('Error eliminando chat de BD', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    // ============================================================================
    // MÉTODOS HELPER PRIVADOS
    // ============================================================================
    
    /**
     * Cargar o crear conversación en la base de datos
     */
    protected function loadOrCreateConversation(): void
    {
        if (!$this->userId) {
            Log::warning('⚠️ No se puede crear conversación sin usuario autenticado');
            return;
        }
        
        $identifier = $this->getIdentifier();
        
        // Si el identifier es un ID numérico, cargar directamente
        if (is_numeric($identifier)) {
            $this->conversation = ChatConversation::where('id', $identifier)
                ->where('user_id', $this->userId)
                ->first();
                
            if ($this->conversation) {
                $this->conversationId = $this->conversation->id;
                return;
            }
        }
        
        // Buscar conversación existente por session_key
        $this->conversation = ChatConversation::where('user_id', $this->userId)
            ->where('agent_type', $this->agentType)
            ->where('status', 'active')
            ->whereRaw("JSON_EXTRACT(context_metadata, '$.session_key') = ?", [$identifier])
            ->latest('updated_at')
            ->first();
        
        // Si no existe, crear nueva conversación
        if (!$this->conversation) {
            // ✅ Guardar el agente original en context_metadata para poder detectarlo después
            $contextMetadata = [
                'session_key' => $identifier
            ];
            
            // Si es un agente de chat, guardar el tipo original en metadata
            if (in_array($this->originalAgentType, ['openai', 'claude', 'gemini'])) {
                $contextMetadata['original_agent_type'] = $this->originalAgentType;
            }
            
            $this->conversation = ChatConversation::create([
                'user_id' => $this->userId,
                'account_id' => $this->accountId,
                'agent_type' => $this->agentType, // Será 'chat-agent' para openai/claude/gemini
                'model_name' => $this->modelName,
                'title' => 'Nueva conversación - ' . now()->format('d/m/Y H:i'),
                'context_metadata' => $contextMetadata,
                'status' => 'active',
                'last_message_at' => now(),
            ]);
            
            Log::info('📝 Nueva conversación creada', [
                'conversation_id' => $this->conversation->id,
                'session_key' => $identifier
            ]);
        }
        
        $this->conversationId = $this->conversation->id;
    }
    
    /**
     * Extraer texto de contenido estructurado
     */
    protected function extractTextFromContent($content): string
    {
        if (is_string($content)) {
            return $content;
        }
        
        if (is_array($content)) {
            // Formato Claude/Anthropic: [['type' => 'text', 'text' => '...']]
            if (isset($content[0]['type']) && $content[0]['type'] === 'text') {
                $texts = [];
                foreach ($content as $item) {
                    if (isset($item['type']) && $item['type'] === 'text' && isset($item['text'])) {
                        $texts[] = $item['text'];
                    }
                }
                return implode("\n", $texts);
            }
            
            return json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        
        return (string) $content;
    }
    
    /**
     * Eliminar clave de conversación
     */
    protected function removeChatKey(string $key): void
    {
        // No necesario en BD, la eliminación se maneja en removeChatFromMemory()
    }
}

<?php

namespace App\AiAgents;

use LarAgent\Agent;
use App\AiAgents\Tools\DocumentContext;
use App\AiAgents\Tools\ReadExternalFile;
use App\AiAgents\Tools\Clima;
use App\AiAgents\Tools\ImageGenerator;
use App\AiAgents\History\DatabaseChatHistory;

class ChatGeminiAgent extends Agent
{
    protected $model = 'gemini-2.5-pro';
    protected $history = DatabaseChatHistory::class;
    protected $provider = 'gemini_native';
    protected $contextWindowSize = 4000;
    protected $reinjectInstructionsPer = 0;
    protected $storeMeta = true; // ✅ Activar guardado de metadata (tokens, costos)
    protected $tools = [
        DocumentContext::class,
        ReadExternalFile::class,
        ImageGenerator::class,
    ];
    
    /**
     * ⚠️ NOTA: No se usa $responseSchema en Gemini
     * 
     * Gemini no permite structured output (response_mime_type: 'application/json')
     * cuando hay function calling (tools) activos simultáneamente.
     * 
     * Solución: Las instrucciones del sistema le piden a Gemini que devuelva
     * JSON manualmente, el cual es parseado por normalizeAgentResponse()
     */

    public function instructions()
    {
         $instructions = "Eres un asistente de chat experto y amigable. Mantén un tono profesional, útil y en español. Solo respondes para cosas productivas de marketing o cualquier tarea que implique marketing o publicidad o cualquier cosa productiva nada de ocio o entretenimiento.

FORMATO DE RESPUESTA (MUY IMPORTANTE):
Debes responder SIEMPRE en formato JSON con esta estructura exacta:
{
  \"text\": \"tu respuesta completa al usuario\",
  \"images\": [\"url1\", \"url2\"]
}

IMPORTANTE sobre imágenes generadas:
- Cuando uses la herramienta ImageGenerator, esta te devolverá las URLs de las imágenes generadas.
- Debes extraer SOLO las URLs (que comienzan con https://) del resultado de la herramienta.
- Coloca estas URLs en el campo 'images' del JSON.
- En el campo 'text', describe lo que generaste pero NO incluyas las URLs completas.
- Si no generaste imágenes, el campo 'images' debe ser un array vacío [].
- NO uses formato markdown para las imágenes, solo JSON puro.

Ejemplo sin imágenes:
{\"text\": \"¡Hola! ¿En qué puedo ayudarte hoy?\", \"images\": []}

Ejemplo con imágenes:
{\"text\": \"Aquí está tu imagen de un gatito tierno.\", \"images\": [\"https://santo-x.s3.us-east-2.amazonaws.com/genesis/output-images/image.png\"]}";
        
        // ✅ Si hay documento seleccionado, agregar contexto en las instrucciones (NO en el mensaje del usuario)
        if (session()->has('chat_document')) {
            $documentId = session()->get('chat_document');
            $instructions .= "\n\nCONTEXTO IMPORTANTE: El usuario ha seleccionado un documento (ID: {$documentId}) desde la interfaz. 
DEBES hacer lo siguiente AUTOMÁTICAMENTE en tu primera respuesta:
1. Usa INMEDIATAMENTE la herramienta 'get_document_context' con el parámetro document_id=\"{$documentId}\" (el ID es: {$documentId})
2. Lee el contenido completo del documento
3. Responde al usuario confirmando que has leído el documento y pregunta en qué puedes ayudarle

Si el usuario menciona 'el documento', 'este documento', 'el brief', 'el génesis', etc., se refiere al documento con ID {$documentId}.";
        }
        
        return $instructions;
    }

    public function prompt($message)
    {
        // ✅ NO modificar el mensaje del usuario, devolverlo tal cual
        return $message;
    }

    /**
     * ✅ Crear instancia de DatabaseChatHistory con configuración correcta
     */
    public function createChatHistory($name)
    {
        return new DatabaseChatHistory($name, [
            'user_id' => auth()->id(),
            'agent_type' => 'gemini',
            'model_name' => $this->model,
            'context_window' => $this->contextWindowSize,
            'store_meta' => $this->storeMeta,
        ]);
    }

    /**
     * Sobrescribir respond() para extraer imágenes de tool results ANTES de que se guarden
     */
    public function respond(?string $message = null): \LarAgent\Core\Contracts\Message|array|string
    {
        $response = parent::respond($message);
        
        // Extraer URLs de imágenes del historial en memoria (tool results de Gemini tienen role='user')
        $history = $this->chatHistory();
        $messages = $history->getMessages();
        
        $imageUrls = [];
        
        // Buscar en los últimos mensajes 'user' que contengan functionResponse (tool results)
        $recentMessages = array_slice($messages, -10);
        
        \Log::info('🔍 DEBUG - Analizando mensajes en memoria', [
            'total' => count($recentMessages)
        ]);
        
        foreach ($recentMessages as $msg) {
            $arr = $msg->toArray();
            $role = $arr['role'] ?? '';
            $content = $arr['content'] ?? '';
            
            \Log::info('🔍 DEBUG - Mensaje en memoria', [
                'role' => $role,
                'content_type' => gettype($content),
                'content_structure' => is_array($content) ? array_keys($content) : 'not_array',
                'content_preview' => is_string($content) ? substr($content, 0, 200) : json_encode($content, JSON_UNESCAPED_SLASHES)
            ]);
            
            // Gemini (driver oficial) guarda tool results como role='user' con parts=[{functionResponse: {...}}]
            if ($role === 'user' && is_array($content)) {
                // Buscar en parts (formato del driver oficial de LarAgent)
                $parts = $content['parts'] ?? $content;
                
                if (is_array($parts)) {
                    foreach ($parts as $part) {
                        // Driver oficial de LarAgent usa 'functionResponse'
                        if (isset($part['functionResponse']['response']['content'])) {
                            $responseContent = $part['functionResponse']['response']['content'];
                            
                            \Log::info('✅ functionResponse encontrado en memoria', [
                                'content_preview' => substr($responseContent, 0, 200)
                            ]);
                            
                            // Extraer URLs del contenido del tool result
                            if (is_string($responseContent)) {
                                $pattern = '/(https:\/\/[^\s\)\]]+\.s3\.[^\s\)\]]+\.amazonaws\.com\/genesis\/(output-images|agent-generated-images)\/[^\s\)\]]+\.(?:png|jpg|jpeg|webp|gif))/i';
                                preg_match_all($pattern, $responseContent, $matches);
                                
                                if (!empty($matches[0])) {
                                    $imageUrls = array_merge($imageUrls, $matches[0]);
                                }
                            }
                        }
                    }
                }
            }
        }
        
        $imageUrls = array_unique($imageUrls);
        
        \Log::info('🖼️ Gemini - URLs extraídas de tool results en memoria', [
            'count' => count($imageUrls),
            'urls' => array_values($imageUrls)
        ]);
        
        // Si la respuesta es string, intentar convertirla a JSON con las imágenes
        if (is_string($response) && !empty($response)) {
            $trimmed = trim($response);
            
            // Si ya es JSON válido con images, dejarlo
            if (preg_match('/```json\s*(\{.*?\})\s*```/s', $trimmed, $matches)) {
                $jsonStr = $matches[1];
                $decoded = json_decode($jsonStr, true);
                
                if (json_last_error() === JSON_ERROR_NONE && isset($decoded['text'])) {
                    // Si el JSON ya tiene images, usar esas
                    if (!empty($decoded['images'])) {
                        return $response;
                    }
                    // Si no tiene images pero encontramos URLs, agregarlas
                    elseif (!empty($imageUrls)) {
                        $decoded['images'] = array_values($imageUrls);
                        $response = "```json\n" . json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n```";
                        
                        \Log::info('✅ URLs agregadas a JSON de Gemini', [
                            'images_added' => count($imageUrls)
                        ]);
                        
                        return $response;
                    }
                }
            }
            
            // Si no es JSON pero tenemos URLs, crear JSON
            if (!empty($imageUrls)) {
                $cleanText = $trimmed;
                
                // Limpiar URLs del texto
                foreach ($imageUrls as $url) {
                    $cleanText = str_replace($url, '', $cleanText);
                }
                
                $cleanText = preg_replace('/Las imágenes están listas.*$/is', '', $cleanText);
                $cleanText = preg_replace('/:\s*$/m', '', $cleanText);
                $cleanText = preg_replace('/\n{3,}/', "\n\n", $cleanText);
                $cleanText = trim($cleanText);
                
                if (empty($cleanText)) {
                    $cleanText = "Aquí tienes " . (count($imageUrls) > 1 ? "las imágenes" : "la imagen") . " que pediste.";
                }
                
                $response = "```json\n" . json_encode([
                    'text' => $cleanText,
                    'images' => array_values($imageUrls)
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n```";
                
                \Log::info('✅ JSON creado para Gemini con URLs extraídas', [
                    'text_length' => strlen($cleanText),
                    'images_count' => count($imageUrls)
                ]);
            }
        }
        
        return $response;
    }
}
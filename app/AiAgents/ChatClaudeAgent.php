<?php

namespace App\AiAgents;

use LarAgent\Agent;
use App\AiAgents\Tools\DocumentContext;
use App\AiAgents\Tools\ReadExternalFile;
use App\AiAgents\Tools\ImageGenerator;
use App\AiAgents\History\DatabaseChatHistory;

class ChatClaudeAgent extends Agent
{
    protected $model = 'claude-sonnet-4-5-20250929';
    protected $history = DatabaseChatHistory::class;
    protected $provider = 'claude';
    protected $contextWindowSize = 200000;
    protected $reinjectInstructionsPer = 0;
    protected $storeMeta = true; // ✅ Activar guardado de metadata (tokens, costos)
    protected $tools = [
        DocumentContext::class,
        ReadExternalFile::class,
        ImageGenerator::class,
    ];
    
    /**
     * ⚠️ NOTA: No se usa $responseSchema en Claude
     * 
     * El driver de Claude en LarAgent no soporta structured output nativo.
     * 
     * Solución: Las instrucciones del sistema le piden a Claude que devuelva
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
{\"text\": \"Aquí está tu imagen de un gato durmiendo.\", \"images\": [\"https://santo-x.s3.us-east-2.amazonaws.com/genesis/output-images/image.png\"]}";
        
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
            'agent_type' => 'claude',
            'model_name' => $this->model,
            'context_window' => $this->contextWindowSize,
            'store_meta' => $this->storeMeta,
        ]);
    }

    /**
     * Sobrescribir el método respond para normalizar el historial antes de enviar
     */
    public function respond(?string $message = null): \LarAgent\Core\Contracts\Message|array|string
    {
        // Normalizar el historial antes de enviar
        $this->normalizeClaudeHistory();
        
        return parent::respond($message);
    }

    /**
     * Normaliza el historial al formato esperado por Claude
     * Claude requiere: ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'mensaje']]]
     */
    private function normalizeClaudeHistory()
    {
        $history = $this->chatHistory();
        $messages = $history->getMessages();

        if (empty($messages)) {
            return;
        }

        // Verificar si algún mensaje necesita normalización
        $needsNormalization = false;
        foreach ($messages as $message) {
            $messageArray = $message->toArray();
            $content = $messageArray['content'] ?? '';
            
            // Si encuentra un string o array mal formado, necesita normalización
            if (is_string($content) || (is_array($content) && !isset($content[0]['type']))) {
                $needsNormalization = true;
                break;
            }
        }

        // Si no necesita normalización, retornar
        if (!$needsNormalization) {
            return;
        }

        // Guardar los mensajes normalizados
        $normalizedMessages = [];

        foreach ($messages as $message) {
            $messageArray = $message->toArray();
            $role = $messageArray['role'] ?? 'user';
            $content = $messageArray['content'] ?? '';

            // Si el contenido ya está en formato array correcto, dejarlo
            if (is_array($content) && isset($content[0]['type']) && $content[0]['type'] === 'text') {
                $normalizedMessages[] = $messageArray;
                continue;
            }

            // Si es un string, convertirlo al formato esperado
            if (is_string($content)) {
                $normalizedMessages[] = [
                    'role' => $role,
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $content
                        ]
                    ]
                ];
                continue;
            }

            // Si es un array pero no tiene la estructura correcta
            if (is_array($content)) {
                // Intentar extraer el texto
                $text = '';
                if (isset($content['text'])) {
                    $text = $content['text'];
                } elseif (isset($content[0]) && is_string($content[0])) {
                    $text = $content[0];
                } else {
                    $text = json_encode($content);
                }

                $normalizedMessages[] = [
                    'role' => $role,
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $text
                        ]
                    ]
                ];
            }
        }

        // Limpiar el historial actual
        $this->clear();
        
        // Reconstruir el historial con mensajes normalizados usando la clase correcta
        foreach ($normalizedMessages as $msg) {
            // Crear un mensaje usando la clase Message de Laragent
            $message = new \LarAgent\Message($msg['role'], $msg['content']);
            $history->addMessage($message);
        }
        
        // Guardar manualmente en memoria
        $history->writeToMemory();
    }
}
<?php

namespace App\AiAgents;

use LarAgent\Agent;
use LarAgent\Attributes\Tool;
use App\AiAgents\Tools\Clima;
use App\AiAgents\Tools\DocumentContext;
use App\AiAgents\Tools\ReadExternalFile;
use App\AiAgents\Tools\ImageGenerator;
use App\AiAgents\History\DatabaseChatHistory;

class ChatOpenaiAgent extends Agent
{
    protected $model = 'gpt-5-nano-2025-08-07';
    protected $history = DatabaseChatHistory::class;
    protected $provider = 'default';
    protected $contextWindowSize = 4000;
    protected $reinjectInstructionsPer = 0;
    protected $storeMeta = true; 
    protected $tools = [
        DocumentContext::class,
        ReadExternalFile::class,
        ImageGenerator::class,
    ];
    
    /**
     * ✅ Structured Output: Normalizar respuestas en formato JSON
     * Esto garantiza que todas las respuestas tengan el mismo formato
     */
    protected $responseSchema = [
        'name' => 'agent_response',
        'strict' => true,
        'schema' => [
            'type' => 'object',
            'properties' => [
                'text' => [
                    'type' => 'string',
                    'description' => 'Tu respuesta completa al usuario en español',
                ],
                'images' => [
                    'type' => 'array',
                    'description' => 'URLs de imágenes generadas (solo si usaste la herramienta ImageGenerator)',
                    'items' => [
                        'type' => 'string'
                    ],
                    'default' => []
                ],
            ],
            'required' => ['text', 'images'],
            'additionalProperties' => false,
        ]
    ];

    public function instructions()
    {
        $instructions = "Eres un asistente de chat experto y amigable. Mantén un tono profesional, útil y en español. Solo respondes para cosas productivas de marketing o cualquier tarea que implique marketing o publicidad o cualquier cosa productiva nada de ocio o entretenimiento.

IMPORTANTE sobre imágenes generadas:
- Cuando uses la herramienta ImageGenerator, esta te devolverá las URLs de las imágenes generadas.
- Debes extraer SOLO las URLs (que comienzan con https://) del resultado de la herramienta.
- Coloca estas URLs en el campo 'images' del JSON de respuesta.
- En el campo 'text', describe lo que generaste pero NO incluyas las URLs completas, solo una breve descripción.
- NO uses formato markdown para las imágenes, ya que se mostrarán automáticamente.";
        
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
            'agent_type' => 'openai',
            'model_name' => $this->model,
            'context_window' => $this->contextWindowSize,
            'store_meta' => $this->storeMeta,
        ]);
    }
  

// #[Tool('Obtener el clima en una ubicación dada')]
// public function weatherTool($location, $unit = 'celsius')
// {
//     return 'El clima en '.$location.' es '.'20'.' grados '.$unit;
// }
}
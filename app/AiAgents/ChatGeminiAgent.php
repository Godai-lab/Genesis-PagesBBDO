<?php

namespace App\AiAgents;

use LarAgent\Agent;
use App\AiAgents\Tools\Clima;
use App\AiAgents\Tools\DocumentContext;
use App\AiAgents\Tools\ReadExternalFile;

class ChatGeminiAgent extends Agent
{
    /**
     * Modelo de Gemini a usar
     */
    protected $model = 'gemini-2.5-pro';

    /**
     * Historial en base de datos (tabla laragent_messages).
     * @see https://docs.laragent.ai/v1/context/history
     */
    protected $history = 'database';

    /**
     * Provider de Gemini configurado en laragent.php
     */
    protected $provider = 'gemini_native';

    /**
     * Temperatura para las respuestas (0-1)
     * Menor = más predecible, Mayor = más creativo
     */
    protected $temperature = 0.7;

    /**
     * Máximo de tokens en la respuesta
     */
    protected $maxCompletionTokens = 4000;

    /**
     * Guardar metadata con los mensajes
     */
    protected $storeMeta = true;

    /**
     * Herramientas disponibles para el agente
     * 
     * - Clima: Obtener información del clima
     * - DocumentContext: Leer documentos Genesis de la base de datos
     * - ReadExternalFile: Leer archivos externos subidos por el usuario (PDF, Word, Excel, etc.)
     */
    protected $tools = [
        Clima::class,
        DocumentContext::class,
        ReadExternalFile::class,
    ];

    /**
     * Instrucciones del sistema para el agente
     * 
     * Define la personalidad, comportamiento y cómo usar las herramientas
     */
    public function instructions()
    {
        $instructions = "Eres un asistente de chat experto y amigable. Mantén un tono profesional, útil y en español. 

Solo respondes para cosas productivas de marketing o cualquier tarea que implique marketing o publicidad o cualquier cosa productiva. No proporciones ayuda para ocio o entretenimiento.

FORMATO DE RESPUESTAS: 
- Responde SIEMPRE en texto plano, conversacional y natural.
- NO uses formato JSON en tus respuestas.
- Sé claro, conciso y útil.

HERRAMIENTAS DISPONIBLES:

1. 'get_document_context' - Para leer DOCUMENTOS GENESIS de la base de datos
   - Estos son documentos pre-cargados en el sistema
   - Usa cuando el usuario seleccione un documento Genesis desde el selector
   
2. 'read_external_file' - Para leer ARCHIVOS EXTERNOS subidos por el usuario
   - PDF, Word (.doc, .docx), Excel (.xls, .xlsx), CSV, TXT
   - El mensaje del usuario incluirá instrucciones específicas si hay un archivo adjunto
   - SOLO usa esta herramienta si ves instrucciones como: '[El usuario ha subido un archivo: ...]'
   
3. 'get_weather' - Para obtener información del clima de una ciudad";
        
        return $instructions;
    }

    /**
     * Procesar el mensaje del usuario antes de enviarlo al LLM
     * Aquí puedes agregar contexto adicional o modificar el mensaje
     * 
     * @param string $message
     * @return string
     */
    public function prompt($message)
    {
        // Devolver el mensaje tal cual (sin modificaciones)
        return $message;
    }
}

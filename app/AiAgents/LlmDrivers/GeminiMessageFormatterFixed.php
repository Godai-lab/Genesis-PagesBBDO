<?php

namespace App\AiAgents\LlmDrivers;

use LarAgent\Drivers\Gemini\GeminiMessageFormatter;
use LarAgent\Messages\ToolResultMessage;

/**
 * Formateador que corrige el manejo de functionResponse en historial persistido:
 * - response solo con 'content' (formato que espera la API de Gemini)
 * - name nunca vacío (fallback cuando tool_name se pierde al cargar historial)
 */
class GeminiMessageFormatterFixed extends GeminiMessageFormatter
{
    protected function formatToolResultMessage(ToolResultMessage $message): array
    {
        $toolName = $message->getToolName();
        if ($toolName === '' || $toolName === null) {
            $toolName = 'tool_result'; // Fallback: historial persistido puede no tener tool_name a nivel top
        }
        $responseContent = $message->getContentAsString();

        return [
            'role' => 'user',
            'parts' => [
                [
                    'functionResponse' => [
                        'name' => $toolName,
                        'response' => [
                            'content' => $responseContent,
                        ],
                    ],
                ],
            ],
        ];
    }
}

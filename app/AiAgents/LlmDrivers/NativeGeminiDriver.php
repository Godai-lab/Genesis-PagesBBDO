<?php

namespace App\AiAgents\LlmDrivers;

use GuzzleHttp\Client;
use LarAgent\Core\Abstractions\LlmDriver;
use LarAgent\Core\Contracts\LlmDriver as LlmDriverInterface;
use LarAgent\Core\Contracts\Tool as ToolInterface;
use LarAgent\Core\Contracts\ToolCall as ToolCallInterface;
use LarAgent\Messages\AssistantMessage;
use LarAgent\Messages\StreamedAssistantMessage;
use LarAgent\Messages\ToolCallMessage;
use LarAgent\ToolCall;
use Illuminate\Support\Facades\Log;

/**
 * Native Gemini Driver con soporte completo para imágenes
 * 
 * Este driver usa la API nativa de Gemini en lugar del endpoint OpenAI-compatible
 * Formato de respuesta: { candidates: [...] } en lugar de { choices: [...] }
 */
class NativeGeminiDriver extends LlmDriver implements LlmDriverInterface
{
    protected Client $client;
    protected string $apiKey;
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/';

    public function __construct(array $settings = [])
    {
        parent::__construct($settings);
        
        $this->apiKey = $settings['api_key'] ?? throw new \Exception('API key is required for Gemini driver.');
        $this->baseUrl = $settings['api_url'] ?? $this->baseUrl;
        
        // Asegurar que baseUrl termine con /
        if (!str_ends_with($this->baseUrl, '/')) {
            $this->baseUrl .= '/';
        }
        
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 60,
        ]);
    }

    public function sendMessage(array $messages, array $options = []): AssistantMessage
    {
        $payload = $this->preparePayload($messages, $options);
        
        // 🔍 DEBUG: Loguear payload enviado
        Log::info('📤 Gemini Request Payload:', [
            'model' => $payload['model'],
            'contents_count' => count($payload['body']['contents'] ?? []),
            'has_system' => isset($payload['body']['system_instruction']),
            'has_tools' => isset($payload['body']['tools']),
        ]);
        
        try {
            $response = $this->client->post("models/{$payload['model']}:generateContent", [
                'json' => $payload['body'],
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $this->apiKey,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $this->lastResponse = $data;

            // 🔍 DEBUG: Loguear respuesta completa
            Log::info('📥 Gemini API Raw Response:', [
                'full_response' => $data,
            ]);

            return $this->parseResponse($data);
            
        } catch (\Exception $e) {
            Log::error('💥 Gemini API Error:', [
                'message' => $e->getMessage(),
                'payload' => $payload,
            ]);
            throw new \Exception('Gemini API request failed: ' . $e->getMessage());
        }
    }

    public function sendMessageStreamed(array $messages, array $options = [], ?callable $callback = null): \Generator
    {
        $payload = $this->preparePayload($messages, $options);
        
        try {
            $response = $this->client->post("models/{$payload['model']}:streamGenerateContent?alt=sse", [
                'json' => $payload['body'],
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $this->apiKey,
                ],
                'stream' => true,
            ]);

            $streamedMessage = new StreamedAssistantMessage();
            $body = $response->getBody();

            while (!$body->eof()) {
                $line = $this->readLine($body);
                
                if (empty($line) || !str_starts_with($line, 'data: ')) {
                    continue;
                }

                $jsonData = substr($line, 6); // Remove "data: " prefix
                
                if ($jsonData === '[DONE]') {
                    break;
                }

                $chunk = json_decode($jsonData, true);
                
                if (isset($chunk['candidates'][0]['content']['parts'])) {
                    foreach ($chunk['candidates'][0]['content']['parts'] as $part) {
                        if (isset($part['text'])) {
                            $streamedMessage->appendContent($part['text']);
                            
                            if ($callback) {
                                $callback($streamedMessage);
                            }
                            
                            yield $streamedMessage;
                        }
                    }
                }
            }

            // Marcar como completo
            if (isset($chunk['usageMetadata'])) {
                $streamedMessage->setUsage($this->formatUsage($chunk['usageMetadata']));
            }
            
            $streamedMessage->setComplete(true);
            yield $streamedMessage;
            
        } catch (\Exception $e) {
            Log::error('Gemini Streaming Error:', [
                'message' => $e->getMessage(),
            ]);
            throw new \Exception('Gemini streaming failed: ' . $e->getMessage());
        }
    }

    protected function preparePayload(array $messages, array $options = []): array
    {
        $model = $options['model'] ?? $this->settings['model'] ?? 'gemini-2.0-flash-exp';
        
        // Separar system instructions y mensajes normales
        $systemInstruction = null;
        $contents = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                // System puede venir como string o array
                $systemText = is_string($message['content']) 
                    ? $message['content'] 
                    : ($message['content'][0]['text'] ?? $message['content']);
                    
                $systemInstruction = [
                    'parts' => [
                        ['text' => $systemText]
                    ]
                ];
            } else {
                $parts = [];
                
                // Normalizar content: si es string, convertir a array
                $contentArray = is_string($message['content']) 
                    ? [['type' => 'text', 'text' => $message['content']]] 
                    : $message['content'];
                
                foreach ($contentArray as $item) {
                    // Manejar texto
                    if (isset($item['type']) && $item['type'] === 'text') {
                        $parts[] = ['text' => $item['text']];
                    }
                    // Manejar imágenes
                    elseif (isset($item['type']) && $item['type'] === 'image_url') {
                        $imageUrl = $item['image_url']['url'] ?? $item['image_url'];
                        $parts[] = $this->convertImageToPart($imageUrl);
                    }
                    // Manejar function_call (cuando el modelo quiere llamar una herramienta)
                    elseif (isset($item['type']) && $item['type'] === 'function_call') {
                        $parts[] = [
                            'functionCall' => [
                                'name' => $item['name'],
                                'args' => json_decode($item['arguments'], true),
                            ],
                        ];
                    }
                    // Manejar function_result (respuesta de una herramienta)
                    elseif (isset($item['type']) && $item['type'] === 'function_result') {
                        $parts[] = [
                            'functionResponse' => [
                                'name' => $item['name'],
                                'response' => [
                                    'content' => $item['content'],
                                ],
                            ],
                        ];
                    }
                    // Fallback: si es string directo
                    elseif (is_string($item)) {
                        $parts[] = ['text' => $item];
                    }
                }

                $contents[] = [
                    'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                    'parts' => $parts,
                ];
            }
        }

        $body = ['contents' => $contents];

        // Agregar system instruction si existe
        if ($systemInstruction) {
            $body['system_instruction'] = $systemInstruction;
        }

        // Agregar configuración de generación
        $generationConfig = [];
        
        if (isset($options['temperature'])) {
            $generationConfig['temperature'] = $options['temperature'];
        }
        
        if (isset($options['max_completion_tokens'])) {
            $generationConfig['maxOutputTokens'] = $options['max_completion_tokens'];
        } elseif (isset($this->settings['max_completion_tokens'])) {
            $generationConfig['maxOutputTokens'] = $this->settings['max_completion_tokens'];
        }

        if (!empty($generationConfig)) {
            $body['generationConfig'] = $generationConfig;
        }

        // Agregar tools si existen
        if (!empty($this->tools)) {
            $body['tools'] = $this->formatToolsForPayload();
        }

        return [
            'model' => $model,
            'body' => $body,
        ];
    }

    /**
     * Convierte una imagen URL a formato inline_data de Gemini
     * Gemini espera: { inline_data: { mime_type: "image/jpeg", data: "base64..." } }
     */
    protected function convertImageToPart(string $imageUrl): array
    {
        try {
            // Descargar imagen
            $imageData = file_get_contents($imageUrl);
            
            if ($imageData === false) {
                throw new \Exception("Failed to download image from: {$imageUrl}");
            }

            // Obtener mime type
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($imageData);

            // Convertir a base64
            $base64Data = base64_encode($imageData);

            return [
                'inline_data' => [
                    'mime_type' => $mimeType,
                    'data' => $base64Data,
                ]
            ];
            
        } catch (\Exception $e) {
            Log::error('Image conversion error:', [
                'url' => $imageUrl,
                'error' => $e->getMessage(),
            ]);
            
            // Fallback: agregar como texto
            return ['text' => "[Error loading image: {$imageUrl}]"];
        }
    }

    /**
     * Parsea la respuesta de Gemini al formato de Laragent
     * Gemini responde: { candidates: [{ content: { parts: [...] } }] }
     */
    protected function parseResponse(array $data): AssistantMessage
    {
        // 🔍 DEBUG: Verificar estructura de respuesta
        Log::info('🔍 Parseando respuesta de Gemini:', [
            'has_candidates' => isset($data['candidates']),
            'candidates_count' => isset($data['candidates']) ? count($data['candidates']) : 0,
            'first_candidate' => $data['candidates'][0] ?? null,
        ]);
        
        if (!isset($data['candidates'][0])) {
            Log::error('❌ No se encontraron candidates en la respuesta de Gemini', [
                'response_keys' => array_keys($data),
                'full_data' => $data,
            ]);
            throw new \Exception('Invalid Gemini response: no candidates found');
        }

        $candidate = $data['candidates'][0];
        $content = $candidate['content'] ?? [];
        $parts = $content['parts'] ?? [];

        // 🔍 DEBUG: Verificar estructura de content
        Log::info('🔍 Estructura de content:', [
            'has_content' => isset($candidate['content']),
            'has_parts' => isset($content['parts']),
            'parts_count' => count($parts),
            'parts_preview' => $parts,
        ]);

        // Extraer metadata de usage
        $metadata = [];
        if (isset($data['usageMetadata'])) {
            $metadata['usage'] = $this->formatUsage($data['usageMetadata']);
        }

        // Verificar si hay tool calls (functionCall)
        $toolCalls = [];
        foreach ($parts as $part) {
            if (isset($part['functionCall'])) {
                $functionCall = $part['functionCall'];
                
                Log::info('🔧 Tool call detectado:', [
                    'name' => $functionCall['name'] ?? 'unknown',
                    'args' => $functionCall['args'] ?? [],
                ]);
                
                $toolCalls[] = new ToolCall(
                    uniqid('gemini_tool_'),
                    $functionCall['name'] ?? '',
                    json_encode($functionCall['args'] ?? [])
                );
            }
        }

        // Si hay tool calls, devolver ToolCallMessage
        if (!empty($toolCalls)) {
            $message = $this->toolCallsToMessage($toolCalls);
            
            return new ToolCallMessage(
                toolCalls: $toolCalls,
                message: $message,
                metadata: $metadata
            );
        }

        // Extraer texto
        $text = '';
        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $text .= $part['text'];
            }
        }

        // 🔍 DEBUG: Resultado final
        Log::info('✅ Texto extraído de Gemini:', [
            'text_length' => strlen($text),
            'text_preview' => substr($text, 0, 200),
        ]);
        
        return new AssistantMessage($text, $metadata);
    }

    /**
     * Formatea el usage de Gemini al formato de Laragent
     */
    protected function formatUsage(array $usageMetadata): array
    {
        return [
            'prompt_tokens' => $usageMetadata['promptTokenCount'] ?? 0,
            'completion_tokens' => $usageMetadata['candidatesTokenCount'] ?? 0,
            'total_tokens' => $usageMetadata['totalTokenCount'] ?? 0,
        ];
    }

    /**
     * Lee una línea del stream
     */
    protected function readLine($stream): string
    {
        $line = '';
        while (!$stream->eof()) {
            $char = $stream->read(1);
            if ($char === "\n") {
                break;
            }
            $line .= $char;
        }
        return trim($line);
    }

    // ========== MÉTODOS DE TOOLS (Para futuro) ==========

    public function formatToolsForPayload(): array
    {
        // TODO: Implementar cuando necesites tools
        $tools = [];
        
        foreach ($this->getRegisteredTools() as $tool) {
            $tools[] = [
                'function_declarations' => [
                    [
                        'name' => $tool->getName(),
                        'description' => $tool->getDescription(),
                        'parameters' => [
                            'type' => 'object',
                            'properties' => $tool->getProperties(),
                            'required' => $tool->getRequired(),
                        ],
                    ]
                ]
            ];
        }
        
        return $tools;
    }

    public function toolCallsToMessage(array $toolCalls): array
    {
        // Devolver formato intermedio de LarAgent (NO formato Gemini directo)
        // preparePayload() lo convertirá al formato correcto de Gemini
        $content = [];
        
        foreach ($toolCalls as $toolCall) {
            $content[] = [
                'type' => 'function_call',
                'name' => $toolCall->getToolName(),
                'arguments' => $toolCall->getArguments(),
            ];
        }

        return [
            'role' => 'assistant',  // LarAgent usa 'assistant' como estándar
            'content' => $content,   // Usar 'content', no 'parts'
        ];
    }

    public function toolResultToMessage(ToolCallInterface $toolCall, mixed $result): array
    {
        // Devolver formato intermedio de LarAgent
        return [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'function_result',
                    'name' => $toolCall->getToolName(),
                    'content' => is_string($result) ? $result : json_encode($result),
                ],
            ],
        ];
    }

    public function formatImagesForPayload(?array $images = null): array
    {
        $formattedImages = [];

        foreach ($images as $url) {
            $formattedImages[] = $this->convertImageToPart($url);
        }

        return $formattedImages;
    }
}
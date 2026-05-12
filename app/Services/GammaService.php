<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class GammaService
{
    /**
     * Generar presentación desde cero (Sin plantilla)
     * Endpoint: https://developers.gamma.app/reference/generate-a-gamma
     */
    public static function generateFromScratch($prompt, $exportAs = 'pptx', $imageOptions = [], $textMode = 'generate', $numCards = 10)
    {
        try {
            $url = "https://public-api.gamma.app/v1.0/generations";

            // Preparar los datos del request
            $data = [
                'inputText' => $prompt,
                'textMode' => $textMode, // generate, condense, preserve
                'exportAs' => $exportAs,
                'numCards' => $numCards  // Número de diapositivas a generar
            ];

            // Agregar opciones de imagen si existen
            if (!empty($imageOptions)) {
                $data['imageOptions'] = $imageOptions;
            }

            // Agregar opciones de texto (idioma español latinoamericano)
            $data['textOptions'] = [
                'language' => 'es-419'
            ];

            // 🆕 Hacer la presentación pública para permitir embed
            $data['sharingOptions'] = [
                'externalAccess' => 'view'
            ];

            $data_string = json_encode($data);

            // Log de la petición
            Log::info('Gamma API Request (From Scratch)', [
                'url' => $url,
                'data' => $data
            ]);

            // Configurar cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-API-KEY: ' . env('GAMMA_API_KEY')
            ]);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);

            // Ejecutar request
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            // Log de la respuesta
            Log::info('Gamma API Response (From Scratch)', [
                'http_code' => $http_code,
                'curl_error' => $curl_error,
                'response_preview' => substr($response, 0, 500)
            ]);

            // Verificar errores de cURL
            if ($curl_error) {
                Log::error('Gamma cURL Error', ['error' => $curl_error]);
                return ['error' => 'Error de conexión con Gamma: ' . $curl_error];
            }

            // Decodificar respuesta
            $response_data = json_decode($response, true);

            // Validar que sea un array
            if (!is_array($response_data)) {
                Log::error('Gamma: Respuesta malformada', ['response' => $response]);
                return ['error' => 'Respuesta malformada de Gamma'];
            }

            // Verificar código HTTP
            if ($http_code >= 200 && $http_code < 300) {
                Log::info('Gamma: Generación exitosa', $response_data);
                return ['data' => $response_data];
            } else {
                $error_message = $response_data['error'] ?? $response_data['message'] ?? 'Error desconocido';
                Log::error('Gamma API Error', [
                    'http_code' => $http_code,
                    'error' => $error_message,
                    'response' => $response_data
                ]);
                return ['error' => $error_message];
            }

        } catch (\Exception $e) {
            Log::error('Gamma Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ['error' => 'Error interno del servidor: ' . $e->getMessage()];
        }
    }

    /**
     * Generar presentación desde una plantilla
     * 
     * @param string $prompt Contenido/descripción de la presentación
     * @param string $exportAs Formato de exportación (pptx, pdf, etc.)
     * @param string $gammaId ID del gamma template (requerido)
     * @param array $imageOptions Opciones de imagen (model, style)
     * @return array Respuesta de la API con 'data' o 'error'
     */
    public static function generateFromTemplate($prompt, $exportAs = 'pptx', $gammaId = 'g_9pmna820lth9290', $imageOptions = [])
    {
        try {
            $url = "https://public-api.gamma.app/v1.0/generations/from-template";

            // Preparar los datos del request (gammaId siempre es requerido)
            $data = [
                'prompt' => $prompt,
                'exportAs' => $exportAs,
                'gammaId' => $gammaId
            ];

            // Agregar opciones de imagen si existen
            if (!empty($imageOptions)) {
                $data['imageOptions'] = $imageOptions;
            }

            // 🆕 Hacer la presentación pública para permitir embed
            $data['sharingOptions'] = [
                'externalAccess' => 'view'
            ];

            $data_string = json_encode($data);

            // Log de la petición
            Log::info('Gamma API Request', [
                'url' => $url,
                'data' => $data
            ]);

            // Configurar cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-API-KEY: ' . env('GAMMA_API_KEY')
            ]);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutos timeout (las presentaciones pueden tardar)
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);

            // Ejecutar request
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            // Log de la respuesta
            Log::info('Gamma API Response', [
                'http_code' => $http_code,
                'curl_error' => $curl_error,
                'response_preview' => substr($response, 0, 500)
            ]);

            // Verificar errores de cURL
            if ($curl_error) {
                Log::error('Gamma cURL Error', ['error' => $curl_error]);
                return ['error' => 'Error de conexión con Gamma: ' . $curl_error];
            }

            // Decodificar respuesta
            $response_data = json_decode($response, true);

            // Validar que sea un array
            if (!is_array($response_data)) {
                Log::error('Gamma: Respuesta malformada', ['response' => $response]);
                return ['error' => 'Respuesta malformada de Gamma'];
            }

            // Verificar código HTTP
            if ($http_code >= 200 && $http_code < 300) {
                // Respuesta exitosa
                Log::info('Gamma: Generación exitosa', $response_data);
                return ['data' => $response_data];
            } else {
                // Error en la respuesta
                $error_message = $response_data['error'] ?? $response_data['message'] ?? 'Error desconocido';
                Log::error('Gamma API Error', [
                    'http_code' => $http_code,
                    'error' => $error_message,
                    'response' => $response_data
                ]);
                return ['error' => $error_message];
            }

        } catch (\Exception $e) {
            Log::error('Gamma Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ['error' => 'Error interno del servidor: ' . $e->getMessage()];
        }
    }

    /**
     * Verificar el estado de una generación
     * 
     * @param string $generationId ID de la generación a verificar
     * @return array Respuesta con 'data' o 'error'
     */
    public static function checkGenerationStatus($generationId)
    {
        try {
            $url = "https://public-api.gamma.app/v1.0/generations/{$generationId}";

            Log::info('Gamma Status Check - Iniciando request', [
                'generationId' => $generationId,
                'url' => $url
            ]);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'X-API-KEY: ' . env('GAMMA_API_KEY')
            ]);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Timeout más generoso para polling
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            $curl_errno = curl_errno($ch);
            curl_close($ch);

            // Verificar errores de cURL primero
            if ($curl_error || $curl_errno) {
                Log::warning('Gamma Status Check - Error cURL (se reintentará)', [
                    'generationId' => $generationId,
                    'curl_error' => $curl_error,
                    'curl_errno' => $curl_errno,
                    'http_code' => $http_code
                ]);
                // Retornar como pendiente para que el frontend reintente
                return ['data' => ['status' => 'pending', 'message' => 'Timeout/Error de conexión, reintentando...']];
            }

            // Verificar respuesta vacía
            if (empty($response)) {
                Log::warning('Gamma Status Check - Respuesta vacía (se reintentará)', [
                    'generationId' => $generationId,
                    'http_code' => $http_code
                ]);
                return ['data' => ['status' => 'pending', 'message' => 'Respuesta vacía, reintentando...']];
            }

            // Log detallado de la respuesta
            Log::info('Gamma Status Check - Respuesta recibida', [
                'generationId' => $generationId,
                'http_code' => $http_code,
                'response_length' => strlen($response),
                'response_preview' => substr($response, 0, 500)
            ]);

            $response_data = json_decode($response, true);

            // Verificar JSON válido
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Gamma Status Check - JSON inválido (se reintentará)', [
                    'generationId' => $generationId,
                    'json_error' => json_last_error_msg(),
                    'response_preview' => substr($response, 0, 200)
                ]);
                return ['data' => ['status' => 'pending', 'message' => 'Respuesta malformada, reintentando...']];
            }

            // Log del JSON parseado
            Log::info('Gamma Status Check - JSON parseado', [
                'generationId' => $generationId,
                'status' => $response_data['status'] ?? 'no-status',
                'has_gammaUrl' => isset($response_data['gammaUrl']),
                'has_exportUrl' => isset($response_data['exportUrl'])
            ]);

            // Éxito (2xx)
            if ($http_code >= 200 && $http_code < 300) {
                Log::info('Gamma Status Check - Éxito', [
                    'generationId' => $generationId,
                    'status' => $response_data['status'] ?? 'unknown'
                ]);
                return ['data' => $response_data];
            }
            
            // Errores de servidor (5xx) - tratar como pendiente para reintentar
            if ($http_code >= 500) {
                Log::warning('Gamma Status Check - Error de servidor (se reintentará)', [
                    'generationId' => $generationId,
                    'http_code' => $http_code,
                    'response' => $response_data
                ]);
                return ['data' => ['status' => 'pending', 'message' => "Error HTTP {$http_code}, reintentando..."]];
            }
            
            // Rate limiting (429) - tratar como pendiente para reintentar
            if ($http_code === 429) {
                Log::warning('Gamma Status Check - Rate limiting (se reintentará)', [
                    'generationId' => $generationId
                ]);
                return ['data' => ['status' => 'pending', 'message' => 'Rate limited, esperando...']];
            }

            // Otros errores (4xx excepto 429)
            $error_message = $response_data['error'] ?? $response_data['message'] ?? "Error HTTP {$http_code}";
            Log::error('Gamma Status Check Error', [
                'generation_id' => $generationId,
                'http_code' => $http_code,
                'error' => $error_message,
                'response' => $response_data
            ]);
            return ['error' => $error_message];

        } catch (\Exception $e) {
            Log::error('Gamma Status Check Exception', [
                'generationId' => $generationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // En caso de excepción, tratar como pendiente para reintentar
            return ['data' => ['status' => 'pending', 'message' => 'Excepción: ' . $e->getMessage()]];
        }
    }

    /**
     * Generar presentación con documento Genesis como contexto
     * 
     * @param string $userPrompt Prompt del usuario
     * @param string $genesisContent Contenido del documento Genesis
     * @param string $exportAs Formato de exportación
     * @param string $themeId Tema a usar
     * @return array Respuesta con 'data' o 'error'
     */
    public static function generateWithGenesis($userPrompt, $genesisContent, $exportAs = 'pptx')
    {
        try {
            // Combinar el prompt del usuario con el contenido de Genesis
            $fullPrompt = "Crear una presentación sobre: {$userPrompt}\n\n";
            $fullPrompt .= "Basándote en el siguiente contenido estratégico:\n\n";
            $fullPrompt .= $genesisContent;

            Log::info('Gamma: Generando con Genesis', [
                'prompt_length' => strlen($fullPrompt),
                'user_prompt' => $userPrompt
            ]);

            return self::generateFromTemplate($fullPrompt, $exportAs);

        } catch (\Exception $e) {
            Log::error('Gamma Genesis Generation Exception', [
                'error' => $e->getMessage()
            ]);
            return ['error' => 'Error al generar con Genesis: ' . $e->getMessage()];
        }
    }
}

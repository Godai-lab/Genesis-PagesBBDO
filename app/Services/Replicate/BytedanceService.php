<?php

namespace App\Services\Replicate;

use Illuminate\Support\Facades\Log;

/**
 * BytedanceService - Servicio para modelos de Bytedance en Replicate
 * 
 * Maneja modelos de Bytedance disponibles en Replicate:
 * - bytedance/seedream-4.5 (generación y edición de imágenes)
 * 
 * Características de Seedream 4.5:
 * - Generación de imágenes de alta calidad
 * - Edición de imágenes con prompts
 * - Soporte para aspect ratios personalizados
 * - Comprensión espacial avanzada
 * 
 * @see https://replicate.com/bytedance/seedream-4.5/api
 */
class BytedanceService extends ReplicateBaseService
{
    /**
     * Mapeo de aspect ratios a dimensiones en píxeles
     * Seedream usa size="custom" con width/height personalizados
     * IMPORTANTE: El total de pixels debe ser >= 3,686,400 (aprox 1920x1920)
     */
    private const ASPECT_RATIO_DIMENSIONS = [
        '1:1' => ['width' => 1920, 'height' => 1920],   // 3,686,400 pixels ✓
        '16:9' => ['width' => 2560, 'height' => 1440],  // 3,686,400 pixels ✓
        '9:16' => ['width' => 1440, 'height' => 2560],  // 3,686,400 pixels ✓
        '4:3' => ['width' => 2240, 'height' => 1680],   // 3,763,200 pixels ✓
        '3:4' => ['width' => 1680, 'height' => 2240],   // 3,763,200 pixels ✓
    ];

    /**
     * Genera una imagen usando el modelo bytedance/seedream-4.5 en Replicate
     * 
     * @param string $prompt Texto descriptivo de la imagen a generar
     * @param string $aspectRatio Ratio de la imagen: "1:1", "16:9", "9:16", "4:3", "3:4"
     * @param int|null $seed Semilla para reproducibilidad (opcional)
     * 
     * @return array Respuesta con 'success', 'prediction_id' o 'error'
     */
    public static function generateImageSeedream(
        string $prompt,
        string $aspectRatio = "1:1",
        ?int $seed = null
    ): array {
        try {
            // Obtener dimensiones del aspect ratio
            $dimensions = self::ASPECT_RATIO_DIMENSIONS[$aspectRatio] ?? self::ASPECT_RATIO_DIMENSIONS['1:1'];
            
            // Construir el input
            // NOTA: No enviamos sequential_image_generation porque espera string, no boolean
            // y por defecto está deshabilitado
            $input = [
                'prompt' => $prompt,
                'size' => 'custom',  // Siempre custom para usar width/height
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
            ];

            // Seed opcional
            if ($seed !== null) {
                $input['seed'] = $seed;
            }

            $payload = ['input' => $input];

            Log::info('🎨 [BYTEDANCE/REPLICATE] Iniciando generación Seedream 4.5', [
                'prompt' => substr($prompt, 0, 100) . '...',
                'aspectRatio' => $aspectRatio,
                'width' => $dimensions['width'],
                'height' => $dimensions['height']
            ]);

            // Hacer la petición usando el helper de la clase base
            $result = self::makePostRequest('/models/bytedance/seedream-4.5/predictions', $payload);

            if (!$result['success']) {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Error desconocido'
                ];
            }

            $responseData = $result['response'];
            $predictionId = $responseData['id'] ?? null;
            $status = $responseData['status'] ?? 'unknown';

            Log::info('✅ [BYTEDANCE/REPLICATE] Predicción creada', [
                'predictionId' => $predictionId,
                'status' => $status
            ]);

            return [
                'success' => true,
                'prediction_id' => $predictionId,
                'status' => $status,
                'urls' => $responseData['urls'] ?? null
            ];

        } catch (\Exception $e) {
            Log::error('❌ [BYTEDANCE/REPLICATE] Excepción en generación', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Edita una imagen usando el modelo bytedance/seedream-4.5 en Replicate
     * 
     * @param string $prompt Texto descriptivo de la edición a realizar
     * @param string $imageUrl URL de la imagen a editar
     * @param float $imagePromptStrength Fuerza del prompt sobre la imagen (0.0 a 1.0)
     * @param bool $matchInputImage Si true, mantiene el aspect ratio de la imagen original
     * @param string $aspectRatio Ratio de la imagen (solo si matchInputImage es false)
     * @param int|null $seed Semilla para reproducibilidad (opcional)
     * 
     * @return array Respuesta con 'success', 'prediction_id' o 'error'
     */
    public static function editImageSeedream(
        string $prompt,
        string $imageUrl,
        float $imagePromptStrength = 0.5,
        bool $matchInputImage = true,
        string $aspectRatio = "1:1",
        ?int $seed = null
    ): array {
        try {
            // Construir el input base
            // NOTA: image_input es un ARRAY de URLs (1-14 imágenes)
            $input = [
                'prompt' => $prompt,
                'image_input' => [$imageUrl],  // Array con la imagen
                'image_prompt_strength' => $imagePromptStrength,
            ];

            // Si match_input_image está activo, usar el tamaño de la imagen original
            if ($matchInputImage) {
                $input['match_input_image'] = true;
            } else {
                // Si no, usar dimensiones personalizadas basadas en aspect ratio
                $dimensions = self::ASPECT_RATIO_DIMENSIONS[$aspectRatio] ?? self::ASPECT_RATIO_DIMENSIONS['1:1'];
                $input['size'] = 'custom';
                $input['width'] = $dimensions['width'];
                $input['height'] = $dimensions['height'];
            }

            // Seed opcional
            if ($seed !== null) {
                $input['seed'] = $seed;
            }

            $payload = ['input' => $input];

            Log::info('🎨 [BYTEDANCE/REPLICATE] Iniciando edición Seedream 4.5', [
                'prompt' => substr($prompt, 0, 100) . '...',
                'image_input' => [$imageUrl],
                'imagePromptStrength' => $imagePromptStrength,
                'matchInputImage' => $matchInputImage,
                'aspectRatio' => $aspectRatio
            ]);

            // Hacer la petición usando el helper de la clase base
            $result = self::makePostRequest('/models/bytedance/seedream-4.5/predictions', $payload);

            if (!$result['success']) {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Error desconocido'
                ];
            }

            $responseData = $result['response'];
            $predictionId = $responseData['id'] ?? null;
            $status = $responseData['status'] ?? 'unknown';

            Log::info('✅ [BYTEDANCE/REPLICATE] Predicción de edición creada', [
                'predictionId' => $predictionId,
                'status' => $status
            ]);

            return [
                'success' => true,
                'prediction_id' => $predictionId,
                'status' => $status,
                'urls' => $responseData['urls'] ?? null
            ];

        } catch (\Exception $e) {
            Log::error('❌ [BYTEDANCE/REPLICATE] Excepción en edición', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene las dimensiones para un aspect ratio dado
     * 
     * @param string $aspectRatio El ratio de aspecto
     * @return array Array con 'width' y 'height'
     */
    public static function getDimensionsForAspectRatio(string $aspectRatio): array
    {
        return self::ASPECT_RATIO_DIMENSIONS[$aspectRatio] ?? self::ASPECT_RATIO_DIMENSIONS['1:1'];
    }
}


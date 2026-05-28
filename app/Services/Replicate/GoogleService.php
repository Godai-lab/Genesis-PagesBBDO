<?php

namespace App\Services\Replicate;

use Illuminate\Support\Facades\Log;

/**
 * GoogleService - Servicio para modelos de Google en Replicate
 * 
 * Maneja modelos de Google disponibles en Replicate:
 * - google/veo-3.1 (y futuros modelos como nano, banana, etc.)
 * 
 * Características de Veo 3.1:
 * - Imagen de inicio (image)
 * - Imagen de fin (last_frame) 
 * - Imágenes de referencia (reference_images)
 * - Generación de audio
 * - Diferentes resoluciones
 * 
 * A diferencia de Gemini API, Replicate acepta URLs de imágenes en lugar de base64
 */
class GoogleService extends ReplicateBaseService
{
    /**
     * Genera un video usando el modelo google/veo-3.1 en Replicate
     * 
     * @param string $prompt Texto descriptivo del video a generar
     * @param string $aspectRatio Ratio del video: "16:9" o "9:16"
     * @param int $duration Duración en segundos: 4, 6 u 8
     * @param string $resolution Resolución: "720p" o "1080p"
     * @param bool $generateAudio Si debe generar audio
     * @param string|null $imageUrl URL de imagen de inicio
     * @param string|null $lastFrameUrl URL de imagen de fin
     * @param string|null $negativePrompt Prompt negativo
     * @param array $referenceImages Array de URLs de imágenes de referencia (1-3)
     * @param int|null $seed Semilla para reproducibilidad
     * 
     * @return array Respuesta con 'success', 'prediction_id' o 'error'
     */
    public static function generateVideoVeo31(
        string $prompt,
        string $aspectRatio = "16:9",
        int $duration = 8,
        string $resolution = "1080p",
        bool $generateAudio = true,
        ?string $imageUrl = null,
        ?string $lastFrameUrl = null,
        ?string $negativePrompt = null,
        array $referenceImages = [],
        ?int $seed = null
    ): array {
        try {
            // Construir el input base
            $input = [
                'prompt' => $prompt,
                'aspect_ratio' => $aspectRatio,
                'duration' => $duration,
                'resolution' => $resolution,
                'generate_audio' => $generateAudio,
            ];

            // ✅ Imagen de inicio (opcional)
            if ($imageUrl !== null) {
                $input['image'] = $imageUrl;
                Log::info('📷 [GOOGLE/REPLICATE] Agregando imagen de inicio', ['url' => $imageUrl]);
            }

            // ✅ Imagen de fin / last_frame (opcional)
            // Crea una transición entre la imagen de inicio y fin
            if ($lastFrameUrl !== null) {
                $input['last_frame'] = $lastFrameUrl;
                Log::info('📷 [GOOGLE/REPLICATE] Agregando imagen de fin (last_frame)', ['url' => $lastFrameUrl]);
            }

            // ✅ Prompt negativo (opcional)
            if ($negativePrompt !== null && !empty(trim($negativePrompt))) {
                $input['negative_prompt'] = $negativePrompt;
            }

            // ✅ Imágenes de referencia (opcional, 1-3 imágenes)
            // NOTA: Solo funcionan con aspect_ratio 16:9 y duration 8s
            // Si hay reference_images, se ignora last_frame
            if (!empty($referenceImages)) {
                // Limitar a máximo 3 imágenes
                $input['reference_images'] = array_slice($referenceImages, 0, 3);
                Log::info('📷 [GOOGLE/REPLICATE] Agregando imágenes de referencia', [
                    'count' => count($input['reference_images'])
                ]);
            }

            // ✅ Seed (opcional)
            if ($seed !== null) {
                $input['seed'] = $seed;
            }

            $payload = ['input' => $input];

            Log::info('🎬 [GOOGLE/REPLICATE] Iniciando generación Veo 3.1', [
                'prompt' => substr($prompt, 0, 100) . '...',
                'aspectRatio' => $aspectRatio,
                'duration' => $duration,
                'resolution' => $resolution,
                'generateAudio' => $generateAudio,
                'hasStartImage' => $imageUrl !== null,
                'hasLastFrame' => $lastFrameUrl !== null,
                'referenceImagesCount' => count($referenceImages)
            ]);

            // Hacer la petición usando el helper de la clase base
            $result = self::makePostRequest('/models/google/veo-3.1/predictions', $payload);

            if (!$result['success']) {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Error desconocido'
                ];
            }

            $responseData = $result['response'];
            $predictionId = $responseData['id'] ?? null;
            $status = $responseData['status'] ?? 'unknown';

            Log::info('✅ [GOOGLE/REPLICATE] Predicción creada', [
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
            Log::error('❌ [GOOGLE/REPLICATE] Excepción', [
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
     * Genera o edita una imagen usando google/nano-banana-pro en Replicate
     * 
     * Si se proporciona imageUrl, es edición (usa image_input)
     * Si no se proporciona imageUrl, es generación
     * 
     * @param string $prompt Texto descriptivo o instrucción de edición
     * @param string $aspectRatio Ratio: "1:1", "16:9", "9:16", "4:3", "3:4" o "match_input_image" (para edición)
     * @param string $resolution Resolución: "1K", "2K", "4K"
     * @param string|null $imageUrl URL de la imagen a editar (null para generación)
     * @param int|null $seed Semilla para reproducibilidad (opcional)
     * 
     * @return array Respuesta con 'success', 'prediction_id' o 'error'
     */
    public static function generateOrEditNanoBananaPro(
        string $prompt,
        string $aspectRatio = "1:1",
        string $resolution = "2K",
        ?string $imageUrl = null,
        ?int $seed = null
    ): array {
        try {
            $isEditing = !empty($imageUrl);
            
            $input = [
                'prompt' => $prompt,
                'aspect_ratio' => $isEditing ? 'match_input_image' : $aspectRatio,
                'resolution' => $resolution,
                'output_format' => 'png',
            ];

            // Si hay imagen, es edición
            if ($isEditing) {
                $input['image_input'] = [$imageUrl];
            }

            // Seed opcional
            if ($seed !== null) {
                $input['seed'] = $seed;
            }

            $payload = ['input' => $input];

            $action = $isEditing ? 'edición' : 'generación';
            Log::info("🎨 [GOOGLE/REPLICATE] Iniciando {$action} Nano Banana Pro", [
                'prompt' => substr($prompt, 0, 100) . '...',
                'aspect_ratio' => $input['aspect_ratio'],
                'resolution' => $resolution,
                'hasImage' => $isEditing,
                'image_input' => $isEditing ? [$imageUrl] : null
            ]);

            $result = self::makePostRequest('/models/google/nano-banana-pro/predictions', $payload);

            if (!$result['success']) {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Error desconocido'
                ];
            }

            $responseData = $result['response'];
            $predictionId = $responseData['id'] ?? null;
            $status = $responseData['status'] ?? 'unknown';

            Log::info("✅ [GOOGLE/REPLICATE] {$action} iniciada Nano Banana Pro", [
                'predictionId' => $predictionId,
                'status' => $status
            ]);

            return [
                'success' => true,
                'prediction_id' => $predictionId,
                'status' => $status,
                'raw' => $responseData
            ];

        } catch (\Exception $e) {
            Log::error('❌ [GOOGLE/REPLICATE] Error en Nano Banana Pro', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Genera una imagen usando google/imagen-4-ultra en Replicate
     * 
     * NOTA: Este modelo NO soporta edición de imágenes, solo generación
     * 
     * @param string $prompt Texto descriptivo de la imagen a generar
     * @param string $aspectRatio Ratio: "1:1", "16:9", "9:16", "4:3", "3:4"
     * @param string $safetyFilterLevel Nivel de filtro: "block_low_and_above", "block_medium_and_above", "block_only_high"
     * @param int|null $seed Semilla para reproducibilidad (opcional)
     * 
     * @return array Respuesta con 'success', 'prediction_id' o 'error'
     * @see https://replicate.com/google/imagen-4-ultra
     */
    public static function generateImagen4Ultra(
        string $prompt,
        string $aspectRatio = "1:1",
        string $safetyFilterLevel = "block_only_high",
        ?int $seed = null
    ): array {
        try {
            $input = [
                'prompt' => $prompt,
                'aspect_ratio' => $aspectRatio,
                'output_format' => 'png', // Siempre PNG
                'safety_filter_level' => $safetyFilterLevel,
            ];

            // Seed opcional
            if ($seed !== null) {
                $input['seed'] = $seed;
            }

            $payload = ['input' => $input];

            Log::info("🎨 [GOOGLE/REPLICATE] Iniciando generación Imagen 4 Ultra", [
                'prompt' => substr($prompt, 0, 100) . '...',
                'aspect_ratio' => $aspectRatio,
                'safety_filter_level' => $safetyFilterLevel
            ]);

            $result = self::makePostRequest('/models/google/imagen-4-ultra/predictions', $payload);

            if (!$result['success']) {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Error desconocido'
                ];
            }

            $responseData = $result['response'];
            $predictionId = $responseData['id'] ?? null;
            $status = $responseData['status'] ?? 'unknown';

            Log::info("✅ [GOOGLE/REPLICATE] Generación iniciada Imagen 4 Ultra", [
                'predictionId' => $predictionId,
                'status' => $status
            ]);

            return [
                'success' => true,
                'prediction_id' => $predictionId,
                'status' => $status,
                'raw' => $responseData
            ];

        } catch (\Exception $e) {
            Log::error('❌ [GOOGLE/REPLICATE] Error en Imagen 4 Ultra', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}


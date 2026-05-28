<?php

namespace App\Services\Replicate;

use Illuminate\Support\Facades\Log;

/**
 * KwaivgiService - Servicio para modelos de kwaivgi en Replicate
 * 
 * Maneja modelos de kwaivgi disponibles en Replicate:
 * - kwaivgi/kling-v2.5-turbo-pro
 * 
 * Características de Kling v2.5 Turbo Pro:
 * - Text-to-video
 * - Image-to-video (start_image)
 * - Múltiples duraciones (5s, 10s)
 * - Múltiples aspect ratios (16:9, 9:16, 1:1)
 * - Negative prompt
 * 
 * @see https://replicate.com/kwaivgi/kling-v2.5-turbo-pro/api
 */
class KwaivgiService extends ReplicateBaseService
{
    /**
     * Genera un video usando el modelo kwaivgi/kling-v2.5-turbo-pro en Replicate
     * 
     * @param string $prompt Texto descriptivo del video a generar
     * @param string $aspectRatio Ratio del video: "16:9", "9:16" o "1:1"
     * @param int $duration Duración en segundos: 5 o 10
     * @param string|null $startImageUrl URL de imagen de inicio (opcional)
     * @param string|null $negativePrompt Prompt negativo (opcional)
     * 
     * @return array Respuesta con 'success', 'prediction_id' o 'error'
     */
    public static function generateVideoKling(
        string $prompt,
        string $aspectRatio = "16:9",
        int $duration = 5,
        ?string $startImageUrl = null,
        ?string $negativePrompt = null
    ): array {
        try {
            // Validar duración (solo 5 o 10 segundos)
            if (!in_array($duration, [5, 10])) {
                $duration = 5; // Por defecto 5 segundos
                Log::warning('⚠️ [KWAIVGI/REPLICATE] Duración inválida, usando 5s por defecto');
            }

            // Validar aspect ratio
            if (!in_array($aspectRatio, ['16:9', '9:16', '1:1'])) {
                $aspectRatio = '16:9'; // Por defecto 16:9
                Log::warning('⚠️ [KWAIVGI/REPLICATE] Aspect ratio inválido, usando 16:9 por defecto');
            }

            // Construir el input base
            $input = [
                'prompt' => $prompt,
                'aspect_ratio' => $aspectRatio,
                'duration' => $duration,
            ];

            // ✅ Imagen de inicio (opcional)
            // NOTA: Si se proporciona start_image, el aspect_ratio se ignora
            if ($startImageUrl !== null) {
                $input['start_image'] = $startImageUrl;
                Log::info('📷 [KWAIVGI/REPLICATE] Agregando imagen de inicio', ['url' => $startImageUrl]);
            }

            // ✅ Prompt negativo (opcional)
            if ($negativePrompt !== null && !empty(trim($negativePrompt))) {
                $input['negative_prompt'] = $negativePrompt;
            }

            $payload = ['input' => $input];

            Log::info('🎬 [KWAIVGI/REPLICATE] Iniciando generación Kling v2.5 Turbo Pro', [
                'prompt' => substr($prompt, 0, 100) . '...',
                'aspectRatio' => $aspectRatio,
                'duration' => $duration,
                'hasStartImage' => $startImageUrl !== null,
                'hasNegativePrompt' => $negativePrompt !== null
            ]);

            // Hacer la petición usando el helper de la clase base
            $result = self::makePostRequest('/models/kwaivgi/kling-v2.5-turbo-pro/predictions', $payload);

            if (!$result['success']) {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Error desconocido'
                ];
            }

            $responseData = $result['response'];
            $predictionId = $responseData['id'] ?? null;
            $status = $responseData['status'] ?? 'unknown';

            Log::info('✅ [KWAIVGI/REPLICATE] Predicción creada', [
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
            Log::error('❌ [KWAIVGI/REPLICATE] Excepción', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}


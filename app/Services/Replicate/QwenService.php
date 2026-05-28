<?php

namespace App\Services\Replicate;

use Illuminate\Support\Facades\Log;

/**
 * QwenService - Servicio para modelos de Qwen en Replicate
 * 
 * Maneja modelos de Qwen disponibles en Replicate:
 * - qwen/qwen-image (generación de imágenes)
 * - qwen/qwen-image-edit (edición de imágenes)
 * 
 * Características de Qwen-Image:
 * - Excelente renderizado de texto en imágenes (especialmente chino)
 * - Generación de imágenes de alta calidad
 * - Edición precisa de texto en imágenes
 * - Múltiples estilos artísticos
 * 
 * Precio: $0.025 por imagen de salida
 * 
 * @see https://replicate.com/qwen/qwen-image
 * @see https://replicate.com/qwen/qwen-image-edit
 */
class QwenService extends ReplicateBaseService
{
    /**
     * Aspect ratios válidos para Qwen-Image
     * Qwen acepta el aspect_ratio directamente como string
     */
    private const VALID_ASPECT_RATIOS = ['1:1', '16:9', '9:16', '4:3', '3:4', '3:2', '2:3'];

    /**
     * Genera una imagen usando el modelo qwen/qwen-image en Replicate
     * 
     * @param string $prompt Texto descriptivo de la imagen a generar
     * @param string $aspectRatio Ratio de la imagen: "1:1", "16:9", "9:16", "4:3", "3:4", "3:2", "2:3"
     * @param int $guidance Escala de guía (default: 4)
     * @param int $numInferenceSteps Pasos de inferencia (default: 50)
     * @param int|null $seed Semilla para reproducibilidad (opcional)
     * 
     * @return array Respuesta con 'success', 'prediction_id' o 'error'
     */
    public static function generateImageQwen(
        string $prompt,
        string $aspectRatio = "1:1",
        int $guidance = 4,
        int $numInferenceSteps = 50,
        ?int $seed = null
    ): array {
        try {
            // Validar aspect ratio
            $validRatio = in_array($aspectRatio, self::VALID_ASPECT_RATIOS) ? $aspectRatio : '1:1';
            
            // Construir el input - Qwen acepta aspect_ratio directamente
            $input = [
                'prompt' => $prompt,
                'aspect_ratio' => $validRatio,
                'guidance' => $guidance,
                'num_inference_steps' => $numInferenceSteps,
                'output_format' => 'png',  // Siempre PNG
            ];

            // Seed opcional
            if ($seed !== null) {
                $input['seed'] = $seed;
            }

            $payload = ['input' => $input];

            Log::info('🎨 [QWEN/REPLICATE] Iniciando generación Qwen-Image', [
                'prompt' => substr($prompt, 0, 100) . '...',
                'aspect_ratio' => $validRatio,
                'guidance' => $guidance,
                'steps' => $numInferenceSteps,
                'output_format' => 'png'
            ]);

            // Hacer la petición usando el helper de la clase base
            $result = self::makePostRequest('/models/qwen/qwen-image/predictions', $payload);

            if (!$result['success']) {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Error desconocido'
                ];
            }

            $responseData = $result['response'];
            $predictionId = $responseData['id'] ?? null;
            $status = $responseData['status'] ?? 'unknown';

            Log::info('✅ [QWEN/REPLICATE] Predicción creada', [
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
            Log::error('❌ [QWEN/REPLICATE] Error generando imagen', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Edita una imagen usando el modelo qwen/qwen-image-edit en Replicate
     * Modelo especializado en edición con excelente renderizado de texto
     * 
     * @param string $prompt Instrucción de edición
     * @param string $imageUrl URL de la imagen a editar
     * @param string $aspectRatio Ratio de la imagen de salida: "1:1", "16:9", "9:16", etc.
     * @param int $guidance Escala de guía (default: 4)
     * @param int $numInferenceSteps Pasos de inferencia (default: 50)
     * 
     * @return array Respuesta con 'success', 'prediction_id' o 'error'
     * 
     * @see https://replicate.com/qwen/qwen-image-edit
     */
    public static function editImageQwen(
        string $prompt,
        string $imageUrl,
        string $aspectRatio = "1:1",
        int $guidance = 4,
        int $numInferenceSteps = 50
    ): array {
        try {
            // Validar aspect ratio
            $validRatio = in_array($aspectRatio, self::VALID_ASPECT_RATIOS) ? $aspectRatio : '1:1';
            
            // Construir el input para edición
            $input = [
                'prompt' => $prompt,
                'image' => $imageUrl,  // Imagen a editar
                'aspect_ratio' => $validRatio,
                'guidance' => $guidance,
                'num_inference_steps' => $numInferenceSteps,
                'output_format' => 'png',  // Siempre PNG
            ];

            $payload = ['input' => $input];

            Log::info('🎨 [QWEN/REPLICATE] Iniciando edición Qwen-Image-Edit', [
                'prompt' => substr($prompt, 0, 100) . '...',
                'imageUrl' => $imageUrl,
                'aspect_ratio' => $validRatio,
                'output_format' => 'png'
            ]);

            // Hacer la petición al modelo de EDICIÓN específico
            $result = self::makePostRequest('/models/qwen/qwen-image-edit/predictions', $payload);

            if (!$result['success']) {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Error desconocido'
                ];
            }

            $responseData = $result['response'];
            $predictionId = $responseData['id'] ?? null;
            $status = $responseData['status'] ?? 'unknown';

            Log::info('✅ [QWEN/REPLICATE] Edición iniciada', [
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
            Log::error('❌ [QWEN/REPLICATE] Error editando imagen', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}


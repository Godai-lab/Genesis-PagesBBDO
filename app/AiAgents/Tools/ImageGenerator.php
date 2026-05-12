<?php

namespace App\AiAgents\Tools;

use LarAgent\Tool;
use App\Services\GeminiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Herramienta extensible para generación de imágenes con IA
 * 
 * Esta herramienta permite a los agentes generar imágenes usando diferentes servicios:
 * - Gemini Imagen4 (por defecto)
 * - FLUX (próximamente)
 * - Leonardo AI (próximamente)
 * - Etc.
 * 
 * El servicio se puede cambiar dinámicamente sin modificar el código del agente
 */
class ImageGenerator extends Tool
{
    protected string $name = 'generate_image';

    protected string $description = 'Genera imágenes a partir de un prompt de texto usando IA. Puedes crear imágenes realistas, artísticas, ilustraciones, etc..';

    protected array $properties = [
        'prompt' => [
            'type' => 'string',
            'description' => 'Descripción detallada de la imagen a generar. Incluye detalles sobre estilo, composición, colores, ambiente, etc. Puede ser en español o inglés.',
        ],
        'aspect_ratio' => [
            'type' => 'string',
            'description' => 'Relación de aspecto de la imagen. Valores posibles: "1:1" (cuadrado), "16:9" (horizontal), "9:16" (vertical), "4:3", "3:4"',
            'enum' => ['1:1', '16:9', '9:16', '4:3', '3:4']
        ],
        'number_of_images' => [
            'type' => 'integer',
            'description' => 'Número de imágenes a generar (entre 1 y 4). Por defecto: 1',
            'minimum' => 1,
            'maximum' => 4
        ],
        'service' => [
            'type' => 'string',
            'description' => 'Servicio de generación a usar. Por defecto: "gemini". Opciones: "gemini" (Imagen4), "flux" (próximamente), "leonardo" (próximamente)',
            'enum' => ['gemini', 'flux', 'leonardo']
        ]
    ];

    protected array $required = ['prompt'];

    protected array $metaData = [
        'version' => '1.0.0',
        'author' => 'Godai Genesis',
        'created_at' => '2025-01-01'
    ];

    /**
     * Ejecuta la generación de imágenes
     * 
     * @param array $input Parámetros de entrada
     * @return array Resultado con URLs de imágenes o error
     */
    public function execute(array $input): mixed
    {
        try {
            // Extraer parámetros con valores por defecto
            $prompt = $input['prompt'] ?? '';
            $aspectRatio = $input['aspect_ratio'] ?? '1:1';
            $numberOfImages = $input['number_of_images'] ?? 1;
            $service = $input['service'] ?? 'gemini';

            // Validar prompt
            if (empty(trim($prompt))) {
                return 'Error: El prompt no puede estar vacío. Por favor, proporciona una descripción de la imagen que deseas generar.';
            }

            // Validar número de imágenes
            $numberOfImages = max(1, min(4, (int) $numberOfImages));

            Log::info('🎨 Iniciando generación de imágenes', [
                'service' => $service,
                'prompt' => substr($prompt, 0, 100),
                'aspect_ratio' => $aspectRatio,
                'number_of_images' => $numberOfImages,
                'agent' => $this->agent->provider ?? 'unknown'
            ]);

            // Resolver el servicio a usar
            $result = $this->resolveService($service, $prompt, $aspectRatio, $numberOfImages);

            // Si hubo error, retornar inmediatamente como string
            if (!$result['success']) {
                $errorResponse = $this->errorResponse($result['error']);
                Log::warning('⚠️ Error en generación de imágenes', [
                    'service' => $service,
                    'error' => $result['error'],
                    'response_type' => gettype($errorResponse)
                ]);
                return $errorResponse;
            }

            // Procesar y guardar las imágenes
            $savedImages = $this->saveImagesToStorage($result['images'], $prompt);

            Log::info('✅ Imágenes generadas exitosamente', [
                'service' => $service,
                'count' => count($savedImages),
                'urls' => array_column($savedImages, 'url')
            ]);

            $response = $this->successResponse($savedImages, $service, $prompt, $aspectRatio);
            
            Log::info('📤 Retornando respuesta de herramienta', [
                'response_length' => strlen($response),
                'response_preview' => substr($response, 0, 200),
                'response_type' => gettype($response)
            ]);
            
            return $response;

        } catch (\Exception $e) {
            Log::error('❌ Error generando imágenes:', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse('Error al generar imágenes: ' . $e->getMessage());
        }
    }

    /**
     * Resuelve qué servicio usar y ejecuta la generación
     * 
     * @param string $service Nombre del servicio
     * @param string $prompt Prompt de generación
     * @param string $aspectRatio Relación de aspecto
     * @param int $numberOfImages Número de imágenes
     * @return array Resultado con imágenes o error
     */
    private function resolveService(string $service, string $prompt, string $aspectRatio, int $numberOfImages): array
    {
        return match ($service) {
            'gemini' => $this->generateWithGemini($prompt, $aspectRatio, $numberOfImages),
            'flux' => $this->generateWithFlux($prompt, $aspectRatio, $numberOfImages),
            'leonardo' => $this->generateWithLeonardo($prompt, $aspectRatio, $numberOfImages),
            default => [
                'success' => false,
                'error' => "Servicio '{$service}' no soportado. Usa: gemini, flux, leonardo"
            ]
        };
    }

    /**
     * Genera imágenes con Gemini Imagen4
     * 
     * @param string $prompt Prompt de generación
     * @param string $aspectRatio Relación de aspecto
     * @param int $numberOfImages Número de imágenes
     * @return array Resultado con imágenes en base64 o error
     */
    private function generateWithGemini(string $prompt, string $aspectRatio, int $numberOfImages): array
    {
        try {
            Log::info('🔮 Generando con Gemini Imagen4');

            // Llamar al servicio Gemini
            $result = GeminiService::generateImage(
                prompt: $prompt,
                model: 'imagen-4.0-fast-generate-001',
                numberOfImages: $numberOfImages,
                aspectRatio: $aspectRatio,
                personGeneration: 'ALLOW_ADULT'
            );

            if (!$result['success']) {
                return [
                    'success' => false,
                    'error' => $result['error']['message'] ?? 'Error desconocido en Gemini'
                ];
            }

            // Extraer imágenes del resultado
            $images = [];
            foreach ($result['data'] as $index => $prediction) {
                if (isset($prediction['bytesBase64Encoded'])) {
                    $images[] = [
                        'base64' => $prediction['bytesBase64Encoded'],
                        'mimeType' => $prediction['mimeType'] ?? 'image/png',
                        'index' => $index
                    ];
                }
            }

            if (empty($images)) {
                return [
                    'success' => false,
                    'error' => 'No se recibieron imágenes de Gemini'
                ];
            }

            return [
                'success' => true,
                'images' => $images
            ];

        } catch (\Exception $e) {
            Log::error('❌ Error en generateWithGemini:', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Genera imágenes con FLUX (próximamente)
     * 
     * @param string $prompt Prompt de generación
     * @param string $aspectRatio Relación de aspecto
     * @param int $numberOfImages Número de imágenes
     * @return array Resultado con imágenes o error
     */
    private function generateWithFlux(string $prompt, string $aspectRatio, int $numberOfImages): array
    {
        // TODO: Implementar integración con FLUX
        // Ejemplo de cómo se vería:
        // 
        // $result = FluxService::generateImage($prompt, $aspectRatio, $numberOfImages);
        // return [
        //     'success' => $result['success'],
        //     'images' => $result['images'], // formato: [['base64' => '...', 'mimeType' => 'image/png']]
        //     'error' => $result['error'] ?? null
        // ];

        return [
            'success' => false,
            'error' => 'FLUX aún no está implementado. Usa "gemini" por ahora.'
        ];
    }

    /**
     * Genera imágenes con Leonardo AI (próximamente)
     * 
     * @param string $prompt Prompt de generación
     * @param string $aspectRatio Relación de aspecto
     * @param int $numberOfImages Número de imágenes
     * @return array Resultado con imágenes o error
     */
    private function generateWithLeonardo(string $prompt, string $aspectRatio, int $numberOfImages): array
    {
        // TODO: Implementar integración con Leonardo AI
        // Ejemplo de cómo se vería:
        //
        // $result = LeonardoService::generateImage($prompt, $aspectRatio, $numberOfImages);
        // return [
        //     'success' => $result['success'],
        //     'images' => $result['images'], // formato: [['base64' => '...', 'mimeType' => 'image/png']]
        //     'error' => $result['error'] ?? null
        // ];

        return [
            'success' => false,
            'error' => 'Leonardo AI aún no está implementado. Usa "gemini" por ahora.'
        ];
    }

    /**
     * Guarda las imágenes generadas en S3 y retorna las URLs públicas
     * 
     * @param array $images Array de imágenes en base64
     * @param string $prompt Prompt usado (para metadata)
     * @return array Array de imágenes con URLs públicas
     */
    private function saveImagesToStorage(array $images, string $prompt): array
    {
        $savedImages = [];

        foreach ($images as $index => $image) {
            try {
                // Decodificar base64
                $imageData = base64_decode($image['base64']);

                // Generar nombre único (misma ruta que el generador principal)
                $extension = $this->getExtensionFromMimeType($image['mimeType']);
                $fileName = 'genesis/output-images/' 
                    . now()->format('Ymd_His') 
                    . '_agent_' 
                    . uniqid('img_') 
                    . '.' . $extension;

                // Subir a S3 (mismo método que el generador principal)
                Storage::disk('s3')->put($fileName, $imageData);

                // Obtener URL pública
                $url = Storage::disk('s3')->url($fileName);

                $savedImages[] = [
                    'url' => $url,
                    'filename' => $fileName,
                    'size' => strlen($imageData),
                    'mimeType' => $image['mimeType'],
                    'index' => $index + 1
                ];

                Log::info('📤 Imagen guardada en S3', [
                    'filename' => $fileName,
                    'url' => $url,
                    'size' => strlen($imageData)
                ]);

            } catch (\Exception $e) {
                Log::error('❌ Error guardando imagen en S3:', [
                    'index' => $index,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $savedImages;
    }

    /**
     * Obtiene la extensión de archivo según el MIME type
     * 
     * @param string $mimeType Tipo MIME
     * @return string Extensión de archivo
     */
    private function getExtensionFromMimeType(string $mimeType): string
    {
        return match ($mimeType) {
            'image/png' => 'png',
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'png'
        };
    }

    /**
     * Genera respuesta de éxito con URLs embebidas
     * 
     * @param array $images Array de imágenes guardadas
     * @param string $service Servicio usado
     * @param string $prompt Prompt usado
     * @param string $aspectRatio Relación de aspecto
     * @return string Respuesta con URLs para el agente
     */
    private function successResponse(array $images, string $service, string $prompt, string $aspectRatio): string
    {
        $count = count($images);
        $urls = array_map(fn($img) => $img['url'], $images);
        
        $response = "✅ He generado exitosamente {$count} " . ($count === 1 ? 'imagen' : 'imágenes') . " usando {$service}.\n\n";
        $response .= "Las imágenes están listas y disponibles en las siguientes URLs:\n\n";
        
        foreach ($urls as $index => $url) {
            $response .= ($index + 1) . ". {$url}\n";
        }
        
        $response .= "\nPuedes compartir estas URLs con el usuario o mostrarlas directamente en el chat.";
        
        return $response;
    }

    /**
     * Genera respuesta de error como string (formato consistente con LarAgent)
     * 
     * @param string $error Mensaje de error
     * @return string Respuesta de error en formato legible
     */
    private function errorResponse(string $error): string
    {
        return "Error al generar imágenes: {$error}. Por favor, intenta nuevamente con un prompt diferente o verifica la configuración del servicio.";
    }
}


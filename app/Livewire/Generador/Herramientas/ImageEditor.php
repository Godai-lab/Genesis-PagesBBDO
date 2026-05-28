<?php

namespace App\Livewire\Generador\Herramientas;

use App\Http\Traits\ValidatesCreditLimit;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Reactive;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Services\OpenAiService;
use App\Services\GeminiService;
use App\Services\Replicate\BytedanceService;
use App\Services\Replicate\QwenService;
use App\Services\Replicate\GoogleService;
use App\Supports\CostCalculationService;

/**
 * Editor de Imágenes con IA
 *
 * Permite a los usuarios subir imágenes y editarlas mediante prompts
 * usando modelos como GPT y Flux-Kontext
 */
class ImageEditor extends Component
{
    use WithFileUploads;
    
    protected string $toolName = 'Editor de Imágenes';
    use ValidatesCreditLimit;
    
    /** ✅ Account ID recibido del componente padre - Reactive para sincronizar automáticamente */
    #[Reactive]
    public ?int $accountId = null;

    /** Texto del prompt para edición */
    #[Validate('required|string|min:3')]
    public string $promptText = '';

    /** Modelo de IA para edición */
    public string $model = 'gpt-image-1';

    /** Relación de aspecto */
    public string $ratio = '1:1';

    /** Cantidad de imágenes a generar */
    #[Validate('integer|min:1|max:4')]
    public int $count = 1;

    /** Estado de procesamiento */
    public bool $isProcessing = false;

    /** IDs de generaciones ya procesadas (para evitar duplicados) */
    private static array $processedGenerationIds = [];

    /** Resolución para Nano Banana Pro: "1K", "2K", "4K" */
    public string $resolutionNanoBanana = "2K";

    /** Imagen subida por el usuario */
    public $uploadedImage = null;

    /** URL de la imagen subida (después de guardarla) */
    public ?string $imageUrl = null;

    /** Imágenes temporales para manejar la carga */
    #[Validate('max:4', message: 'Máximo 4 imágenes permitidas')]
    public $temporaryImages = [];
    
    /** Imágenes procesadas */
    public $imageFiles = [];

    /** Resultados de edición */
    public array $results = [];
    
    /** Indicador si las imágenes vienen del historial */
    public bool $fromHistory = false;
    
    /** Metadata de la imagen del historial */
    public array $historyMetadata = [];

    /** Propiedades específicas de OpenAI */
    public string $calidadImagen = 'auto';

    public array $calidadesDisponibles = [
        'auto' => 'Automática',
        'high' => 'Alta',
        'medium' => 'Media',
        'low' => 'Baja'
    ];

    /** Catálogo de modelos disponibles para edición */
    public array $availableModels = [
        'gemini-3-pro-image-preview' => [
            'name' => 'Nano Banana Pro',
            'price' => '~$0.13',
            'priceUnit' => 'por edición (1K/2K, según tokens)',
            'description' => 'Modelo Gemini 3 Pro Image de Google. Cobro por token (entrada $2/1M, salida $120/1M)',
            'bestFor' => 'Editar imágenes con texto, infografías, contenido educativo, mockups',
            'speed' => 'Rápido',
            'quality' => 'Excelente'
        ],
        'gemini-3.1-flash-image-preview' => [
            'name' => 'Nano Banana 2',
            'price' => '~$0.07',
            'priceUnit' => 'por edición (1K, según tokens)',
            'description' => 'Modelo Gemini 3.1 Flash optimizado para velocidad. Cobro por token (entrada $0.25/1M, salida $60/1M)',
            'bestFor' => 'Edición rápida, iteraciones frecuentes, imágenes con texto y composición creativa',
            'speed' => 'Muy rápido',
            'quality' => 'Excelente'
        ],
        'gpt-image-1' => [
            'name' => 'ChatGPT',
            'price' => '$0.10',
            'priceUnit' => 'por edición',
            'description' => 'Editor de OpenAI para modificaciones precisas de imágenes',
            'bestFor' => 'Ediciones detalladas, modificaciones específicas, retoque profesional',
            'speed' => 'Medio',
            'quality' => 'Alta'
        ],
        'gpt-image-1.5' => [
            'name' => 'GPT Image 1.5',
            'price' => '$0.10',
            'priceUnit' => 'por edición',
            'description' => 'Editor GPT de OpenAI para ediciones de imágenes con mayor calidad y detalle',
            'bestFor' => 'Ediciones creativas y profesionales, modificaciones precisas, retoque avanzado',
            'speed' => 'Medio',
            'quality' => 'Excelente'
        ],
        'flux-kontext-max' => [
            'name' => 'Flux-Kontext-Max',
            'price' => '$0.08',
            'priceUnit' => 'por edición',
            'description' => 'Editor Flux de máxima calidad para transformaciones artísticas',
            'bestFor' => 'Transformaciones artísticas, cambios de estilo, ediciones creativas',
            'speed' => 'Medio',
            'quality' => 'Excelente'
        ],
        'flux-2-pro' => [
            'name' => 'Flux 2 Pro',
            'price' => '$0.03',
            'priceUnit' => 'por edición',
            'description' => 'FLUX.2 Pro con multi-referencia hasta 8 imágenes',
            'bestFor' => 'Edición fotorealista, composición multi-imagen',
            'speed' => 'Rápido',
            'quality' => 'Excepcional'
        ],
        'flux-kontext-pro' => [
            'name' => 'Flux-Kontext-Pro',
            'price' => '$0.04',
            'priceUnit' => 'por edición',
            'description' => 'Editor Flux equilibrado para uso profesional',
            'bestFor' => 'Ediciones generales, ajustes de contenido, modificaciones rápidas',
            'speed' => 'Rápido',
            'quality' => 'Muy buena'
        ],
        'seedream-4.5' => [
            'name' => 'Seedream 4.5',
            'price' => '$0.04',
            'priceUnit' => 'por edición',
            'description' => 'Editor de Bytedance con comprensión espacial avanzada',
            'bestFor' => 'Ediciones complejas, transformaciones artísticas, retoque creativo',
            'speed' => 'Rápido',
            'quality' => 'Excelente'
        ],
        'qwen-image' => [
            'name' => 'Qwen Image',
            'price' => '$0.03',
            'priceUnit' => 'por edición',
            'description' => 'Especializado en edición de texto en imágenes',
            'bestFor' => 'Editar texto en imágenes, posters, señalización, tipografía',
            'speed' => 'Medio',
            'quality' => 'Excelente'
        ]
    ];

    public array $availableRatios = [
        '1:1' => 'Cuadrado',
        '16:9' => 'Panorámico',
        '9:16' => 'Vertical móvil',
        '4:3' => 'Horizontal',
        '3:4' => 'Vertical',
    ];

    /**
     * Determina si el modelo actual soporta múltiples imágenes editadas
     */
    public function getSupportsMultipleImagesProperty(): bool
    {
        // Modelos que soportan múltiples imágenes en edición
        // OpenAI y Gemini soportan múltiples ediciones
        // Flux modelos solo procesan 1 imagen por request
        return in_array($this->model, [
            'gpt-image-1',
            'gpt-image-1.5' // OpenAI GPT image models soportan múltiples imágenes editadas
        ]);
    }

    /**
     * Obtiene el nombre amigable del modelo
     */
    private function getModelDisplayName($modelKey): string
    {
        return $this->availableModels[$modelKey]['name'] ?? $modelKey;
    }

    /**
     * Método helper para obtener solo los nombres de los modelos
     */
    public function getModelNamesAttribute(): array
    {
        return collect($this->availableModels)->mapWithKeys(function ($info, $key) {
            return [$key => $info['name']];
        })->toArray();
    }

    /**
     * Listener para cambio de modelo desde el selector (igual que ImageGenerator)
     */
    #[On('image-generator-model-selected')]
    public function updateModel($key)
    {
        $this->model = $key;
        
        // Si el nuevo modelo no soporta múltiples imágenes, resetear a 1
        if (!$this->supportsMultipleImages && $this->count > 1) {
            $this->count = 1;
        }
        
        Log::info('Modelo de edición cambiado a: ' . $key);
    }

    /**
     * Listener para cargar imagen desde el historial
     * Simplificado: reutiliza la misma lógica que las imágenes subidas
     */
    #[On('loadImageFromHistory')]
    public function loadImageFromHistory($imageUrl, $generationId, $originalModel, $originalRatio): void
    {
        try {
            Log::info('🖼️ Cargando imagen del historial para edición', [
                'imageUrl' => $imageUrl,
                'generationId' => $generationId,
                'originalModel' => $originalModel,
                'originalRatio' => $originalRatio
            ]);

            // Limpiar imágenes previas
            $this->clearImage();
            
            // ✅ SIMPLIFICACIÓN: Usar la misma lógica que las imágenes subidas
            // En lugar de crear un sistema paralelo, simulamos que es una imagen "subida"
            $this->imageUrl = $imageUrl;
            $this->fromHistory = true;
            $this->historyMetadata = [
                'imageUrl' => $imageUrl,
                'generationId' => $generationId,
                'originalModel' => $originalModel,
                'originalRatio' => $originalRatio
            ];
            
            // Configurar el ratio basado en la imagen original
            if ($originalRatio && isset($this->availableRatios[$originalRatio])) {
                $this->ratio = $originalRatio;
            }
            
            // Dispatch el mismo evento que las imágenes subidas para compatibilidad
            $this->dispatch('imageUploaded', url: $this->imageUrl);
            
            Log::info('✅ Imagen del historial cargada exitosamente');
            
        } catch (\Exception $e) {
            Log::error('❌ Error cargando imagen del historial: ' . $e->getMessage());
            
            $this->addError('temporaryImages', 'Error al cargar la imagen del historial.');
            
            $this->dispatch('addErrorToList', 
                message: 'Error al cargar imagen del historial: ' . $e->getMessage(), 
                type: 'system', 
                tool: 'image-editor'
            );
        }
    }

    /**
     * Observador para cuando se seleccionan nuevas imágenes temporales
     */
    public function updatedTemporaryImages()
    {
        if (empty($this->temporaryImages)) {
            return;
        }
        
        // 🔄 LIMPIAR IMAGEN DEL HISTORIAL si se suben imágenes manualmente
        if ($this->fromHistory) {
            Log::info('🧹 Limpiando imagen del historial al subir imagen manual');
            $this->fromHistory = false;
            $this->historyMetadata = [];
        }
        
        // Verificar límite total de imágenes (máximo 4)
        $totalImages = count($this->imageFiles) + count($this->temporaryImages);
        if ($totalImages > 4) {
            $this->addError('temporaryImages', 'Máximo 4 imágenes permitidas en total.');
            $this->temporaryImages = [];
            return;
        }
        
        // Si no hay imágenes previas, simplemente asignamos las nuevas
        if (empty($this->imageFiles)) {
            $this->imageFiles = $this->temporaryImages;
        } else {
            // Si ya hay imágenes, las combinamos con las nuevas
            foreach ($this->temporaryImages as $newImage) {
                $this->imageFiles[] = $newImage;
            }
        }
        
        // Actualizar imageUrl con la primera imagen para compatibilidad
        if (!empty($this->imageFiles)) {
            try {
                $this->imageUrl = $this->imageFiles[0]->temporaryUrl();
            } catch (\Exception $e) {
                Log::error('Error obteniendo URL temporal: ' . $e->getMessage());
            }
        }
        
        // Limpiamos las imágenes temporales
        $this->temporaryImages = [];
        
        // Dispatch evento para notificar que la imagen está lista
        $this->dispatch('imageUploaded', url: $this->imageUrl);
        
        Log::info('Imagen cargada exitosamente en ImageEditor');
    }

    /**
     * Método helper para obtener URL temporal de una imagen
     */
    public function getTemporaryUrl($image)
    {
        try {
            if ($image && method_exists($image, 'temporaryUrl')) {
                return $image->temporaryUrl();
            }
            return null;
        } catch (\Exception $e) {
            Log::error('Error al obtener URL temporal: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Método principal para iniciar edición (igual que generate() en ImageGenerator)
     */
    public function editImage(): void
    {
        $this->validate();
        
        // Validar que haya imágenes (subidas o del historial)
        if (empty($this->imageFiles) && !($this->fromHistory && $this->imageUrl)) {
            $errorMessage = 'Debes subir una imagen o seleccionar una del historial primero.';
            
            // Enviar error al componente principal (igual que en VideoGenerator)
            $this->dispatch('addErrorToList', 
                message: $errorMessage, 
                type: 'validation', 
                tool: 'image-editor'
            );
            
            return;
        }

        // 1. ACTIVAR INMEDIATAMENTE el spinner
        $this->isProcessing = true;
        $this->results = [];
        
        // 2. DISPARAR EVENTO para mostrar spinner en frontend
        $this->dispatch('editingStarted');
        
        // 3. DISPARAR EVENTO para iniciar edición REAL (con delay)
        $imagesCount = $this->fromHistory ? 1 : count($this->imageFiles);
        
        $this->dispatch('startImageEditing', [
            'prompt' => $this->promptText,
            'model' => $this->model,
            'count' => $this->count,
            'ratio' => $this->ratio,
            'images_count' => $imagesCount,
            'from_history' => $this->fromHistory,
            'history_metadata' => $this->historyMetadata
        ]);
    }

    // 4. MÉTODO QUE HACE LA EDICIÓN REAL
    #[On('startImageEditing')]
    public function executeEditing($data): void
    {
        try {
            Log::info('Ejecutando edición de imagen', [
                'model' => $data['model'],
                'prompt' => substr($data['prompt'], 0, 50) . '...',
                'ratio' => $data['ratio'],
                'images_count' => $data['images_count']
            ]);

            // ✅ Validar que haya una cuenta seleccionada
            if (!$this->accountId) {
                $errorMessage = 'Debes seleccionar una cuenta antes de editar imágenes';
                $this->addError('promptText', $errorMessage);
                
                $this->dispatch('addErrorToList', 
                    message: $errorMessage, 
                    type: 'validation', 
                    tool: 'image-editor'
                );
                
                $this->dispatch('generationError');
                $this->isGenerating = false;
                return;
            }

            // ✅ Validar límite de créditos usando la cuenta del componente
            $this->validateCreditLimit($this->accountId);

            // Procesar según el modelo seleccionado
            switch ($data['model']) {
                case 'gpt-image-1':
                case 'gpt-image-1.5':
                    $this->editarConOpenAI($data);
                    break;
                case 'flux-kontext-max':
                case 'flux-kontext-pro':
                    $this->editarConFluxKontext($data);
                    break;
                case 'flux-2-pro':
                    $this->editarConFlux2Pro($data);
                    break;
                case 'seedream-4.5':
                    $this->editarConSeedream($data);
                    break;
                case 'qwen-image':
                    $this->editarConQwen($data);
                    break;
                case 'gemini-3-pro-image-preview':
                case 'gemini-3.1-flash-image-preview':
                    $this->editarConGeminiContentImage($data);
                    break;
                default:
                    throw new \Exception("Modelo de edición no soportado: {$data['model']}");
            }

        } catch (\App\Exceptions\CreditLimitExceededException $e) {
            Log::warning('Límite de créditos excedido en Editor de Imágenes', [
                'message' => $e->getMessage(),
                'accountId' => $accountId ?? null
            ]);
            
            $this->addError('promptText', $e->getMessage());
            
            // Enviar error al componente principal
            $this->dispatch('addErrorToList', 
                message: $e->getMessage(), 
                type: 'system', 
                tool: 'image-editor'
            );
            
            $this->dispatch('editingError');
            $this->isProcessing = false;
        } catch (\Exception $e) {
            Log::error('Error en Editor de Imágenes', [
                'message' => $e->getMessage(),
                'accountId' => $accountId ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            
            $errorMessage = 'Ha ocurrido un error al editar la imagen. Por favor, intenta nuevamente.';
            $this->addError('promptText', $errorMessage);
            
            // Enviar error al componente principal
            $this->dispatch('addErrorToList', 
                message: $errorMessage, 
                type: 'system', 
                tool: 'image-editor'
            );
            
            $this->dispatch('editingError');
            $this->isProcessing = false;
        }
    }

    /**
     * Limpiar imagen subida
     */
    public function clearImage(): void
    {
        $this->uploadedImage = null;
        $this->imageUrl = null;
        $this->results = [];
        $this->imageFiles = [];
        $this->temporaryImages = [];
        
        // Limpiar datos del historial
        $this->fromHistory = false;
        $this->historyMetadata = [];
        
        $this->dispatch('imageCleared');
    }

    /**
     * Quita una imagen específica del array
     */
    public function quitarImagen($index)
    {
        if (isset($this->imageFiles[$index])) {
            // Crear un nuevo array sin la imagen eliminada
            $newFiles = [];
            foreach ($this->imageFiles as $i => $file) {
                if ($i != $index) {
                    $newFiles[] = $file;
                }
            }
            $this->imageFiles = $newFiles;
            
            // Actualizar imageUrl
            if (!empty($this->imageFiles)) {
                try {
                    $this->imageUrl = $this->imageFiles[0]->temporaryUrl();
                } catch (\Exception $e) {
                    $this->imageUrl = null;
                }
            } else {
                $this->imageUrl = null;
            }
        }
    }

    /**
     * Convierte la primera imagen a base64 para envío a APIs (OpenAI)
     * Simplificado: usa imageUrl para ambos casos
     */
    public function getImageAsBase64()
    {
        try {
            if ($this->fromHistory && $this->imageUrl) {
                // Para imágenes del historial, descargar desde S3 y convertir a base64
                Log::info('📥 Descargando imagen del historial para convertir a base64', [
                    'imageUrl' => $this->imageUrl
                ]);
                
                $imageContent = file_get_contents($this->imageUrl);
                if ($imageContent === false) {
                    throw new \Exception('No se pudo descargar la imagen del historial');
                }
                
                Log::info('✅ Imagen del historial convertida a base64', [
                    'imageSize' => strlen($imageContent)
                ]);
                
                return base64_encode($imageContent);
                
            } elseif (!empty($this->imageFiles)) {
                // Para imágenes subidas, leer desde el archivo temporal
                $image = $this->imageFiles[0]; // Tomar la primera imagen
                $imageContent = file_get_contents($image->getRealPath());
                return base64_encode($imageContent);
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::error('Error convirtiendo imagen a base64: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Sube múltiples imágenes a S3 y retorna las URLs para APIs (Flux)
     * Simplificado: usa imageUrl para imágenes del historial
     */
    public function uploadImagesToS3ForFlux()
    {
        try {
            $mainImageUrl = null;
            $additionalImageUrls = [];
            
            if ($this->fromHistory && $this->imageUrl) {
                // ✅ OPTIMIZACIÓN: Las imágenes del historial ya están en S3, reutilizarlas directamente
                Log::info('🚀 Reutilizando imagen del historial (ya en S3)', [
                    'imageUrl' => $this->imageUrl
                ]);
                
                $mainImageUrl = $this->imageUrl; // Imagen del historial va en input_image
                
                Log::info('✅ URL del historial preparada para Flux', [
                    'mainImage' => $mainImageUrl
                ]);
                
            } elseif (!empty($this->imageFiles)) {
                // Para imágenes subidas, subirlas a S3 como antes
                foreach ($this->imageFiles as $index => $image) {
                    $imageContent = file_get_contents($image->getRealPath());
                    
                    // Generar nombre de archivo único para imagen temporal
                    $fileName = 'genesis/temp-images/' . now()->format('Ymd_His') . '_' . uniqid('temp_' . $index . '_') . '.jpg';
                    
                    Log::info('☁️ Subiendo imagen temporal a S3 para Flux', [
                        'index' => $index,
                        'fileName' => $fileName,
                        'imageSize' => strlen($imageContent)
                    ]);
                    
                    // Subir a S3
                    Storage::disk('s3')->put($fileName, $imageContent);
                    
                    // Obtener la URL de S3
                    $url = Storage::disk('s3')->url($fileName);
                    
                    if ($index === 0) {
                        $mainImageUrl = $url; // Primera imagen va en input_image
                    } else {
                        $additionalImageUrls[] = $url; // Imágenes adicionales van en input_image_2, input_image_3, etc.
                    }
                }
                
                Log::info('✅ Imágenes temporales subidas exitosamente a S3', [
                    'mainImage' => $mainImageUrl,
                    'additionalCount' => count($additionalImageUrls)
                ]);
            }
            
            return [
                'main' => $mainImageUrl,
                'additional' => $additionalImageUrls
            ];

        } catch (\Exception $e) {
            Log::error('💥 Error preparando imágenes para Flux: ' . $e->getMessage());
            return ['main' => null, 'additional' => []];
        }
    }

    /**
     * Obtiene información de la imagen (tipo MIME, etc.)
     */
    public function getImageInfo()
    {
        if (empty($this->imageFiles)) {
            return null;
        }

        try {
            $image = $this->imageFiles[0];
            return [
                'mime_type' => $image->getMimeType(),
                'original_name' => $image->getClientOriginalName(),
                'size' => $image->getSize(),
                'extension' => $image->getClientOriginalExtension()
            ];
        } catch (\Exception $e) {
            Log::error('Error obteniendo información de imagen: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Edita imágenes usando OpenAI
     */
    private function editarConOpenAI($data): void
    {
        try {
            // Establecer tiempo de ejecución máximo
            set_time_limit(180); // 3 minutos
            
            // Mapear el aspect ratio a formato OpenAI
            $aspecto = $this->mapearAspectRatioAOpenAI($data['ratio']);
            $quality = $this->calidadImagen;
            
            Log::info('Iniciando edición con OpenAI', [
                'prompt' => $data['prompt'],
                'size' => $aspecto,
                'quality' => $quality,
                'count' => $data['count'],
                'images_count' => $data['images_count']
            ]);
            
            // Preparar rutas de imágenes para OpenAI
            $imagePaths = [];
            
            if ($this->fromHistory && $this->imageUrl) {
                // Para imágenes del historial, descargar temporalmente para OpenAI
                $tempFile = tempnam(sys_get_temp_dir(), 'history_image_');
                $imageContent = file_get_contents($this->imageUrl);
                if ($imageContent === false) {
                    throw new \Exception('No se pudo descargar imagen del historial para OpenAI');
                }
                file_put_contents($tempFile, $imageContent);
                $imagePaths[] = $tempFile;
                
            } elseif (!empty($this->imageFiles)) {
                // Para imágenes subidas, usar las rutas temporales
                foreach ($this->imageFiles as $image) {
                    $imagePaths[] = $image->getRealPath();
                }
            }
            
            // Llamar al servicio de edición de OpenAI (modelo dinámico y calidad seleccionada)
            $response = \App\Services\OpenAiService::editImage(
                $data['prompt'], 
                $imagePaths, 
                $data['model'], 
                $aspecto, 
                'auto', 
                $data['count'], // Usar el count del parámetro
                $quality
            );
            
            Log::info('Respuesta de OpenAI editImage:', $response);
            
            // Registrar el uso de tokens si está disponible (gpt-image-1 cobra por tokens)
            if (!isset($response['error']) && isset($response['usage'])) {
                $usage = $response['usage'];
                // ✅ Usar accountId del componente
                $userId = Auth::id();
                
                if ($userId) {
                    // Extraer tokens de la respuesta de OpenAI
                    $inputTokens = $usage['input_tokens'] ?? $usage['prompt_tokens'] ?? 0;
                    $outputTokens = $usage['output_tokens'] ?? $usage['completion_tokens'] ?? 0;
                    
                    // Contar imágenes realmente editadas
                    $imagesEdited = isset($response['data']) ? count($response['data']) : 0;
                    $imagesRequested = $data['count'] ?? 1;
                    
                    Log::info('📊 Tokens extraídos de OpenAI Image Edit', [
                        'account_id' => $this->accountId,
                        'user_id' => $userId,
                        'inputTokens' => $inputTokens,
                        'outputTokens' => $outputTokens,
                        'totalTokens' => $usage['total_tokens'] ?? ($inputTokens + $outputTokens),
                        'imagesRequested' => $imagesRequested,
                        'imagesEdited' => $imagesEdited,
                        'note' => 'Los tokens de OpenAI ya reflejan el costo total de todas las imágenes editadas',
                        'usage' => $usage
                    ]);
                    
                    // Registrar el uso solo si hay tokens
                    // IMPORTANTE: Los tokens de OpenAI ya incluyen el costo de todas las imágenes editadas
                    if ($inputTokens > 0 || $outputTokens > 0) {
                        try {
                            CostCalculationService::trackUsage(
                                $this->accountId,
                                $userId,
                                $data['model'], // Modelo usado (gpt-image-1, gpt-image-1.5, etc.)
                                [
                                    'tokens' => [
                                        'input' => $inputTokens,
                                        'output' => $outputTokens
                                    ]
                                ],
                                null, // usageDate (usa ahora)
                                'Image Editor', // request_type correcto
                                null, // external_request_id
                                null, // generated_id (no hay Generated para ediciones simples)
                                null, // step
                                'openai' // service_type
                            );
                            Log::info('✅ Uso registrado exitosamente para OpenAI Image Edit', [
                                'imagesEdited' => $imagesEdited,
                                'tokensUsed' => $inputTokens + $outputTokens,
                                'inputTokens' => $inputTokens,
                                'outputTokens' => $outputTokens
                            ]);
                        } catch (\Exception $e) {
                            Log::error('❌ Error al registrar uso de OpenAI Image Edit', [
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ]);
                        }
                    } else {
                        Log::warning('⚠️ No se encontraron tokens en usage de OpenAI Image Edit', [
                            'usage' => $usage,
                            'imagesEdited' => $imagesEdited
                        ]);
                    }
                } else {
                    Log::warning('⚠️ No se pudo obtener userId para registrar uso', [
                        'accountId' => $accountId,
                        'userId' => $userId
                    ]);
                }
            }
            
            if (isset($response['error'])) {
                $errorMessage = 'Error editando imagen con OpenAI: ' . $response['error'];
                $this->addError('promptText', $errorMessage);
                
                $this->dispatch('addErrorToList', 
                    message: $errorMessage, 
                    type: 'generation', 
                    tool: 'image-editor'
                );
                
                $this->dispatch('editingError');
                return;
            }
            
            // Procesar respuesta y guardar las imágenes resultantes
            if (isset($response['data']) && is_array($response['data'])) {
                $generatedImages = [];
                $generationId = uniqid('edit_openai_');
                
                foreach ($response['data'] as $resultImage) {
                    if (isset($resultImage['b64_json'])) {
                        $imageBase64 = $resultImage['b64_json'];
                        $mimeType = 'image/jpeg';
                        
                        // Guardar la imagen editada en S3
                        $imageUrl = $this->subirImagenEditadaAS3($imageBase64, $mimeType, 'openai');
                        
                        if ($imageUrl) {
                            $generatedImages[] = [
                                'url' => $imageUrl,
                                'mimeType' => $mimeType
                            ];
                        }
                    } else if (isset($resultImage['url'])) {
                        // Si es una URL directa, descargarla y subirla a S3
                        $imageUrl = $this->descargarYSubirAS3($resultImage['url'], 'openai');
                        
                        if ($imageUrl) {
                            $generatedImages[] = [
                                'url' => $imageUrl,
                                'mimeType' => 'image/jpeg'
                            ];
                        }
                    }
                }
                
                if (!empty($generatedImages)) {
                    // Agregar al historial del generador principal
                    $this->dispatch('addToHistory', 
                        type: 'image/edit', 
                        images: $generatedImages, 
                        generationId: $generationId,
                        prompt: $data['prompt'],
                        model: $this->getModelDisplayName($data['model']),
                        ratio: $data['ratio'],
                        count: $data['count']
                    );
                    
                    $this->results = $generatedImages;
                    
                    Log::info('Imágenes editadas con OpenAI: ' . count($generatedImages));
                    
                    // Finalizar procesamiento exitoso
                    $this->dispatch('editingCompleted');
                    
                    // Limpiar el prompt y la vista previa después de edición exitosa
                    $this->promptText = '';
                    $this->clearImage();
                } else {
                    $this->addError('promptText', 'No se pudieron procesar las imágenes editadas.');
                    $this->dispatch('editingError');
                }
            } else {
                $this->addError('promptText', 'Respuesta inválida de OpenAI.');
                $this->dispatch('editingError');
            }

        } catch (\Exception $e) {
            $errorMessage = 'Error editando con OpenAI: ' . $e->getMessage();
            $this->addError('promptText', $errorMessage);
            
            $this->dispatch('addErrorToList', 
                message: $errorMessage, 
                type: 'system', 
                tool: 'image-editor'
            );
            
            $this->dispatch('editingError');
        } finally {
            // Limpiar archivos temporales si se crearon para imágenes del historial
            if ($this->fromHistory && $this->imageUrl && isset($imagePaths)) {
                foreach ($imagePaths as $tempFile) {
                    if (file_exists($tempFile)) {
                        unlink($tempFile);
                    }
                }
            }
            
            $this->isProcessing = false;
        }
    }
    
    /**
     * Edita imágenes usando Gemini 2.5 Flash
     */
    private function editarConGemini25Flash($data): void
    {
        try {
            Log::info('🎨 Iniciando edición con Gemini 2.5 Flash', [
                'model' => $data['model'],
                'prompt' => substr($data['prompt'], 0, 50) . '...',
                'ratio' => $data['ratio'],
                'images_count' => $data['images_count']
            ]);
            
            // Establecer tiempo de ejecución máximo
            set_time_limit(180); // 3 minutos
            
            // Preparar imágenes en base64 para Gemini
            $imagesBase64 = [];
            
            if ($this->fromHistory && $this->imageUrl) {
                // Para imágenes del historial, descargar y convertir a base64
                $imageContent = file_get_contents($this->imageUrl);
                if ($imageContent === false) {
                    throw new \Exception('No se pudo descargar imagen del historial para Gemini');
                }
                
                // Determinar MIME type basado en la extensión de la URL
                $mimeType = 'image/jpeg'; // Por defecto
                if (strpos($this->imageUrl, '.png') !== false) {
                    $mimeType = 'image/png';
                }
                
                $imagesBase64[] = [
                    'mime_type' => $mimeType,
                    'data' => base64_encode($imageContent)
                ];
                
            } elseif (!empty($this->imageFiles)) {
                // Para imágenes subidas, leer desde archivos temporales
                foreach ($this->imageFiles as $image) {
                    $imageContent = file_get_contents($image->getRealPath());
                    $mimeType = $image->getMimeType();
                    
                    $imagesBase64[] = [
                        'mime_type' => $mimeType,
                        'data' => base64_encode($imageContent)
                    ];
                }
            }
            
            Log::info('🖼️ Imágenes convertidas a base64 para Gemini', [
                'imagesCount' => count($imagesBase64),
                'firstImageMimeType' => $imagesBase64[0]['mime_type'] ?? 'unknown'
            ]);
            
            // Llamar al servicio Gemini para edición
            $response = GeminiService::generateContentImage(
                prompt: $data['prompt'],
                files: $imagesBase64,
                model: $data['model']
            );
            
            Log::info('📡 Respuesta de GeminiService::generateContentImage', [
                'model' => $data['model'],
                'success' => $response['success'] ?? false,
                'hasError' => isset($response['error']),
                'dataCount' => count($response['data'] ?? []),
                'responseKeys' => array_keys($response)
            ]);
            
            if (!($response['success'] ?? false)) {
                $errorMessage = $response['error']['message'] ?? 'No se pudo editar la imagen con Gemini.';
                Log::error('❌ Error en respuesta de Gemini', [
                    'model' => $data['model'],
                    'error' => $response['error'] ?? 'No error details'
                ]);
                
                $this->addError('promptText', $errorMessage);
                
                $this->dispatch('addErrorToList', 
                    message: $errorMessage, 
                    type: 'generation', 
                    tool: 'image-editor'
                );
                
                $this->dispatch('editingError');
                return;
            }
            
            // Procesar respuesta y guardar las imágenes resultantes
            if (isset($response['data']) && is_array($response['data'])) {
                $generatedImages = [];
                $generationId = uniqid('edit_gemini25_');
                
                Log::info('🔄 Procesando imágenes editadas por Gemini 2.5 Flash', [
                    'generationId' => $generationId,
                    'dataCount' => count($response['data'])
                ]);
                
                foreach ($response['data'] as $index => $resultImage) {
                    if (isset($resultImage['base64'])) {
                        $imageBase64 = $resultImage['base64'];
                        $mimeType = $resultImage['mimeType'] ?? 'image/png';
                        
                        Log::info('🖼️ Procesando imagen editada Gemini 2.5 Flash', [
                            'index' => $index,
                            'base64Length' => strlen($imageBase64),
                            'mimeType' => $mimeType,
                            'generationId' => $generationId
                        ]);
                        
                        // Guardar la imagen editada en S3
                        $imageUrl = $this->subirImagenEditadaAS3($imageBase64, $mimeType, 'gemini25');
                        
                        if ($imageUrl) {
                            $generatedImages[] = [
                                'url' => $imageUrl,
                                'mimeType' => $mimeType
                            ];
                            
                            Log::info('✅ Imagen editada Gemini 2.5 Flash procesada exitosamente', [
                                'index' => $index,
                                'url' => $imageUrl
                            ]);
                        }
                    } else {
                        Log::warning('⚠️ Imagen Gemini sin base64', [
                            'index' => $index,
                            'resultImageKeys' => array_keys($resultImage)
                        ]);
                    }
                }
                
                if (!empty($generatedImages)) {
                    Log::info('📊 Resumen de edición Gemini 2.5 Flash', [
                        'generationId' => $generationId,
                        'totalImages' => count($generatedImages),
                        'successfulImages' => count($generatedImages)
                    ]);
                    
                    // Agregar al historial del generador principal
                    $this->dispatch('addToHistory', 
                        type: 'image/edit', 
                        images: $generatedImages, 
                        generationId: $generationId,
                        prompt: $data['prompt'],
                        model: $this->getModelDisplayName($data['model']),
                        ratio: $data['ratio'],
                        count: $data['count']
                    );
                    
                    $this->results = $generatedImages;
                    
                    Log::info('🎉 Edición Gemini 2.5 Flash completada exitosamente', [
                        'generationId' => $generationId,
                        'imagesCount' => count($generatedImages)
                    ]);
                    
                    // Finalizar procesamiento exitoso
                    $this->dispatch('editingCompleted');
                    
                    // Limpiar el prompt y la vista previa después de edición exitosa
                    $this->promptText = '';
                    $this->clearImage();
                    
                } else {
                    Log::warning('⚠️ No se procesaron imágenes editadas con Gemini 2.5 Flash', [
                        'generationId' => $generationId
                    ]);
                    
                    $this->addError('promptText', 'No se pudieron procesar las imágenes editadas con Gemini.');
                    $this->dispatch('editingError');
                }
            } else {
                Log::error('❌ Respuesta inválida de Gemini 2.5 Flash', [
                    'response' => $response
                ]);
                
                $this->addError('promptText', 'Respuesta inválida de Gemini 2.5 Flash.');
                $this->dispatch('editingError');
            }

        } catch (\Exception $e) {
            Log::error('💥 Error en editarConGemini25Flash', [
                'model' => $data['model'],
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $errorMessage = 'Error editando con Gemini 2.5 Flash: ' . $e->getMessage();
            $this->addError('promptText', $errorMessage);
            
            $this->dispatch('addErrorToList', 
                message: $errorMessage, 
                type: 'system', 
                tool: 'image-editor'
            );
            
            $this->dispatch('editingError');
        } finally {
            $this->isProcessing = false;
        }
    }
    
    /**
     * Edita imágenes usando Flux-Kontext
     */
    private function editarConFluxKontext($data): void
    {
        try {
            Log::info('🚀 Iniciando edición con Flux-Kontext', [
                'model' => $data['model'],
                'prompt' => substr($data['prompt'], 0, 50) . '...',
                'ratio' => $data['ratio'],
                'images_count' => $data['images_count']
            ]);
            
            // Subir múltiples imágenes a S3 para obtener las URLs (Flux necesita URLs, no base64)
            $imageUrls = $this->uploadImagesToS3ForFlux();
            if (!$imageUrls['main']) {
                throw new \Exception('No se pudo subir la imagen principal a S3 para Flux');
            }
            
            // Llamar al servicio FluxService para edición con múltiples imágenes
            $response = \App\Services\FluxService::GenerateImageKontext(
                $data['model'],                    // Modelo (flux-kontext-max o flux-kontext-pro)
                $data['prompt'],                   // Prompt de edición
                $data['ratio'],                    // Aspect ratio
                $imageUrls['main'],                // URL de la imagen principal en S3 (input_image)
                false,                             // prompt_upsampling
                null,                              // seed (aleatorio)
                2,                                 // safety_tolerance
                'jpeg',                            // output_format
                null,                              // webhook_url
                null,                              // webhook_secret
                $imageUrls['additional']           // URLs de imágenes adicionales (input_image_2, input_image_3, input_image_4)
            );
            
            Log::info('📝 Respuesta de Flux-Kontext para edición', [
                'response' => $response
            ]);
            
            // Verificar si hubo error en la respuesta inicial
            if (isset($response['error'])) {
                $errorMessage = 'Error con Flux-Kontext: ' . $response['error'];
                $this->addError('promptText', $errorMessage);
                
                $this->dispatch('addErrorToList', 
                    message: $errorMessage, 
                    type: 'generation', 
                    tool: 'image-editor'
                );
                
                $this->dispatch('editingError');
                return;
            }

            // Obtener el ID de generación
            if (!isset($response['data'])) {
                throw new \Exception('Respuesta inesperada de Flux-Kontext');
            }

            $generationId = $response['data'];
            
            // ✅ EMITIR AL FRONTEND - Flux usa su propio sistema (NO es Replicate)
            $this->dispatch('fluxTaskStarted', 
                generationId: $generationId,
                prompt: $data['prompt'],
                model: $data['model'],
                ratio: $data['ratio'],
                count: $data['count'],
                originalImageUrls: $imageUrls
            );
            
            Log::info('🚀 Evento fluxTaskStarted disparado para edición', [
                'generationId' => $generationId,
                'model' => $data['model'],
                'eventName' => 'fluxTaskStarted'
            ]);
            
        } catch (\Exception $e) {
            $errorMessage = 'Error editando con Flux-Kontext: ' . $e->getMessage();
            $this->addError('promptText', $errorMessage);
            
            $this->dispatch('addErrorToList', 
                message: $errorMessage, 
                type: 'system', 
                tool: 'image-editor'
            );
            
            $this->dispatch('editingError');
            $this->isProcessing = false; // Solo en caso de error inicial
        }
        // NO hay finally aquí - el isProcessing se mantiene true hasta que termine el polling
    }

    /**
     * Edita imágenes usando FLUX.2 Pro
     * Soporta multi-referencia hasta 8 imágenes
     */
    private function editarConFlux2Pro($data): void
    {
        try {
            Log::info('🚀 Iniciando edición con Flux 2 Pro', [
                'model' => $data['model'],
                'prompt' => substr($data['prompt'], 0, 50) . '...',
                'ratio' => $data['ratio']
            ]);
            
            // Subir imagen a S3 para obtener URL
            $imageUrls = $this->uploadImagesToS3ForFlux();
            if (!$imageUrls['main']) {
                throw new \Exception('No se pudo subir la imagen a S3 para Flux 2 Pro');
            }
            
            // Obtener dimensiones del ratio
            $dimensions = $this->getDimensionsFromRatio($data['ratio']);
            
            // Llamar al servicio FluxService para edición
            $response = \App\Services\FluxService::GenerateOrEditImageFlux2Pro(
                prompt: $data['prompt'],
                width: $dimensions['width'],
                height: $dimensions['height'],
                inputImage: $imageUrls['main'],
                additionalImages: $imageUrls['additional'] ?? []
            );
            
            Log::info('📝 Respuesta de Flux 2 Pro para edición', [
                'response' => $response
            ]);
            
            // Verificar si hubo error
            if (isset($response['error'])) {
                $errorMessage = 'Error con Flux 2 Pro: ' . (is_array($response['error']) ? json_encode($response['error']) : $response['error']);
                $this->addError('promptText', $errorMessage);
                
                $this->dispatch('addErrorToList', 
                    message: $errorMessage, 
                    type: 'generation', 
                    tool: 'image-editor'
                );
                
                $this->isProcessing = false;
                $this->dispatch('editingError');
                return;
            }

            // Obtener el ID de generación y polling_url
            if (!isset($response['data'])) {
                throw new \Exception('Respuesta inesperada de Flux 2 Pro');
            }

            $generationId = $response['data'];
            $pollingUrl = $response['polling_url'] ?? null;
            
            // ✅ EMITIR AL FRONTEND - Flux usa su propio sistema
            // Incluimos polling_url porque Flux 2 Pro varía la región
            $this->dispatch('fluxTaskStarted', 
                generationId: $generationId,
                prompt: $data['prompt'],
                model: $data['model'],
                ratio: $data['ratio'],
                count: $data['count'] ?? 1,
                originalImageUrls: $imageUrls,
                pollingUrl: $pollingUrl
            );
            
            Log::info('🚀 Evento fluxTaskStarted disparado para Flux 2 Pro edición', [
                'generationId' => $generationId,
                'model' => $data['model'],
                'polling_url' => $pollingUrl
            ]);
            
        } catch (\Exception $e) {
            $errorMessage = 'Error editando con Flux 2 Pro: ' . $e->getMessage();
            $this->addError('promptText', $errorMessage);
            
            $this->dispatch('addErrorToList', 
                message: $errorMessage, 
                type: 'system', 
                tool: 'image-editor'
            );
            
            $this->dispatch('editingError');
            $this->isProcessing = false;
        }
    }
    
    /**
     * Verifica el estado de edición de Flux-Kontext (igual que en ImageGenerator)
     */
    #[On('verificarEstadoFluxKontext')]
    public function verificarEstadoFluxEdicion($generationId, $prompt, $model, $ratio, $count, $originalImageUrls = null, $pollingUrl = null): void
    {
        try {
            Log::info('🔍 Verificando estado de edición Flux desde frontend', [
                'generationId' => $generationId,
                'model' => $model,
                'prompt' => substr($prompt, 0, 50) . '...',
                'ratio' => $ratio,
                'count' => $count,
                'hasOriginalImages' => !empty($originalImageUrls),
                'pollingUrl' => $pollingUrl
            ]);
            
            // Determinar qué endpoint usar según el modelo
            if ($model === 'flux-2-pro' && !empty($pollingUrl)) {
                // Flux 2 Pro usa polling_url dinámico (región varía: us2, eu2, etc.)
                $result = \App\Services\FluxService::GetResultFromPollingUrl($pollingUrl);
            } else {
                // Flux-Kontext usa región us1
                $result = \App\Services\FluxService::GetResultUltra($generationId);
            }
            
            Log::info('📡 Respuesta de FluxService para edición', [
                'generationId' => $generationId,
                'status' => $result['status'] ?? 'unknown',
                'hasData' => isset($result['data'])
            ]);
            
            // Crear array de datos para compatibilidad
            $datos = [
                'generationId' => $generationId,
                'prompt' => $prompt,
                'model' => $model,
                'ratio' => $ratio,
                'count' => $count,
                'originalImageUrls' => $originalImageUrls
            ];
            
            switch ($result['status']) {
                case 'complete':
                case 'Ready':
                    // ✅ IMAGEN LISTA - Verificar si ya se procesó para evitar duplicados
                    if (in_array($generationId, self::$processedGenerationIds)) {
                        Log::info('⏭️ [Flux Edit] Ya procesado, ignorando', ['id' => $generationId]);
                        return;
                    }
                    
                    // Marcar como procesado ANTES de procesar
                    self::$processedGenerationIds[] = $generationId;
                    
                    Log::info('✅ Flux edición completada', ['id' => $generationId]);
                    $this->dispatch('fluxEditCompleted', generationId: $generationId);
                    $this->procesarImagenEditadaFlux($result['data'], $datos);
                    break;
                    
                case 'pending':
                    // ⏳ AÚN PENDIENTE - EMITIR AL FRONTEND PARA NUEVO DELAY
                    Log::info('⏳ Flux edición aún pendiente', ['id' => $generationId]);
                    $this->dispatch('fluxStillPending', 
                        generationId: $generationId,
                        prompt: $prompt,
                        model: $model,
                        ratio: $ratio,
                        count: $count,
                        originalImageUrls: $originalImageUrls,
                        pollingUrl: $pollingUrl
                    );
                    break;
                    
                case 'failed':
                case 'error':
                    // ❌ ERROR
                    Log::error('❌ Flux edición falló', ['id' => $generationId]);
                    $this->isProcessing = false;
                    $this->dispatch('editingError');
                    break;
            }
            
        } catch (\Exception $e) {
            Log::error('💥 Error verificando Flux edición', ['error' => $e->getMessage()]);
            $this->isProcessing = false;
            $this->dispatch('editingError');
        }
    }

    /**
     * Procesa una imagen editada completada por Flux (similar al generador)
     */
    private function procesarImagenEditadaFlux(string $imageUrl, array $datos): void
    {
        try {
            // Descargar la imagen desde la URL de Flux y subirla a S3
            $finalUrl = $this->descargarYSubirAS3($imageUrl, 'flux');

            if (!$finalUrl) {
                throw new \Exception('No se pudo procesar la imagen editada de Flux');
            }

            // Crear datos de la imagen
            $imageData = [
                'url' => $finalUrl,
                'mimeType' => 'image/jpeg'
            ];
            
            $this->results[] = $imageData;

            // Registrar el uso de edición (Flux edita 1 imagen por request)
            $this->trackImageEditUsage(
                $datos['model'], // flux-kontext-max, flux-kontext-pro, flux-2-pro
                1,
                'flux',
                $datos['generationId'] // external_request_id para evitar duplicados
            );

            // Disparar evento de finalización
            $generationId = uniqid('edit_flux_');
            $this->dispatch('addToHistory', 
                type: 'image/edit', 
                images: [$imageData], 
                generationId: $generationId,
                prompt: $datos['prompt'],
                model: $this->getModelDisplayName($datos['model']),
                ratio: $datos['ratio'],
                count: 1 
            );
            
            $this->dispatch('editingCompleted');
            // fluxEditCompleted ya se emitió antes de procesar para detener polling inmediatamente
            
            // Limpiar la vista previa después de edición exitosa con Flux
            $this->clearImage();
            
        } catch (\Exception $e) {
            $errorMessage = 'Error procesando imagen editada con Flux: ' . $e->getMessage();
            $this->addError('promptText', $errorMessage);
            
            $this->dispatch('addErrorToList', 
                message: $errorMessage, 
                type: 'system', 
                tool: 'image-editor'
            );
            
            $this->dispatch('editingError');
        } finally {
            $this->isProcessing = false;
        }
    }
    
    /**
     * Maneja errores durante la edición
     */
    private function handleEditingError(\Exception $e): void
    {
        $errorMessage = 'Error editando imagen: ' . $e->getMessage();
        $this->addError('promptText', $errorMessage);
        
        // Enviar error al componente principal
        $this->dispatch('addErrorToList', 
            message: $errorMessage, 
            type: 'editing', 
            tool: 'image-editor'
        );
        
        $this->dispatch('editingError');
        $this->isProcessing = false;
        
        Log::error('Error en edición de imagen: ' . $e->getMessage());
    }
    
    /**
     * Método auxiliar para convertir nuestro ratio a los tamaños de OpenAI
     */
    private function mapearAspectRatioAOpenAI($ratio)
    {
        switch ($ratio) {
            case '1:1':
                return '1024x1024'; // Cuadrado
            case '16:9':
            case '4:3':
                return '1536x1024'; // Horizontal/Landscape
            case '9:16': 
            case '3:4':
                return '1024x1536'; // Vertical/Portrait
            default:
                return '1024x1024'; // Por defecto cuadrado
        }
    }
    
    /**
     * Sube una imagen editada a S3 (igual que en ImageGenerator)
     */
    private function subirImagenEditadaAS3($base64Image, $mimeType, $servicioOrigen)
    {
        try {
            // Decodificar la imagen base64
            $imageBinary = base64_decode($base64Image);
            
            // Generar nombre de archivo único usando la misma estructura que ImageGenerator
            $fileName = 'genesis/edited-images/' . now()->format('Ymd_His') . '_' . uniqid($servicioOrigen . '_edited_') . '.jpg';
            
            Log::info('Subiendo imagen editada a S3', [
                'fileName' => $fileName,
                'servicioOrigen' => $servicioOrigen,
                'imageSize' => strlen($imageBinary)
            ]);
            
            // Subir a S3
            Storage::disk('s3')->put($fileName, $imageBinary);
            
            // Obtener la URL de S3 (igual que en ImageGenerator)
            $url = Storage::disk('s3')->url($fileName);
            
            Log::info('Imagen editada subida exitosamente a S3', [
                'url' => $url
            ]);
            
            return $url;

        } catch (\Exception $e) {
            Log::error('Error subiendo imagen editada a S3: ' . $e->getMessage(), [
                'servicioOrigen' => $servicioOrigen,
                'error' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Descarga una imagen desde URL y la sube a S3 (para modelos que devuelven URLs)
     */
    private function descargarYSubirAS3($imageUrl, $servicioOrigen)
    {
        try {
            // Descargar la imagen desde la URL
            $imageContent = file_get_contents($imageUrl);
            if ($imageContent === false) {
                throw new \Exception('No se pudo descargar la imagen desde la URL');
            }

            // Generar nombre de archivo único usando la misma estructura que ImageGenerator
            $fileName = 'genesis/edited-images/' . now()->format('Ymd_His') . '_' . uniqid($servicioOrigen . '_edited_') . '.jpg';
            
            Log::info('Descargando y subiendo imagen a S3', [
                'originalUrl' => $imageUrl,
                'fileName' => $fileName,
                'servicioOrigen' => $servicioOrigen,
                'imageSize' => strlen($imageContent)
            ]);

            // Subir a S3
            Storage::disk('s3')->put($fileName, $imageContent);
            
            // Obtener la URL de S3 (igual que en ImageGenerator)
            $finalUrl = Storage::disk('s3')->url($fileName);
            
            Log::info('Imagen descargada y subida exitosamente a S3', [
                'finalUrl' => $finalUrl
            ]);
            
            return $finalUrl;

        } catch (\Exception $e) {
            Log::error('Error descargando y subiendo imagen a S3: ' . $e->getMessage(), [
                'originalUrl' => $imageUrl,
                'servicioOrigen' => $servicioOrigen,
                'error' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Edita imágenes usando Seedream 4.5 (Bytedance via Replicate)
     */
    private function editarConSeedream($data): void
    {
        try {
            Log::info('🎨 Iniciando edición con Seedream 4.5', [
                'model' => $data['model'],
                'prompt' => substr($data['prompt'], 0, 50) . '...',
                'ratio' => $data['ratio'],
                'images_count' => $data['images_count']
            ]);
            
            // Subir imagen a S3 para obtener URL (Seedream necesita URL, no base64)
            $imageUrls = $this->uploadImagesToS3ForFlux(); // Reutilizamos este método
            if (!$imageUrls['main']) {
                throw new \Exception('No se pudo subir la imagen a S3 para Seedream');
            }
            
            // Llamar al servicio Bytedance para edición
            // Usamos match_input_image=true para mantener el aspect ratio de la imagen original
            $response = BytedanceService::editImageSeedream(
                prompt: $data['prompt'],
                imageUrl: $imageUrls['main'],
                imagePromptStrength: 0.5, // Balance entre imagen original y prompt
                matchInputImage: true,    // Mantener aspect ratio de la imagen original
                aspectRatio: $data['ratio'],
                seed: null
            );

            Log::info('📝 Respuesta de BytedanceService::editImageSeedream', [
                'model' => $data['model'],
                'response' => $response,
                'success' => $response['success'] ?? false
            ]);

            if (!($response['success'] ?? false)) {
                $errorMessage = 'Error con Seedream 4.5: ' . ($response['error'] ?? 'Error desconocido');
                $this->addError('promptText', $errorMessage);
                
                $this->dispatch('addErrorToList', 
                    message: $errorMessage, 
                    type: 'generation', 
                    tool: 'image-editor'
                );
                
                $this->isProcessing = false;  // ✅ Importante: resetear estado antes de dispatch
                $this->dispatch('editingError');
                return;
            }

            // Obtener el ID de predicción para polling
            $generationId = $response['prediction_id'];
            
            Log::info('✅ ID de predicción obtenido de Seedream edición', [
                'model' => $data['model'],
                'generationId' => $generationId
            ]);
            
            // ✅ EMITIR AL FRONTEND - Evento genérico de Replicate
            $this->dispatch('replicateEditTaskStarted', 
                generationId: $generationId,
                prompt: $data['prompt'],
                model: $data['model'],
                ratio: $data['ratio'],
                count: $data['count'],
                originalImageUrl: $imageUrls['main'],
                replicateType: 'seedream'
            );

            Log::info('✅ Evento seedreamEditTaskStarted disparado', [
                'generationId' => $generationId,
                'model' => $data['model']
            ]);

        } catch (\Exception $e) {
            Log::error('💥 Error en editarConSeedream', [
                'model' => $data['model'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $errorMessage = 'Error editando con Seedream 4.5: ' . $e->getMessage();
            $this->addError('promptText', $errorMessage);
            
            $this->dispatch('addErrorToList', 
                message: $errorMessage, 
                type: 'system', 
                tool: 'image-editor'
            );
            
            $this->dispatch('editingError');
            $this->isProcessing = false;
        }
    }

    /**
     * Edita imágenes usando Qwen Image Edit (via Replicate)
     * Especializado en edición de texto en imágenes
     */
    private function editarConQwen($data): void
    {
        try {
            Log::info('🎨 Iniciando edición con Qwen Image', [
                'model' => $data['model'],
                'prompt' => substr($data['prompt'], 0, 50) . '...',
                'ratio' => $data['ratio']
            ]);
            
            // Subir imagen a S3 para obtener URL (Qwen necesita URL, no base64)
            $imageUrls = $this->uploadImagesToS3ForFlux();
            if (!$imageUrls['main']) {
                throw new \Exception('No se pudo subir la imagen a S3 para Qwen');
            }
            
            // Llamar al servicio Qwen para edición
            $response = QwenService::editImageQwen(
                prompt: $data['prompt'],
                imageUrl: $imageUrls['main'],
                aspectRatio: $data['ratio']
            );

            Log::info('📝 Respuesta de QwenService::editImageQwen', [
                'model' => $data['model'],
                'response' => $response,
                'success' => $response['success'] ?? false
            ]);

            if (!($response['success'] ?? false)) {
                $errorMessage = 'Error con Qwen Image: ' . ($response['error'] ?? 'Error desconocido');
                $this->addError('promptText', $errorMessage);
                
                $this->dispatch('addErrorToList', 
                    message: $errorMessage, 
                    type: 'generation', 
                    tool: 'image-editor'
                );
                
                $this->isProcessing = false;
                $this->dispatch('editingError');
                return;
            }

            // Obtener el ID de predicción para polling
            $generationId = $response['prediction_id'];
            
            Log::info('✅ ID de predicción obtenido de Qwen edición', [
                'model' => $data['model'],
                'generationId' => $generationId
            ]);
            
            // ✅ EMITIR AL FRONTEND - Evento genérico de Replicate
            $this->dispatch('replicateEditTaskStarted', 
                generationId: $generationId,
                prompt: $data['prompt'],
                model: $data['model'],
                ratio: $data['ratio'],
                count: $data['count'] ?? 1,
                originalImageUrl: $imageUrls['main'],
                replicateType: 'qwen'
            );

            Log::info('✅ Evento replicateEditTaskStarted disparado para Qwen', [
                'generationId' => $generationId,
                'model' => $data['model']
            ]);

        } catch (\Exception $e) {
            Log::error('💥 Error en editarConQwen', [
                'model' => $data['model'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $errorMessage = 'Error editando con Qwen Image: ' . $e->getMessage();
            $this->addError('promptText', $errorMessage);
            
            $this->dispatch('addErrorToList', 
                message: $errorMessage, 
                type: 'system', 
                tool: 'image-editor'
            );
            
            $this->dispatch('editingError');
            $this->isProcessing = false;
        }
    }

    /**
     * Edita imágenes usando Gemini generateContent (Nano Banana Pro / Nano Banana 2).
     * Modelos: gemini-3-pro-image-preview, gemini-3.1-flash-image-preview.
     */
    private function editarConGeminiContentImage($data): void
    {
        try {
            Log::info('🎨 Iniciando edición con Gemini Content Image', [
                'model' => $data['model'],
                'prompt' => substr($data['prompt'], 0, 50) . '...',
                'ratio' => $data['ratio'],
                'resolution' => $this->resolutionNanoBanana
            ]);

            set_time_limit(180);

            // Preparar imágenes en base64 para Gemini (igual que editarConGemini25Flash)
            $imagesBase64 = [];
            if ($this->fromHistory && $this->imageUrl) {
                $imageContent = file_get_contents($this->imageUrl);
                if ($imageContent === false) {
                    throw new \Exception('No se pudo descargar imagen del historial para Gemini');
                }
                $mimeType = (strpos($this->imageUrl, '.png') !== false) ? 'image/png' : 'image/jpeg';
                $imagesBase64[] = ['mime_type' => $mimeType, 'data' => base64_encode($imageContent)];
            } elseif (!empty($this->imageFiles)) {
                foreach ($this->imageFiles as $image) {
                    $imageContent = file_get_contents($image->getRealPath());
                    $imagesBase64[] = [
                        'mime_type' => $image->getMimeType(),
                        'data' => base64_encode($imageContent)
                    ];
                }
            }

            if (empty($imagesBase64)) {
                throw new \Exception('No hay imagen para editar.');
            }

            $aspectRatio = $data['ratio'];
            $imageSize = ($data['model'] === 'gemini-3-pro-image-preview') ? $this->resolutionNanoBanana : '1K';

            $response = GeminiService::generateContentImage(
                $data['prompt'],
                $imagesBase64,
                $data['model'],
                $aspectRatio,
                $imageSize
            );

            Log::info('📡 Respuesta de GeminiService::generateContentImage (edición)', [
                'model' => $data['model'],
                'success' => $response['success'] ?? false,
                'hasError' => isset($response['error']),
                'dataCount' => isset($response['data']) ? count($response['data']) : 0
            ]);

            if (!($response['success'] ?? false)) {
                $errorMessage = 'Error con Gemini: ' . ($response['error']['message'] ?? 'Error desconocido');
                Log::error('❌ Error en respuesta de Gemini Content Image (edición)', [
                    'model' => $data['model'],
                    'error' => $response['error'] ?? null
                ]);
                $this->addError('promptText', $errorMessage);
                $this->dispatch('addErrorToList', message: $errorMessage, type: 'generation', tool: 'image-editor');
                $this->dispatch('editingError');
                $this->isProcessing = false;
                return;
            }

            $images = $response['data'] ?? [];
            $usageMetadata = $response['usageMetadata'] ?? null;
            $generationId = uniqid('edit_gemini_');
            $generatedImages = [];

            foreach ($images as $index => $resultImage) {
                if (empty($resultImage['base64'])) continue;
                $mimeType = $resultImage['mimeType'] ?? 'image/png';
                $imageUrl = $this->subirImagenEditadaAS3($resultImage['base64'], $mimeType, 'gemini3');
                if ($imageUrl) {
                    $generatedImages[] = ['url' => $imageUrl, 'mimeType' => $mimeType];
                }
            }

            if (!empty($generatedImages)) {
                $userId = Auth::id();
                if ($userId && $usageMetadata !== null) {
                    $inputTokens = $usageMetadata['promptTokenCount'] ?? $usageMetadata['inputTokenCount'] ?? 0;
                    $outputTokens = $usageMetadata['candidatesTokenCount'] ?? $usageMetadata['outputTokenCount'] ?? 0;
                    if ($inputTokens > 0 || $outputTokens > 0) {
                        try {
                            CostCalculationService::trackUsage(
                                $this->accountId,
                                $userId,
                                $data['model'],
                                ['tokens' => ['input' => $inputTokens, 'output' => $outputTokens]],
                                null,
                                'Image Editor',
                                null,
                                null,
                                null,
                                'gemini'
                            );
                        } catch (\Exception $e) {
                            Log::error('Error al registrar uso Gemini Content Image (edición)', ['error' => $e->getMessage()]);
                        }
                    }
                } else {
                    $this->trackImageEditUsage($data['model'], count($generatedImages), 'gemini');
                }

                $this->dispatch('addToHistory',
                    type: 'image/edit',
                    images: $generatedImages,
                    generationId: $generationId,
                    prompt: $data['prompt'],
                    model: $this->getModelDisplayName($data['model']),
                    ratio: $data['ratio'],
                    count: count($generatedImages)
                );
                $this->results = $generatedImages;
                $this->dispatch('editingCompleted');
                $this->promptText = '';
                $this->clearImage();
            } else {
                $this->addError('promptText', 'No se pudieron procesar las imágenes editadas.');
                $this->dispatch('editingError');
            }
        } catch (\Exception $e) {
            Log::error('💥 Error en editarConGeminiContentImage', [
                'model' => $data['model'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->addError('promptText', 'Error: ' . $e->getMessage());
            $this->dispatch('addErrorToList', message: $e->getMessage(), type: 'system', tool: 'image-editor');
            $this->dispatch('editingError');
        } finally {
            $this->isProcessing = false;
        }
    }

    /**
     * Verifica el estado de edición de Seedream 4.5
     */
    #[On('verificarEstadoSeedreamEdit')]
    public function verificarEstadoSeedreamEdit($generationId, $prompt, $model, $ratio, $count, $originalImageUrl = null): void
    {
        try {
            Log::info('🔍 Verificando estado de Seedream edición desde frontend', [
                'generationId' => $generationId,
                'model' => $model
            ]);
            
            // Consultar estado usando el servicio base de Replicate
            $result = BytedanceService::getPredictionStatus($generationId);
            
            Log::info('📡 Respuesta de BytedanceService::getPredictionStatus (edición)', [
                'generationId' => $generationId,
                'status' => $result['status'] ?? 'unknown',
                'hasOutput' => isset($result['output'])
            ]);
            
            $datos = [
                'generationId' => $generationId,
                'prompt' => $prompt,
                'model' => $model,
                'ratio' => $ratio,
                'count' => $count,
                'originalImageUrl' => $originalImageUrl
            ];
            
            $status = $result['status'] ?? 'unknown';
            
            switch ($status) {
                case 'succeeded':
                    // ✅ IMAGEN LISTA
                    Log::info('✅ Seedream edición completada', ['id' => $generationId]);
                    $this->procesarImagenEditadaSeedream($result['output'], $datos);
                    break;
                    
                case 'starting':
                case 'processing':
                    // ⏳ AÚN PENDIENTE
                    Log::info('⏳ Seedream edición aún pendiente', [
                        'id' => $generationId,
                        'status' => $status
                    ]);
                    $this->dispatch('replicateEditStillPending', 
                        generationId: $generationId,
                        prompt: $prompt,
                        model: $model,
                        ratio: $ratio,
                        count: $count,
                        originalImageUrl: $originalImageUrl,
                        replicateType: 'seedream'
                    );
                    break;
                    
                case 'failed':
                    // ❌ ERROR
                    Log::error('❌ Seedream edición falló', [
                        'id' => $generationId,
                        'error' => $result['error'] ?? 'No error details'
                    ]);
                    $this->isProcessing = false;
                    $this->dispatch('editingError');
                    break;
                    
                default:
                    Log::warning('⚠️ Estado desconocido de Seedream edición', [
                        'id' => $generationId,
                        'status' => $status
                    ]);
                    $this->isProcessing = false;
                    $this->dispatch('editingError');
                    break;
            }
            
        } catch (\Exception $e) {
            Log::error('💥 Error verificando Seedream edición', ['error' => $e->getMessage()]);
            $this->isProcessing = false;
            $this->dispatch('editingError');
        }
    }

    /**
     * Procesa una imagen editada completada por Seedream
     */
    private function procesarImagenEditadaSeedream($output, array $datos): void
    {
        try {
            Log::info('🔄 Procesando imagen editada de Seedream', [
                'generationId' => $datos['generationId'],
                'model' => $datos['model'],
                'output' => $output
            ]);
            
            // Seedream devuelve un array de URLs o una URL única
            $imageUrls = is_array($output) ? $output : [$output];
            $generatedImages = [];
            
            foreach ($imageUrls as $index => $imageUrl) {
                if (empty($imageUrl)) continue;
                
                // Descargar y subir a S3
                $finalUrl = $this->descargarYSubirAS3($imageUrl, 'seedream');
                
                if ($finalUrl) {
                    $generatedImages[] = [
                        'url' => $finalUrl,
                        'mimeType' => 'image/png'
                    ];
                    
                    Log::info('✅ Imagen editada Seedream procesada', [
                        'index' => $index,
                        'finalUrl' => $finalUrl
                    ]);
                }
            }

            if (!empty($generatedImages)) {
                // Registrar el uso de edición
                $this->trackImageEditUsage(
                    'seedream-4.5',
                    count($generatedImages),
                    'replicate',
                    $datos['generationId'] // external_request_id para evitar duplicados
                );
                
                // Agregar al historial
                $generationId = uniqid('edit_seedream_');
                $this->dispatch('addToHistory', 
                    type: 'image/edit', 
                    images: $generatedImages, 
                    generationId: $generationId,
                    prompt: $datos['prompt'],
                    model: $this->getModelDisplayName($datos['model']),
                    ratio: $datos['ratio'],
                    count: count($generatedImages)
                );
                
                $this->results = $generatedImages;
                
                Log::info('🎉 Edición Seedream completada exitosamente', [
                    'generationId' => $generationId,
                    'imagesCount' => count($generatedImages)
                ]);
                
                $this->dispatch('editingCompleted');
                // Marcar el ID original como completado para detener el polling
                $this->dispatch('replicateEditCompleted', 
                    generationId: $datos['generationId'],
                    replicateType: 'seedream'
                );
                
                // Limpiar después de edición exitosa
                $this->promptText = '';
                $this->clearImage();
            } else {
                throw new \Exception('No se pudieron procesar las imágenes editadas de Seedream');
            }
            
        } catch (\Exception $e) {
            Log::error('💥 Error procesando imagen editada Seedream', [
                'generationId' => $datos['generationId'],
                'error' => $e->getMessage()
            ]);
            
            $errorMessage = 'Error procesando imagen editada Seedream: ' . $e->getMessage();
            $this->addError('promptText', $errorMessage);
            
            $this->dispatch('addErrorToList', 
                message: $errorMessage, 
                type: 'system', 
                tool: 'image-editor'
            );
            
            $this->dispatch('editingError');
        } finally {
            $this->isProcessing = false;
        }
    }

    public function mount()
    {
        // Inicialización básica
        Log::info('ImageEditor component mounted', [
            'accountId' => $this->accountId
        ]);
    }

    /**
     * ✅ NUEVO: Listener para actualizar cuenta cuando cambia en el padre
     */
    #[On('accountChanged')]
    public function updateAccount(?int $accountId): void
    {
        $this->accountId = $accountId;
        
        Log::info('🔄 Cuenta actualizada en ImageEditor', [
            'accountId' => $accountId
        ]);
    }

    /**
     * ========== MÉTODO GENÉRICO PARA VERIFICAR ESTADO DE REPLICATE (EDICIÓN) ==========
     * Funciona para TODOS los modelos de Replicate (Flux, Seedream, etc.)
     */
    #[On('verificarEstadoReplicateEdit')]
    public function verificarEstadoReplicateEdit($generationId, $prompt, $model, $ratio, $count, $replicateType, $originalImageUrls = null, $originalImageUrl = null): void
    {
        try {
            Log::info('🔍 [Replicate Edit Genérico] Verificando estado', [
                'generationId' => $generationId,
                'model' => $model,
                'replicateType' => $replicateType
            ]);
            
            // Obtener estado según el tipo
            $result = $this->obtenerEstadoReplicateEdit($generationId, $replicateType);
            
            $datos = [
                'generationId' => $generationId,
                'prompt' => $prompt,
                'model' => $model,
                'ratio' => $ratio,
                'count' => $count,
                'replicateType' => $replicateType,
                'originalImageUrls' => $originalImageUrls,
                'originalImageUrl' => $originalImageUrl
            ];
            
            // Replicate siempre usa estos estados: 'starting', 'processing', 'succeeded', 'failed'
            $status = $result['status'] ?? 'unknown';
            
            if ($status === 'succeeded') {
                // ✅ Verificar si ya se procesó para evitar duplicados
                if (in_array($generationId, self::$processedGenerationIds)) {
                    Log::info('⏭️ [Replicate Edit] Ya procesado, ignorando', ['id' => $generationId]);
                    return;
                }
                
                // Marcar como procesado ANTES de procesar
                self::$processedGenerationIds[] = $generationId;
                
                Log::info('✅ [Replicate Edit] Completado', ['id' => $generationId, 'type' => $replicateType]);
                
                // Emitir evento ANTES de procesar para detener polling
                $this->dispatch('replicateEditCompleted', generationId: $generationId);
                
                $this->procesarResultadoReplicateEdit($result, $datos);
            } elseif (in_array($status, ['starting', 'processing'])) {
                Log::info('⏳ [Replicate Edit] Aún pendiente', ['id' => $generationId, 'type' => $replicateType, 'status' => $status]);
                $this->dispatch('replicateEditStillPending', 
                    generationId: $generationId,
                    prompt: $prompt,
                    model: $model,
                    ratio: $ratio,
                    count: $count,
                    originalImageUrls: $originalImageUrls,
                    originalImageUrl: $originalImageUrl,
                    replicateType: $replicateType
                );
            } else {
                Log::error('❌ [Replicate Edit] Error o estado desconocido', [
                    'id' => $generationId,
                    'status' => $status,
                    'error' => $result['error'] ?? 'Estado desconocido'
                ]);
                $this->isProcessing = false;
                $this->dispatch('editingError');
            }
            
        } catch (\Exception $e) {
            Log::error('💥 [Replicate Edit Genérico] Error verificando estado', [
                'error' => $e->getMessage(),
                'generationId' => $generationId
            ]);
            $this->isProcessing = false;
            $this->dispatch('editingError');
        }
    }
    
    /**
     * Obtiene el estado según el tipo de modelo de Replicate (edición)
     * NO incluye Flux - Flux usa su propio sistema
     */
    private function obtenerEstadoReplicateEdit(string $generationId, string $replicateType): array
    {
        switch ($replicateType) {
            case 'seedream':
                return BytedanceService::getPredictionStatus($generationId);
            case 'qwen':
                return QwenService::getPredictionStatus($generationId);
            default:
                Log::warning('⚠️ [Replicate Edit] Tipo desconocido, usando genérico', ['type' => $replicateType]);
                return BytedanceService::getPredictionStatus($generationId);
        }
    }
    
    /**
     * Procesa el resultado según el tipo de modelo de Replicate (edición)
     * Replicate siempre devuelve el output en 'output'
     */
    private function procesarResultadoReplicateEdit(array $result, array $datos): void
    {
        $replicateType = $datos['replicateType'];
        $output = $result['output'];
        
        switch ($replicateType) {
            case 'seedream':
                $this->procesarImagenEditadaSeedream($output, $datos);
                break;
            case 'qwen':
                // Qwen devuelve imágenes igual que otros modelos de Replicate
                $this->procesarImagenEditadaReplicate($output, $datos);
                break;
            default:
                Log::warning('⚠️ [Replicate Edit] Tipo de procesamiento desconocido, usando genérico', ['type' => $replicateType]);
                $this->procesarImagenEditadaReplicate($output, $datos);
        }
    }
    
    /**
     * Procesa imágenes editadas de modelos de Replicate genéricos (Qwen, etc.)
     */
    private function procesarImagenEditadaReplicate($output, array $datos): void
    {
        try {
            Log::info('🔄 Procesando imagen editada de Replicate', [
                'generationId' => $datos['generationId'],
                'model' => $datos['model'],
                'replicateType' => $datos['replicateType'] ?? 'unknown',
                'output' => $output
            ]);
            
            // Replicate puede devolver un array de URLs o una URL única
            $imageUrls = is_array($output) ? $output : [$output];
            $generatedImages = [];
            
            foreach ($imageUrls as $index => $imageUrl) {
                if (empty($imageUrl)) continue;
                
                // Descargar la imagen desde la URL
                $imageContent = file_get_contents($imageUrl);
                if ($imageContent === false) {
                    Log::warning('⚠️ No se pudo descargar imagen editada de Replicate', [
                        'index' => $index,
                        'url' => $imageUrl
                    ]);
                    continue;
                }

                Log::info('📥 Imagen editada descargada exitosamente', [
                    'generationId' => $datos['generationId'],
                    'imageSize' => strlen($imageContent),
                    'index' => $index
                ]);

                // Guardar en S3
                $fileName = 'genesis/output-images/' . now()->format('Ymd_His') . '_edit_' . ($datos['replicateType'] ?? 'replicate') . '_' . uniqid('img_') . '.png';
                Storage::disk('s3')->put($fileName, $imageContent);
                $finalUrl = Storage::disk('s3')->url($fileName);

                Log::info('☁️ Imagen editada subida a S3 exitosamente', [
                    'generationId' => $datos['generationId'],
                    'fileName' => $fileName,
                    'finalUrl' => $finalUrl
                ]);

                $imageData = [
                    'url' => $finalUrl,
                    'mimeType' => 'image/png'
                ];
                
                $this->results[] = $imageData;
                $generatedImages[] = $imageData;
            }

            if (!empty($generatedImages)) {
                // Registrar el uso de edición
                // Mapear replicateType a nombre de modelo
                $modelName = match($datos['replicateType'] ?? 'unknown') {
                    'qwen' => 'qwen-image',
                    default => $datos['model'] ?? 'unknown'
                };
                
                $this->trackImageEditUsage(
                    $modelName,
                    count($generatedImages),
                    'replicate',
                    $datos['generationId'] // external_request_id para evitar duplicados
                );
                
                // Disparar evento de finalización
                $generationId = uniqid('edit_' . ($datos['replicateType'] ?? 'replicate') . '_');
                $this->dispatch('addToHistory', 
                    type: 'image/edit', 
                    images: $generatedImages, 
                    generationId: $generationId,
                    prompt: $datos['prompt'],
                    model: $this->getModelDisplayName($datos['model']),
                    ratio: $datos['ratio'],
                    count: count($generatedImages)
                );
                
                $this->results = $generatedImages;
                
                Log::info('🎉 Edición completada exitosamente', [
                    'generationId' => $generationId,
                    'imagesCount' => count($generatedImages)
                ]);
                
                $this->dispatch('editingCompleted');
                // Marcar el ID original como completado para detener el polling
                $this->dispatch('replicateEditCompleted', 
                    generationId: $datos['generationId'],
                    replicateType: $datos['replicateType'] ?? 'unknown'
                );
                
                // Limpiar después de edición exitosa
                $this->promptText = '';
                $this->clearImage();
            } else {
                throw new \Exception('No se pudieron procesar las imágenes editadas');
            }
            
        } catch (\Exception $e) {
            Log::error('💥 Error procesando imagen editada de Replicate', [
                'generationId' => $datos['generationId'],
                'error' => $e->getMessage()
            ]);
            
            $errorMessage = 'Error procesando imagen editada: ' . $e->getMessage();
            $this->addError('promptText', $errorMessage);
            
            $this->dispatch('addErrorToList', 
                message: $errorMessage, 
                type: 'system', 
                tool: 'image-editor'
            );
            
            $this->dispatch('editingError');
        } finally {
            $this->isProcessing = false;
        }
    }

    /**
     * Convierte ratio a dimensiones (para Flux 2 Pro)
     */
    private function getDimensionsFromRatio($ratio): array
    {
        return match($ratio) {
            '1:1' => ['width' => 1024, 'height' => 1024],
            '4:3' => ['width' => 1024, 'height' => 768],
            '3:4' => ['width' => 768, 'height' => 1024],
            '16:9' => ['width' => 1024, 'height' => 576],
            '9:16' => ['width' => 576, 'height' => 1024],
            default => ['width' => 1024, 'height' => 1024]
        };
    }

    /**
     * Registra el uso de un modelo que cobra por generación (edición)
     * 
     * @param string $modelName Nombre del modelo (ej: 'flux-kontext-max', 'seedream-4.5')
     * @param int $editsCount Número de imágenes editadas
     * @param string|null $serviceType Tipo de servicio (ej: 'flux', 'replicate')
     * @param string|null $externalRequestId ID externo para evitar duplicados (opcional)
     */
    private function trackImageEditUsage(string $modelName, int $editsCount = 1, ?string $serviceType = null, ?string $externalRequestId = null): void
    {
        // ✅ Usar accountId del componente
        $userId = Auth::id();
        
        if (!$userId) {
            Log::warning('⚠️ No se pudo obtener userId para registrar uso de edición de imagen', [
                'model' => $modelName,
                'accountId' => $this->accountId
            ]);
            return;
        }

        if ($editsCount <= 0) {
            Log::warning('⚠️ Número de ediciones inválido', [
                'model' => $modelName,
                'editsCount' => $editsCount
            ]);
            return;
        }

        try {
            CostCalculationService::trackUsage(
                $this->accountId,  // ✅ Usar accountId del componente
                $userId,
                $modelName,
                [
                    'generations' => $editsCount // Para modelos de edición, usamos 'generations' igual que generación
                ],
                null, // usageDate (usa ahora)
                'Image Editor', // request_type
                $externalRequestId, // external_request_id (para evitar duplicados en Replicate)
                null, // generated_id (no hay Generated para ediciones simples)
                null, // step
                $serviceType // service_type
            );
            
            Log::info('✅ Uso registrado exitosamente para edición de imagen', [
                'model' => $modelName,
                'edits' => $editsCount,
                'accountId' => $this->accountId,
                'userId' => $userId
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Error al registrar uso de edición de imagen', [
                'model' => $modelName,
                'edits' => $editsCount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function render()
    {
        return view('livewire.generador.herramientas.image-editor');
    }
}

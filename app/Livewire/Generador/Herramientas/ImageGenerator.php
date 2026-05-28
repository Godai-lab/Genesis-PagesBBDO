<?php

namespace App\Livewire\Generador\Herramientas;

use App\Http\Traits\ValidatesCreditLimit;
use App\Services\GeminiService;
use App\Services\FluxService;
use App\Services\OpenAiService;
use App\Services\Replicate\BytedanceService;
use App\Services\Replicate\QwenService;
use App\Services\Replicate\GoogleService;
use App\Supports\CostCalculationService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Log;
/**
 * Generador de Imágenes (Gemini)
 *
 * Enfoque minimal: solo modelos de Gemini Imagen (3.x), ratio y cantidad.
 * Guarda los resultados en storage público y emite eventos al historial global.
 */
class ImageGenerator extends Component
{
    use ValidatesCreditLimit;
    use WithFileUploads;
    
    protected string $toolName = 'Generador de Imágenes';
    
    /** ✅ Account ID recibido del componente padre - Reactive para sincronizar automáticamente */
    #[Reactive]
    public ?int $accountId = null;
    
    /** Texto del prompt */
    #[Validate('required|string|min:3')]
    public string $promptText = '';

    /** Modelo de imagen por defecto */
    public string $model = 'gemini-3.1-flash-image-preview';
   
    /** Relación de aspecto */
    public string $ratio = '1:1';
    public bool $isGenerating = false;

    /** Cantidad de imágenes a generar */
    #[Validate('integer|min:1|max:4')]
    public int $count = 1;

    /** Resolución para Nano Banana Pro: "1K", "2K", "4K" */
    public string $resolutionNanoBanana = "2K";

    /** Sidebar de contexto (Nano Banana): visible al pulsar "Contexto" */
    public bool $showContextSidebar = false;

    /** Toggle: cuando está en true el contexto y las imágenes de referencia se incluyen en la generación */
    public bool $contextEnabled = false;

    /** Texto de contexto adicional que influye en la generación */
    public string $contextText = '';

    /** Imágenes de referencia en base64: se llenan al subir y persisten en el estado Livewire entre peticiones */
    public array $referenceImagesData = [];

    /** Input temporal para añadir una imagen de referencia */
    public $newReferenceImages = [];

    /** Resultados generados recientemente */
    public array $results = [];

    /** IDs de generaciones ya procesadas (para evitar duplicados) */
    private static array $processedGenerationIds = [];

    /** Propiedades específicas de OpenAI */
    public string $calidadImagen = 'auto'; // valores posibles: 'auto', 'high', 'medium', 'low'

    public array $calidadesDisponibles = [
        'auto' => 'Automática',
        'high' => 'Alta',
        'medium' => 'Media',
        'low' => 'Baja'
    ];



/** Catálogo de modelos disponibles con información detallada */
public array $availableModels = [
    'gemini-3-pro-image-preview' => [
        'name' => 'Nano Banana Pro',
        'price' => '~$0.13',
        'priceUnit' => 'por imagen (1K/2K, según tokens)',
        'description' => 'Modelo Gemini 3 Pro Image de Google. Cobro por token (entrada $2/1M, salida $120/1M)',
        'bestFor' => 'Imágenes con texto, infografías, contenido educativo, mockups de productos',
        'speed' => 'Rápido',
        'quality' => 'Excelente'
    ],
    'gemini-3.1-flash-image-preview' => [
        'name' => 'Nano Banana 2',
        'price' => '~$0.07',
        'priceUnit' => 'por imagen (1K, según tokens)',
        'description' => 'Modelo Gemini 3.1 Flash optimizado para velocidad. Cobro por token (entrada $0.25/1M, salida $60/1M)',
        'bestFor' => 'Generación rápida, iteraciones frecuentes, imágenes con texto y composición creativa',
        'speed' => 'Muy rápido',
        'quality' => 'Excelente'
    ],
    'imagen-4-ultra' => [
        'name' => 'Imagen 4 Ultra',
        'price' => '$0.06',
        'priceUnit' => 'por imagen',
        'description' => 'Versión Ultra de Imagen 4 para máxima calidad sobre velocidad',
        'bestFor' => 'Detalles finos (telas, gotas, pelo), tipografía precisa, resolución hasta 2K',
        'speed' => 'Medio',
        'quality' => 'Excepcional'
    ],
    'flux-kontext-max' => [
        'name' => 'Flux-Kontext-Max',
        'price' => '$0.08',
        'priceUnit' => 'por imagen',
        'description' => 'Modelo Flux de máxima calidad con capacidades avanzadas',
        'bestFor' => 'Imágenes artísticas de alta calidad, trabajos profesionales',
        'speed' => 'Medio',
        'quality' => 'Excelente'
    ],
    'flux-kontext-pro' => [
        'name' => 'Flux-Kontext-Pro',
        'price' => '$0.04',
        'priceUnit' => 'por imagen',
        'description' => 'Modelo Flux equilibrado para uso profesional',
        'bestFor' => 'Contenido creativo, ilustraciones, diseño',
        'speed' => 'Rápido',
        'quality' => 'Muy buena'
    ],
    'flux-pro' => [
        'name' => 'Flux-Pro-1.1',
        'price' => '$0.04',
        'priceUnit' => 'por imagen',
        'description' => 'Modelo Flux Pro de alta calidad con control de dimensiones',
        'bestFor' => 'Imágenes profesionales con control preciso de tamaño',
        'speed' => 'Medio Rápido',
        'quality' => 'Excelente'
    ],
    'flux-ultra' => [
        'name' => 'Flux-Ultra',
        'price' => '$0.06',
        'priceUnit' => 'por imagen',
        'description' => 'Modelo Flux Ultra de máxima calidad y detalle',
        'bestFor' => 'Trabajos de máxima calidad, arte conceptual profesional',
        'speed' => 'Medio Rápido',
        'quality' => 'Excepcional'
    ],
    'flux-2-pro' => [
        'name' => 'Flux 2 Pro',
        'price' => '$0.03',
        'priceUnit' => 'por imagen',
        'description' => 'FLUX.2 Pro con multi-referencia y salida hasta 4MP',
        'bestFor' => 'Imágenes fotorealistas, edición multi-referencia',
        'speed' => 'Rápido',
        'quality' => 'Excepcional'
    ],
   'gpt-image-1' => [
    'name' => 'ChatGPT Imagen',
    'price' => '$0.10',
    'priceUnit' => 'por imagen',
    'description' => 'Modelo de OpenAI para generación de imágenes de alta calidad',
    'bestFor' => 'Ilustraciones creativas, diseño gráfico, arte conceptual',
    'speed' => 'Lento',
    'quality' => 'Alta-Media-Baja-'
    ],
    'gpt-image-1.5' => [
        'name' => 'GPT Image 1.5',
        'price' => '$0.10',
        'priceUnit' => 'por imagen',
        'description' => 'Modelo GPT de OpenAI para generación de imágenes con mejor calidad y detalle',
        'bestFor' => 'Ilustraciones creativas, diseño gráfico, arte conceptual, imágenes profesionales',
        'speed' => 'Medio',
        'quality' => 'Excelente'
    ],
    'seedream-4.5' => [
        'name' => 'Seedream 4.5',
        'price' => '$0.04',
        'priceUnit' => 'por imagen',
        'description' => 'Modelo de Bytedance con comprensión espacial avanzada',
        'bestFor' => 'Imágenes cinematográficas, escenas complejas, diseño profesional',
        'speed' => 'Rápido',
        'quality' => 'Excelente'
    ],
    'qwen-image' => [
        'name' => 'Qwen Image',
        'price' => '$0.025',
        'priceUnit' => 'por imagen',
        'description' => 'Excelente renderizado de texto en imágenes, múltiples estilos',
        'bestFor' => 'Texto en imágenes, posters, señalización, arte con tipografía',
        'speed' => 'Medio',
        'quality' => 'Excelente'
    ],

];
    /**
     * Obtiene el nombre amigable del modelo
     */
private function getModelDisplayName($modelKey): string
    {
        return $this->availableModels[$modelKey]['name'] ?? $modelKey;
    }
// Método helper para obtener solo los nombres de los modelos (para compatibilidad)
public function getModelNamesAttribute(): array
{
    return collect($this->availableModels)->mapWithKeys(function ($info, $key) {
        return [$key => $info['name']];
    })->toArray();
}

    public array $availableRatios = [
        '1:1' => 'Cuadrado',
        '16:9' => 'Panorámico',
        '9:16' => 'Vertical móvil',
        '4:3' => 'Horizontal',
        '3:4' => 'Vertical',
    ];

    /**
     * Determina si el modelo actual soporta múltiples imágenes
     */
    public function getSupportsMultipleImagesProperty(): bool
    {
        // Modelos que soportan múltiples imágenes
        // Los modelos Flux (todos) solo generan 1 imagen por request
        return in_array($this->model, [
            'gpt-image-1',
            'gpt-image-1.5' // OpenAI GPT image models soportan múltiples imágenes
        ]);
    }

    /** Modelos Nano Banana que tienen sidebar de contexto e imágenes de referencia */
    protected static array $nanoBananaModels = ['gemini-3-pro-image-preview', 'gemini-3.1-flash-image-preview'];

    /** Si el modelo actual muestra el botón Contexto y el sidebar */
    public function getSupportsContextSidebarProperty(): bool
    {
        return in_array($this->model, self::$nanoBananaModels);
    }

    /** Límite de imágenes de referencia según modelo: 3.1 = 14, 3 Pro = 11 */
    public function getMaxReferenceImagesProperty(): int
    {
        return $this->model === 'gemini-3.1-flash-image-preview' ? 14 : 11;
    }

    public function updatedNewReferenceImages(): void
{
    $this->validateOnly('newReferenceImages', [
        'newReferenceImages'   => 'nullable|array',
        'newReferenceImages.*' => 'image|max:10240',
    ]);

    if (empty($this->newReferenceImages)) {
        return;
    }

    $max = $this->maxReferenceImages;
    $remaining = $max - count($this->referenceImagesData);
    if (count($this->newReferenceImages) > $remaining) {
        // opción A: error de validación
        $this->addError('newReferenceImages', "Solo puedes añadir {$remaining} imagen(es) más. Límite del modelo: {$max}.");
        // opción B: solo silenciosamente cortar (ya lo hace)
    }

    foreach (array_slice($this->newReferenceImages, 0, $remaining) as $upload) {
        if (is_object($upload) && method_exists($upload, 'getRealPath') && $upload->getRealPath()) {
            $raw = file_get_contents($upload->getRealPath());
            if ($raw !== false) {
                $mime = method_exists($upload, 'getMimeType') ? ($upload->getMimeType() ?: 'image/jpeg') : 'image/jpeg';
                $this->referenceImagesData[] = [
                    'data'      => base64_encode($raw),
                    'mime_type' => $mime,
                ];
            }
        }
    }

    $this->reset('newReferenceImages');
}

    public function removeReferenceImage(int $index): void
    {
        if (isset($this->referenceImagesData[$index])) {
            array_splice($this->referenceImagesData, $index, 1);
        }
    }

    public function toggleContextSidebar(): void
    {
        $this->showContextSidebar = !$this->showContextSidebar;
    }

    /** Cierra el sidebar y activa el contexto para que la caja principal lo use */
    public function acceptContext(): void
    {
        $this->contextEnabled = true;
        $this->showContextSidebar = false;
        $this->dispatch('contextApplied');
    }

    /** Limpia los datos de contexto y desactiva el toggle */
    public function clearContext(): void
    {
        $this->contextText = '';
        $this->referenceImagesData = [];
        $this->contextEnabled = false;
        $this->showContextSidebar = false;
    }

    protected function rules(): array
    {
        return [
            'newReferenceImages'   => 'nullable|array',
            'newReferenceImages.*' => 'image|max:10240',
        ];
    }
   
    #[On('image-generator-model-selected')]
    public function updateModel($key)
    {
        $this->model = $key;
        
        // Si el nuevo modelo no soporta múltiples imágenes, resetear a 1
        if (!$this->supportsMultipleImages && $this->count > 1) {
            $this->count = 1;
        }
    }

    #[On('loadPromptForImageGeneration')]
    public function loadPromptFromHistory($prompt = null)
    {
        Log::info('🔍 DEBUG: loadPromptFromHistory llamado', [
            'prompt' => $prompt,
            'type' => gettype($prompt),
            'current_promptText' => $this->promptText
        ]);
        
        // Verificar que tenemos un prompt válido
        if (empty($prompt)) {
            Log::warning('⚠️ Prompt vacío o nulo recibido en loadPromptFromHistory', [
                'prompt' => $prompt,
                'type' => gettype($prompt)
            ]);
            return;
        }
        
        // Asignar el prompt directamente
        Log::info('📝 Cargando prompt para generación de imagen', [
            'prompt' => substr($prompt, 0, 50) . '...',
            'full_prompt_length' => strlen($prompt)
        ]);
        
        $this->promptText = $prompt;
        
        Log::info('✅ Prompt asignado exitosamente', [
            'new_promptText_length' => strlen($this->promptText),
            'new_promptText_preview' => substr($this->promptText, 0, 100) . '...'
        ]);
        
        // Forzar actualización del componente
        $this->dispatch('$refresh');
    }
     public function mount()
    {
        Log::info('🔧 ImageGenerator montado correctamente', [
            'accountId' => $this->accountId
        ]);
        
        // Verificar si hay datos pendientes de prompt
        $this->dispatch('imageGeneratorReady');
    }
    
    /**
     * ✅ NUEVO: Listener para actualizar cuenta cuando cambia en el padre
     */
    #[On('accountChanged')]
    public function updateAccount(?int $accountId): void
    {
        $previousAccountId = $this->accountId;
        $this->accountId = $accountId;
        
        Log::info('🔄 Cuenta actualizada en ImageGenerator VIA EVENTO', [
            'previousAccountId' => $previousAccountId,
            'newAccountId' => $accountId,
            'timestamp' => now()->toIso8601String()
        ]);
    }
    
    /**
     * Reglas de validación para generar: permiten prompt vacío en Nano Banana si hay contexto o imágenes de referencia.
     */
    protected function getGenerationRules(): array
    {
        $rules = [
            'promptText'           => 'required|string|min:3',
            'newReferenceImages'   => 'nullable|array',
            'newReferenceImages.*' => 'image|max:10240',
        ];
        if ($this->supportsContextSidebar && $this->contextEnabled) {
            // Solo se relaja si hay texto de contexto real; solo imágenes no es suficiente
            if (trim($this->contextText) !== '') {
                $rules['promptText'] = 'nullable|string';
            }
        }
        return $rules;
    }

    public function generate(): void
{
    $this->validate($this->getGenerationRules());
    $contextIsActive = $this->contextEnabled;
    // Si la caja principal está vacía, el contextText ya fue validado como obligatorio arriba
    $basePrompt = trim($this->promptText) !== '' ? trim($this->promptText) : trim($this->contextText);
    $promptToSend = $basePrompt;
    if ($contextIsActive && trim($this->promptText) !== '' && trim($this->contextText) !== '') {
        $promptToSend .= "\n\nInstrucciones de estilo y contexto: " . trim($this->contextText);
    }
    Log::info('🚀 Iniciando proceso de generación de imagen', [
        'model' => $this->model,
        'prompt' => substr($promptToSend, 0, 50) . '...',
        'ratio' => $this->ratio,
        'count' => $this->count,
        'supportsMultipleImages' => $this->supportsMultipleImages,
        'accountId' => $this->accountId  // ✅ Log de la cuenta que se usará
    ]);
    
    // 1. ACTIVAR INMEDIATAMENTE el spinner
    $this->isGenerating = true;
    $this->results = [];
    
    Log::info('✅ Estado de generación activado', [
        'isGenerating' => $this->isGenerating,
        'resultsCount' => count($this->results)
    ]);
    
    // 2. DISPARAR EVENTO para mostrar spinner en frontend
    $this->dispatch('generationStarted');
    
    Log::info('📡 Evento generationStarted disparado al frontend');
    
    // 3. Incluir imágenes de referencia en el payload para Nano Banana (desde estado o sesión; Livewire no restaura bien el array de uploads)
    $payload = [
        'prompt' => $promptToSend,
        'model' => $this->model,
        'count' => $this->count,
        'ratio' => $this->ratio
    ];
    if ($this->supportsContextSidebar && $this->contextEnabled) {
        $payload['referenceImages'] = $this->referenceImagesData;
    }
    $this->dispatch('startImageGeneration', $payload);

    Log::info('📡 Evento startImageGeneration disparado con datos', [
        'prompt' => substr($promptToSend, 0, 50) . '...',
        'model' => $this->model,
        'count' => $this->count,
        'ratio' => $this->ratio,
        'referenceImagesCount' => count($payload['referenceImages'] ?? [])
    ]);
}

// 4. MÉTODO QUE HACE LA GENERACIÓN REAL
#[On('startImageGeneration')]
public function executeGeneration($data): void
{
    Log::info('🔄 Ejecutando generación real de imagen', [
        'model' => $data['model'],
        'prompt' => substr($data['prompt'], 0, 50) . '...',
        'ratio' => $data['ratio'],
        'count' => $data['count'],
        'timestamp' => now()->toIso8601String()
    ]);
    
    try {
        // ✅ Validar que haya una cuenta seleccionada
        if (!$this->accountId) {
            $errorMessage = 'Debes seleccionar una cuenta antes de generar imágenes';
            $this->addError('promptText', $errorMessage);
            
            $this->dispatch('addErrorToList', 
                message: $errorMessage, 
                type: 'validation', 
                tool: 'image-generator'
            );
            
            $this->dispatch('generationError');
            $this->isGenerating = false;
            return;
        }
        
        // ✅ Validar límite de créditos usando la cuenta del componente
        $this->validateCreditLimit($this->accountId);
       switch ($data['model']) {
        case 'gemini-3-pro-image-preview':
        case 'gemini-3.1-flash-image-preview':
            Log::info('🎨 Generando con Gemini Content Image', ['model' => $data['model']]);
            $this->generarConGeminiContentImage($data);
            break;
        case 'imagen-4-ultra':
            Log::info('🎨 Generando con Imagen 4 Ultra (Replicate)', ['model' => $data['model']]);
            $this->generarConImagen4Ultra($data);
            break;
        case 'flux-kontext-max':
            Log::info('🎨 Generando con Flux-Kontext-Max', ['model' => $data['model']]);
            $this->generarConFluxKontext($data);
            break;
        case 'flux-kontext-pro':
            Log::info('🎨 Generando con Flux-Kontext-Pro', ['model' => $data['model']]);
            $this->generarConFluxKontext($data);
            break;
        case 'flux-pro':
            Log::info('🎨 Generando con Flux Pro 1.1', ['model' => $data['model']]);
            $this->generarConFluxPro($data);
            break;
        case 'flux-ultra':
            Log::info('🎨 Generando con Flux Ultra', ['model' => $data['model']]);
            $this->generarConFluxUltra($data);
            break;
        case 'flux-2-pro':
            Log::info('🎨 Generando con Flux 2 Pro', ['model' => $data['model']]);
            $this->generarConFlux2Pro($data);
            break;
        case 'gpt-image-1':
        case 'gpt-image-1.5':
            Log::info('🎨 Generando con OpenAI', ['model' => $data['model']]);
            $this->generarConOpenAI($data);
            break;
        case 'seedream-4.5':
            Log::info('🎨 Generando con Seedream 4.5', ['model' => $data['model']]);
            $this->generarConSeedream($data);
            break;
        case 'qwen-image':
            Log::info('🎨 Generando con Qwen Image', ['model' => $data['model']]);
            $this->generarConQwen($data);
            break;
        default:
            Log::warning('⚠️ Modelo no reconocido', ['model' => $data['model']]);
            break;
       }

    } catch (\App\Exceptions\CreditLimitExceededException $e) {
        Log::warning('Límite de créditos excedido en Generador de Imágenes', [
            'message' => $e->getMessage(),
            'accountId' => $this->accountId
        ]);
        
        $this->addError('Error inesperado: ' . $e->getMessage(), 'excepción');
        // Enviar error al componente principal
        $this->dispatch('addErrorToList', 
            message: $e->getMessage(), 
            type: 'credit_limit', 
            tool: 'image-generator'
        );
        
        $this->dispatch('generationError');
        $this->isGenerating = false; // Solo en caso de error

    }
     catch (\Exception $e) {
        Log::error('💥 Error en executeGeneration', [
            'model' => $data['model'],
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        
        $errorMessage = 'Error: ' . $e->getMessage();
        $this->addError('promptText', $errorMessage);
        
        // Enviar error al componente principal
        $this->dispatch('addErrorToList', 
            message: $errorMessage, 
            type: 'system', 
            tool: 'image-generator'
        );
        
        $this->dispatch('generationError');
        $this->isGenerating = false; // Solo en caso de error
    }
}

public function generarConGemini25Flash($data): void
{
    Log::info('🎨 Iniciando generación con Gemini 2.5 Flash', [
        'model' => $data['model'],
        'prompt' => substr($data['prompt'], 0, 50) . '...',
        'ratio' => $data['ratio'],
        'count' => $data['count']
    ]);
    
    try {
        $response = GeminiService::generateContentImage(
            prompt: $data['prompt'],
            model: $data['model'],
        );
        
        Log::info('📡 Respuesta de GeminiService::generateContentImage', [
            'model' => $data['model'],
            'success' => $response['success'] ?? false,
            'hasError' => isset($response['error']),
            'predictionsCount' => count($response['data'] ?? []),
            'responseKeys' => array_keys($response)
        ]);

        if (!($response['success'] ?? false)) {
            $errorMessage = $response['error']['message'] ?? 'No se pudo generar la imagen.';
            Log::error('❌ Error en respuesta de Gemini', [
                'model' => $data['model'],
                'error' => $response['error'] ?? 'No error details'
            ]);
            
            $this->addError('promptText', $errorMessage);
            
            $this->dispatch('addErrorToList', 
                message: $errorMessage, 
                type: 'generation', 
                tool: 'image-generator'
            );
            
            $this->dispatch('generationError');
            return;
        }

        // Procesar imágenes...
        $generationId = uniqid('gen_gemini25_');
        $generatedImages = [];
        
        Log::info('🔄 Procesando imágenes generadas por Gemini 2.5 Flash', [
            'generationId' => $generationId,
            'predictionsCount' => count($response['data'] ?? [])
        ]);
        
        $predictions = $response['data'] ?? [];
        foreach ($predictions as $index => $prediction) {
            $base64 = $prediction['base64'] ?? null;
            $mime   = $prediction['mimeType'] ?? 'image/png';

            if (!$base64) {
                Log::warning('⚠️ Predicción sin base64', [
                    'index' => $index,
                    'predictionKeys' => array_keys($prediction)
                ]);
                continue;
            }

            Log::info('🖼️ Procesando imagen Gemini 2.5 Flash', [
                'index' => $index,
                'base64Length' => strlen($base64),
                'generationId' => $generationId
            ]);

            $imageBinary = base64_decode($base64);
            $extension = ($mime === 'image/jpeg') ? 'jpg' : 'png';
            $fileName = 'genesis/output-images/' . now()->format('Ymd_His') . '_gemini25_' . uniqid('img_') . '.' . $extension;
            
            Log::info('☁️ Subiendo imagen Gemini 2.5 Flash a S3', [
                'fileName' => $fileName,
                'imageSize' => strlen($imageBinary)
            ]);
            
            Storage::disk('s3')->put($fileName, $imageBinary);
            $url = Storage::disk('s3')->url($fileName);

            Log::info('✅ Imagen Gemini 2.5 Flash subida exitosamente', [
                'fileName' => $fileName,
                'url' => $url,
                'index' => $index
            ]);

            $imageData = [
                'url' => $url,
                'model' => $this->model,
                'ratio' => $this->ratio,
            ];
            
            $this->results[] = $imageData;
            $generatedImages[] = $imageData;
        }

        Log::info('📊 Resumen de generación Gemini 2.5 Flash', [
            'generationId' => $generationId,
            'totalImages' => count($generatedImages),
            'successfulImages' => count($generatedImages)
        ]);

        if (!empty($generatedImages)) {
            // Registrar el uso de generación
            // Nota: generateContentImage usa 'gemini-2.5-flash-image-preview' por defecto
            // pero puede usar otro modelo si se pasa en $data['model']
            $modelName = $data['model'] ?? 'gemini-2.5-flash-image-preview';
            $this->trackImageGenerationUsage(
                $modelName,
                count($generatedImages),
                'gemini'
            );
            
            Log::info('🎉 Generación Gemini 2.5 Flash completada exitosamente', [
                'generationId' => $generationId,
                'imagesCount' => count($generatedImages)
            ]);
            
            $this->dispatch('addToHistory', 
                type: 'image/generate', 
                images: $generatedImages, 
                generationId: $generationId,
                prompt: $data['prompt'],
                model: $this->getModelDisplayName($data['model']),
                ratio: $data['ratio'],
                count: $data['count']
            );
            
            $this->dispatch('generationCompleted');
            
            Log::info('✅ Eventos de finalización disparados para Gemini 2.5 Flash');
            
        } else {
            Log::warning('⚠️ No se generaron imágenes con Gemini 2.5 Flash', [
                'generationId' => $generationId
            ]);
        }

    } catch (\Exception $e) {
        Log::error('💥 Error en generarConGemini25Flash', [
            'model' => $data['model'],
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        
        $errorMessage = 'Error: ' . $e->getMessage();
        $this->addError('promptText', $errorMessage);
        
        $this->dispatch('addErrorToList', 
            message: $errorMessage, 
            type: 'system', 
            tool: 'image-generator'
        );
        
        $this->dispatch('generationError');
    } finally {
        $this->isGenerating = false;
        Log::info('🏁 Finalizando generarConGemini25Flash', [
            'isGenerating' => $this->isGenerating
        ]);
    }
}

/**
 * Genera imágenes usando Flux-Kontext (Pro o Max)
 */
public function generarConFluxKontext($data): void
{
    try {
              
                
        $modelo = $data['model']; // 'flux-kontext-max' o 'flux-kontext-pro'
        Log::info('🚀 Iniciando generación Flux-Kontext', [
            'model' => $modelo,
            'prompt' => substr($data['prompt'], 0, 50) . '...', // Solo primeros 50 chars
            'ratio' => $data['ratio']
        ]);
   
        $response = FluxService::GenerateImageKontext(
            $modelo,                    
            $data['prompt'],            
            $data['ratio'],            
            null,                       
            false,                     
            null,                       
            2,                          
            'jpeg'                      
        );
        
        Log::info('📝 Respuesta de FluxService::GenerateImageKontext', [
            'model' => $modelo,
            'response' => $response,
            'hasError' => isset($response['error']),
            'hasData' => isset($response['data'])
        ]);
        
        // Verificar si hubo error en la respuesta inicial
        if (isset($response['error'])) {
            $errorMessage = 'Error con Flux-Kontext: ' . $response['error'];
            Log::error('❌ Error en respuesta inicial de Flux', [
                'model' => $modelo,
                'error' => $response['error']
            ]);
            
            $this->addError('promptText', $errorMessage);
            
            $this->dispatch('addErrorToList', 
                message: $errorMessage, 
                type: 'generation', 
                tool: 'image-generator'
            );
            
            $this->dispatch('generationError');
            return;
        }

        // Obtener el ID de generación
        if (!isset($response['data'])) {
            Log::error('❌ Respuesta inesperada de Flux-Kontext', [
                'model' => $modelo,
                'response' => $response
            ]);
            throw new \Exception('Respuesta inesperada de Flux-Kontext');
        }

        $generationId = $response['data'];
        
        Log::info('✅ ID de generación obtenido de Flux', [
            'model' => $modelo,
            'generationId' => $generationId
        ]);
        
        // ✅ EMITIR AL FRONTEND - Flux usa su propio sistema (NO es Replicate)
        $this->dispatch('fluxTaskStarted', 
            generationId: $generationId,
            prompt: $data['prompt'],
            model: $data['model'],
            ratio: $data['ratio'],
            count: $data['count']
        );

        Log::info('✅ Evento fluxTaskStarted disparado para Flux-Kontext', [
            'generationId' => $generationId,
            'model' => $data['model'],
            'eventName' => 'fluxTaskStarted'
        ]);

    } catch (\Exception $e) {
        $errorMessage = 'Error generando con Flux-Kontext: ' . $e->getMessage();
        
        Log::error('💥 Excepción en generarConFluxKontext', [
            'model' => $data['model'] ?? 'unknown',
            'prompt' => substr($data['prompt'] ?? '', 0, 50) . '...',
            'ratio' => $data['ratio'] ?? 'unknown',
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        
        $this->addError('promptText', $errorMessage);
        
        $this->dispatch('addErrorToList', 
            message: $errorMessage, 
            type: 'system', 
            tool: 'image-generator'
        );
        
        $this->dispatch('generationError');
    }
}

    


/**
 * Verifica el estado de generación de Flux-Kontext
 */

#[On('verificarEstadoFluxKontext')]
public function verificarEstadoFluxKontext($generationId, $prompt, $model, $ratio, $count, $pollingUrl = null): void
{
    try {
        Log::info('🔍 Verificando estado desde frontend', [
            'generationId' => $generationId,
            'model' => $model,
            'prompt' => substr($prompt, 0, 50) . '...',
            'ratio' => $ratio,
            'count' => $count,
            'pollingUrl' => $pollingUrl,
            'source' => 'image-generator'
        ]);
        
        // Determinar qué método usar según el modelo
        if ($model === 'flux-2-pro' && !empty($pollingUrl)) {
            // Flux 2 Pro usa polling_url dinámico (región varía: us2, eu2, etc.)
            Log::info('📡 Usando polling_url para flux-2-pro', ['polling_url' => $pollingUrl]);
            $result = FluxService::GetResultFromPollingUrl($pollingUrl);
        } elseif ($model === 'flux-pro') {
            // Flux Pro 1.1 usa el endpoint original
            Log::info('📡 Usando endpoint GetResult para flux-pro', ['model' => $model]);
            $result = FluxService::GetResult($generationId);
        } else {
            // Flux-Kontext y Flux Ultra usan el endpoint Ultra (us1)
            Log::info('📡 Usando endpoint GetResultUltra para flux-kontext/ultra', [
                'model' => $model,
                'generationId' => $generationId
            ]);
            $result = FluxService::GetResultUltra($generationId);
        }
        
        Log::info('📡 Respuesta del FluxService', [
            'generationId' => $generationId,
            'model' => $model,
            'status' => $result['status'] ?? 'unknown',
            'hasData' => isset($result['data']),
            'responseKeys' => array_keys($result)
        ]);
        
        // Crear array de datos para compatibilidad
        $datos = [
            'generationId' => $generationId,
            'prompt' => $prompt,
            'model' => $model,
            'ratio' => $ratio,
            'count' => $count
        ];
        
        switch ($result['status']) {
            case 'complete':
            case 'Ready':
                // ✅ IMAGEN LISTA - Verificar si ya se procesó para evitar duplicados
                if (in_array($generationId, self::$processedGenerationIds)) {
                    Log::info('⏭️ [Flux] Ya procesado, ignorando', ['id' => $generationId]);
                    return;
                }
                
                // Marcar como procesado ANTES de procesar
                self::$processedGenerationIds[] = $generationId;
                
                Log::info('✅ Flux completado', [
                    'id' => $generationId,
                    'model' => $model,
                    'status' => $result['status']
                ]);
                $this->dispatch('fluxCompleted', generationId: $generationId);
                $this->procesarImagen($result['data'], $datos);
                break;
                
            case 'pending':
                // ⏳ AÚN PENDIENTE - EMITIR AL FRONTEND PARA NUEVO DELAY
                Log::info('⏳ Flux aún pendiente', [
                    'id' => $generationId,
                    'model' => $model,
                    'status' => $result['status']
                ]);
                $this->dispatch('fluxStillPending', 
                    generationId: $generationId,
                    prompt: $prompt,
                    model: $model,
                    ratio: $ratio,
                    count: $count,
                    pollingUrl: $pollingUrl
                );
                
                Log::info('🔄 Evento fluxStillPending disparado', [
                    'generationId' => $generationId,
                    'model' => $model,
                    'pollingUrl' => $pollingUrl
                ]);
                break;
                
            case 'failed':
            case 'error':
                // ❌ ERROR
                Log::error('❌ Flux falló', [
                    'id' => $generationId,
                    'model' => $model,
                    'status' => $result['status'],
                    'error' => $result['error'] ?? 'No error details'
                ]);
                $this->isGenerating = false;
                $this->dispatch('generationError');
                break;
                
            default:
                Log::warning('⚠️ Estado desconocido de Flux', [
                    'id' => $generationId,
                    'model' => $model,
                    'status' => $result['status'],
                    'result' => $result
                ]);
                $this->isGenerating = false;
                $this->dispatch('generationError');
                break;
        }
        
    } catch (\Exception $e) {
        Log::error('💥 Error verificando Flux', ['error' => $e->getMessage()]);
        $this->isGenerating = false;
        $this->dispatch('generationError');
    }
}

/**
 * Procesa una imagen completada 
 */
private function procesarImagen(string $imageUrl, array $datos): void
{
    try {
        Log::info('🔄 Procesando imagen completada de Flux', [
            'generationId' => $datos['generationId'],
            'model' => $datos['model'],
            'originalUrl' => $imageUrl,
            'prompt' => substr($datos['prompt'], 0, 50) . '...'
        ]);
        
        // Descargar la imagen desde la URL
        $imageContent = file_get_contents($imageUrl);
        if ($imageContent === false) {
            throw new \Exception('No se pudo descargar la imagen');
        }

        Log::info('📥 Imagen descargada exitosamente', [
            'generationId' => $datos['generationId'],
            'imageSize' => strlen($imageContent),
            'originalUrl' => $imageUrl
        ]);

        // Guardar en S3
        $fileName = 'genesis/output-images/' . now()->format('Ymd_His') . '_flux_' . uniqid('img_') . '.jpg';
        Storage::disk('s3')->put($fileName, $imageContent);
        $finalUrl = Storage::disk('s3')->url($fileName);

        Log::info('☁️ Imagen subida a S3 exitosamente', [
            'generationId' => $datos['generationId'],
            'fileName' => $fileName,
            'finalUrl' => $finalUrl
        ]);

        // Crear datos de la imagen
        $imageData = [
            'url' => $finalUrl,
            'model' => $datos['model'],
            'ratio' => $datos['ratio'],
        ];
        
        $this->results[] = $imageData;

        // Registrar el uso de generación (Flux genera 1 imagen por request)
        $this->trackImageGenerationUsage(
            $datos['model'], // flux-kontext-max, flux-kontext-pro, flux-pro, flux-ultra, flux-2-pro
            1,
            'flux',
            $datos['generationId'] // external_request_id para evitar duplicados
        );

        // Disparar evento de finalización
        $generationId = uniqid('gen_flux_');
        $this->dispatch('addToHistory', 
            type: 'image/generate', 
            images: [$imageData], 
            generationId: $generationId,
            prompt: $datos['prompt'],
            model: $this->getModelDisplayName($datos['model']),
            ratio: $datos['ratio'],
            count: 1 
        );
        
        Log::info('✅ Imagen procesada y agregada al historial', [
            'originalGenerationId' => $datos['generationId'],
            'newGenerationId' => $generationId,
            'model' => $datos['model']
        ]);
        
        $this->dispatch('generationCompleted');
        // fluxCompleted ya se emitió antes de procesar para detener polling inmediatamente
        
    } catch (\Exception $e) {
        Log::error('💥 Error procesando imagen Flux-Kontext', [
            'generationId' => $datos['generationId'],
            'model' => $datos['model'],
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        $errorMessage = 'Error procesando imagen Flux-Kontext: ' . $e->getMessage();
        $this->addError('promptText', $errorMessage);
        
        $this->dispatch('addErrorToList', 
            message: $errorMessage, 
            type: 'system', 
            tool: 'image-generator'
        );
        
        $this->dispatch('generationError');
    } finally {
        $this->isGenerating = false;
    }
}

    /**
     * Genera imágenes usando Flux Pro 1.1
     */
    public function generarConFluxPro($data): void
    {
        Log::info('🎨 Iniciando generación con Flux Pro 1.1', [
            'model' => $data['model'],
            'prompt' => substr($data['prompt'], 0, 50) . '...',
            'ratio' => $data['ratio'],
            'count' => $data['count']
        ]);
        
        try {
            // Determinar dimensiones basadas en la relación de aspecto
            $dimensions = $this->getDimensionsFromRatio($data['ratio']);
            $width = $dimensions['width'];
            $height = $dimensions['height'];

            Log::info('📏 Dimensiones calculadas para Flux Pro 1.1', [
                'ratio' => $data['ratio'],
                'width' => $width,
                'height' => $height
            ]);

            Log::info('🚀 Iniciando generación Flux Pro 1.1', [
                'prompt' => substr($data['prompt'], 0, 50) . '...',
                'width' => $width,
                'height' => $height
            ]);

            $response = FluxService::GenerateImageFlux(
                $data['prompt'],
                $width,
                $height,
                true, // prompt_upsampling
                null, // seed (aleatorio)
                2     // safety_tolerance
            );

            Log::info('📝 Respuesta de FluxService::GenerateImageFlux', [
                'model' => $data['model'],
                'response' => $response,
                'hasError' => isset($response['error']),
                'hasData' => isset($response['data'])
            ]);

            // Verificar si hubo error en la respuesta inicial
            if (isset($response['error'])) {
                $errorMessage = 'Error con Flux Pro 1.1: ' . $response['error'];
                Log::error('❌ Error en respuesta inicial de Flux Pro 1.1', [
                    'model' => $data['model'],
                    'error' => $response['error']
                ]);
                
                $this->addError('promptText', $errorMessage);
                
                $this->dispatch('addErrorToList', 
                    message: $errorMessage, 
                    type: 'generation', 
                    tool: 'image-generator'
                );
                
                $this->dispatch('generationError');
                return;
            }

            // Obtener el ID de generación
            if (!isset($response['data'])) {
                Log::error('❌ Respuesta inesperada de Flux Pro 1.1', [
                    'model' => $data['model'],
                    'response' => $response
                ]);
                throw new \Exception('Respuesta inesperada de Flux Pro 1.1');
            }

            $generationId = $response['data'];
            
            Log::info('✅ ID de generación obtenido de Flux Pro 1.1', [
                'model' => $data['model'],
                'generationId' => $generationId
            ]);
            
            // ✅ EMITIR AL FRONTEND - Flux usa su propio sistema (NO es Replicate)
            $this->dispatch('fluxTaskStarted', 
                generationId: $generationId,
                prompt: $data['prompt'],
                model: $data['model'],
                ratio: $data['ratio'],
                count: $data['count']
            );

            Log::info('✅ Evento fluxTaskStarted disparado para Flux Pro 1.1', [
                'generationId' => $generationId,
                'model' => $data['model'],
                'eventName' => 'fluxTaskStarted'
            ]);

        } catch (\Exception $e) {
            Log::error('💥 Error en generarConFluxPro', [
                'model' => $data['model'],
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $errorMessage = 'Error generando con Flux Pro 1.1: ' . $e->getMessage();
            $this->addError('promptText', $errorMessage);
            
            $this->dispatch('addErrorToList', 
                message: $errorMessage, 
                type: 'system', 
                tool: 'image-generator'
            );
            
            $this->dispatch('generationError');
        }
    }

    /**
     * Genera imágenes usando Flux Ultra
     */
    public function generarConFluxUltra($data): void
    {
        Log::info('🎨 Iniciando generación con Flux Ultra', [
            'model' => $data['model'],
            'prompt' => substr($data['prompt'], 0, 50) . '...',
            'ratio' => $data['ratio'],
            'count' => $data['count']
        ]);
        
        try {
            Log::info('🚀 Iniciando generación Flux Ultra', [
                'prompt' => substr($data['prompt'], 0, 50) . '...',
                'ratio' => $data['ratio']
            ]);

            $response = FluxService::GenerateImageFluxUltra(
                $data['prompt'],
                $data['ratio']
            );

            Log::info('📝 Respuesta de FluxService::GenerateImageFluxUltra', [
                'model' => $data['model'],
                'response' => $response,
                'hasError' => isset($response['error']),
                'hasData' => isset($response['data'])
            ]);

            // Verificar si hubo error en la respuesta inicial
            if (isset($response['error'])) {
                $errorMessage = 'Error con Flux Ultra: ' . $response['error'];
                Log::error('❌ Error en respuesta inicial de Flux Ultra', [
                    'model' => $data['model'],
                    'error' => $response['error']
                ]);
                
                $this->addError('promptText', $errorMessage);
                
                $this->dispatch('addErrorToList', 
                    message: $errorMessage, 
                    type: 'generation', 
                    tool: 'image-generator'
                );
                
                $this->dispatch('generationError');
                return;
            }

            // Obtener el ID de generación
            if (!isset($response['data'])) {
                Log::error('❌ Respuesta inesperada de Flux Ultra', [
                    'model' => $data['model'],
                    'response' => $response
                ]);
                throw new \Exception('Respuesta inesperada de Flux Ultra');
            }

            $generationId = $response['data'];
            
            Log::info('✅ ID de generación obtenido de Flux Ultra', [
                'model' => $data['model'],
                'generationId' => $generationId
            ]);
            
            // ✅ EMITIR AL FRONTEND - Flux usa su propio sistema (NO es Replicate)
            $this->dispatch('fluxTaskStarted', 
                generationId: $generationId,
                prompt: $data['prompt'],
                model: $data['model'],
                ratio: $data['ratio'],
                count: $data['count']
            );

            Log::info('✅ Evento fluxTaskStarted disparado para Flux Ultra', [
                'generationId' => $generationId,
                'model' => $data['model'],
                'eventName' => 'fluxTaskStarted'
            ]);

        } catch (\Exception $e) {
            Log::error('💥 Error en generarConFluxUltra', [
                'model' => $data['model'],
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $errorMessage = 'Error generando con Flux Ultra: ' . $e->getMessage();
            $this->addError('promptText', $errorMessage);
            
            $this->dispatch('addErrorToList', 
                message: $errorMessage, 
                type: 'system', 
                tool: 'image-generator'
            );
            
            $this->dispatch('generationError');
        }
    }

    /**
     * Genera imágenes usando FLUX.2 Pro
     * Soporta generación y edición con multi-referencia
     */
    public function generarConFlux2Pro($data): void
    {
        Log::info('🎨 Iniciando generación con Flux 2 Pro', [
            'model' => $data['model'],
            'prompt' => substr($data['prompt'], 0, 50) . '...',
            'ratio' => $data['ratio'],
            'count' => $data['count']
        ]);
        
        try {
            // Obtener dimensiones del ratio (igual que Flux Pro 1.1)
            $dimensions = $this->getDimensionsFromRatio($data['ratio']);
            
            $response = FluxService::GenerateOrEditImageFlux2Pro(
                prompt: $data['prompt'],
                width: $dimensions['width'],
                height: $dimensions['height']
            );

            Log::info('📝 Respuesta de FluxService::GenerateOrEditImageFlux2Pro', [
                'model' => $data['model'],
                'response' => $response,
                'hasError' => isset($response['error']),
                'hasData' => isset($response['data'])
            ]);

            // Verificar si hubo error en la respuesta inicial
            if (isset($response['error'])) {
                $errorMessage = 'Error con Flux 2 Pro: ' . (is_array($response['error']) ? json_encode($response['error']) : $response['error']);
                Log::error('❌ Error en respuesta de Flux 2 Pro', [
                    'model' => $data['model'],
                    'error' => $response['error']
                ]);
                
                $this->addError('promptText', $errorMessage);
                
                $this->dispatch('addErrorToList', 
                    message: $errorMessage, 
                    type: 'generation', 
                    tool: 'image-generator'
                );
                
                $this->isGenerating = false;
                $this->dispatch('generationError');
                return;
            }

            // Obtener el ID de generación y polling_url
            if (!isset($response['data'])) {
                Log::error('❌ Respuesta inesperada de Flux 2 Pro', [
                    'model' => $data['model'],
                    'response' => $response
                ]);
                throw new \Exception('Respuesta inesperada de Flux 2 Pro');
            }

            $generationId = $response['data'];
            $pollingUrl = $response['polling_url'] ?? null;
            
            Log::info('✅ ID de generación obtenido de Flux 2 Pro', [
                'model' => $data['model'],
                'generationId' => $generationId,
                'polling_url' => $pollingUrl
            ]);
            
            // ✅ EMITIR AL FRONTEND - Flux usa su propio sistema
            // Incluimos polling_url porque Flux 2 Pro varía la región (us2, eu2, etc.)
            $this->dispatch('fluxTaskStarted', 
                generationId: $generationId,
                prompt: $data['prompt'],
                model: $data['model'],
                ratio: $data['ratio'],
                count: $data['count'],
                pollingUrl: $pollingUrl
            );

            Log::info('✅ Evento fluxTaskStarted disparado para Flux 2 Pro', [
                'generationId' => $generationId,
                'model' => $data['model'],
                'polling_url' => $pollingUrl
            ]);

        } catch (\Exception $e) {
            Log::error('💥 Error en generarConFlux2Pro', [
                'model' => $data['model'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $errorMessage = 'Error generando con Flux 2 Pro: ' . $e->getMessage();
            $this->addError('promptText', $errorMessage);
            
            $this->dispatch('addErrorToList', 
                message: $errorMessage, 
                type: 'system', 
                tool: 'image-generator'
            );
            
            $this->isGenerating = false;
            $this->dispatch('generationError');
        }
    }

    /**
     * Método para convertir ratio a dimensiones (para Flux Pro 1.1)
     */
    private function getDimensionsFromRatio($ratio)
    {
        Log::info('📐 Convirtiendo ratio a dimensiones para Flux Pro 1.1', [
            'inputRatio' => $ratio
        ]);
        
        $dimensions = match($ratio) {
            '1:1' => ['width' => 1024, 'height' => 1024],
            '4:3' => ['width' => 1024, 'height' => 768],
            '3:4' => ['width' => 768, 'height' => 1024],
            '16:9' => ['width' => 1024, 'height' => 576],
            '9:16' => ['width' => 576, 'height' => 1024],
            default => ['width' => 1024, 'height' => 1024]
        };
        
        Log::info('✅ Dimensiones calculadas para Flux Pro 1.1', [
            'inputRatio' => $ratio,
            'outputDimensions' => $dimensions
        ]);
        
        return $dimensions;
    }

    /**
     * Método auxiliar para convertir nuestro ratio a los tamaños de OpenAI
     */
    private function mapearAspectRatioAOpenAI($ratio)
    {
        Log::info('📐 Mapeando ratio a formato OpenAI', [
            'inputRatio' => $ratio
        ]);
        
        // Para gpt-image-1, mapear nuestros ratios a los tamaños soportados
        $size = match($ratio) {
            '1:1' => '1024x1024', // Cuadrado
            '16:9', '4:3' => '1536x1024', // Horizontal/Landscape
            '9:16', '3:4' => '1024x1536', // Vertical/Portrait
            default => '1024x1024' // Por defecto cuadrado
        };
        
        Log::info('✅ Ratio mapeado a formato OpenAI', [
            'inputRatio' => $ratio,
            'outputSize' => $size
        ]);
        
        return $size;
    }

    /**
     * Genera imágenes usando OpenAI DALL-E
     */
    public function generarConOpenAI($data): void
    {
        Log::info('🎨 Iniciando generación con OpenAI DALL-E', [
            'model' => $data['model'],
            'prompt' => substr($data['prompt'], 0, 50) . '...',
            'ratio' => $data['ratio'],
            'count' => $data['count']
        ]);
        
        try {
            Log::info('🚀 Iniciando generación OpenAI', [
                'model' => $data['model'],
                'prompt' => substr($data['prompt'], 0, 50) . '...',
                'count' => $data['count'],
                'ratio' => $data['ratio']
            ]);

            // Establecer tiempo de ejecución máximo para esta operación
            set_time_limit(180); // 3 minutos
            
            // Mapear el aspect ratio a formato OpenAI usando el ratio actual
            $aspecto = $this->mapearAspectRatioAOpenAI($data['ratio']);
            $quality = $this->calidadImagen;
            
            Log::info('📏 Mapeo de ratio OpenAI', [
                'ratio_original' => $data['ratio'],
                'size_openai' => $aspecto,
                'quality' => $quality
            ]);
            
            // Llamar al servicio de OpenAI (modelo dinámico: gpt-image-1, gpt-image-1.5, etc.)
            $response = OpenAiService::generateImage(
                $data['prompt'], 
                $data['model'], 
                $aspecto, 
                $data['count'], 
                null, 
                null, 
                $quality
            );
            
            Log::info('📡 Respuesta de OpenAiService::generateImage', [
                'model' => $data['model'],
                'hasError' => isset($response['error']),
                'hasData' => isset($response['data']),
                'dataCount' => count($response['data'] ?? []),
                'hasUsage' => isset($response['usage']),
                'responseKeys' => array_keys($response)
            ]);
            
            // Registrar el uso de tokens si está disponible
            if (!isset($response['error']) && isset($response['usage'])) {
                $usage = $response['usage'];
                // ✅ Usar accountId del componente
                $userId = Auth::id();
                
                if ($userId) {
                    // Extraer tokens de la respuesta de OpenAI
                    $inputTokens = $usage['input_tokens'] ?? $usage['prompt_tokens'] ?? 0;
                    $outputTokens = $usage['output_tokens'] ?? $usage['completion_tokens'] ?? 0;
                    
                    // Contar imágenes realmente generadas
                    $imagesGenerated = count($response['data'] ?? []);
                    $imagesRequested = $data['count'] ?? 1;
                    
                    Log::info('📊 Tokens extraídos de OpenAI Image Generation', [
                        'account_id' => $this->accountId,
                        'user_id' => $userId,
                        'inputTokens' => $inputTokens,
                        'outputTokens' => $outputTokens,
                        'imagesRequested' => $imagesRequested,
                        'imagesGenerated' => $imagesGenerated,
                        'note' => 'Los tokens de OpenAI ya reflejan el costo total de todas las imágenes generadas',
                        'usage' => $usage
                    ]);
                    
                    // Registrar el uso solo si hay tokens
                    // IMPORTANTE: Los tokens de OpenAI ya incluyen el costo de todas las imágenes generadas
                    // Si se generaron 4 imágenes, los tokens ya reflejan el costo de las 4
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
                                'Image Generator', // request_type
                                null, // external_request_id
                                null, // generated_id (no hay Generated para imágenes simples)
                                null, // step
                                'openai' // service_type
                            );
                            Log::info('✅ Uso registrado exitosamente para OpenAI Image Generation', [
                                'imagesGenerated' => $imagesGenerated,
                                'tokensUsed' => $inputTokens + $outputTokens
                            ]);
                        } catch (\Exception $e) {
                            Log::error('❌ Error al registrar uso de OpenAI Image Generation', [
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ]);
                        }
                    } else {
                        Log::warning('⚠️ No se encontraron tokens en usage de OpenAI Image Generation', [
                            'usage' => $usage,
                            'imagesGenerated' => $imagesGenerated
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
                $errorMessage = 'Error generando imagen con OpenAI: ' . $response['error'];
                Log::error('❌ Error en respuesta de OpenAI', [
                    'model' => $data['model'],
                    'error' => $response['error']
                ]);
                
                $this->addError('promptText', $errorMessage);
                
                $this->dispatch('addErrorToList', 
                    message: $errorMessage, 
                    type: 'generation', 
                    tool: 'image-generator'
                );
                
                $this->dispatch('generationError');
                return;
            }
            
            // Procesar respuesta de generación
            $generatedImages = [];
            $generationId = uniqid('gen_openai_');
            
            Log::info('🔄 Procesando imágenes generadas por OpenAI', [
                'generationId' => $generationId,
                'dataCount' => count($response['data'] ?? [])
            ]);
            
            foreach ($response['data'] as $index => $image) {
                if (isset($image['b64_json'])) {
                    $imageBase64 = $image['b64_json'];
                    $mimeType = 'image/jpeg';
                    
                    Log::info('🖼️ Procesando imagen OpenAI (base64)', [
                        'index' => $index,
                        'base64Length' => strlen($imageBase64),
                        'generationId' => $generationId
                    ]);
                    
                    // Guardar la imagen en S3
                    $imageBinary = base64_decode($imageBase64);
                    $fileName = 'genesis/output-images/' . now()->format('Ymd_His') . '_openai_' . uniqid('img_') . '.jpg';
                    
                    Log::info('☁️ Subiendo imagen OpenAI a S3', [
                        'fileName' => $fileName,
                        'imageSize' => strlen($imageBinary)
                    ]);
                    
                    Storage::disk('s3')->put($fileName, $imageBinary);
                    $url = Storage::disk('s3')->url($fileName);
                    
                    Log::info('✅ Imagen OpenAI subida exitosamente', [
                        'fileName' => $fileName,
                        'url' => $url,
                        'index' => $index
                    ]);
                    
                    $imageData = [
                        'url' => $url,
                        'model' => $this->model,
                        'ratio' => $this->ratio,
                    ];
                    
                    $this->results[] = $imageData;
                    $generatedImages[] = $imageData;
                    
                } else if (isset($image['url'])) {
                    // Si es una URL directa
                    Log::info('🖼️ Procesando imagen OpenAI (URL directa)', [
                        'index' => $index,
                        'url' => $image['url'],
                        'generationId' => $generationId
                    ]);
                    
                    $imageData = [
                        'url' => $image['url'],
                        'model' => $this->model,
                        'ratio' => $this->ratio,
                    ];
                    
                    $this->results[] = $imageData;
                    $generatedImages[] = $imageData;
                } else {
                    Log::warning('⚠️ Imagen OpenAI sin formato reconocido', [
                        'index' => $index,
                        'imageKeys' => array_keys($image)
                    ]);
                }
            }
            
            Log::info('📊 Resumen de generación OpenAI', [
                'generationId' => $generationId,
                'totalImages' => count($generatedImages),
                'successfulImages' => count($generatedImages)
            ]);
            
            if (!empty($generatedImages)) {
                Log::info('🎉 Generación OpenAI completada exitosamente', [
                    'generationId' => $generationId,
                    'imagesCount' => count($generatedImages)
                ]);
                
                $this->dispatch('addToHistory', 
                    type: 'image/generate', 
                    images: $generatedImages, 
                    generationId: $generationId,
                    prompt: $data['prompt'],
                    model: $this->getModelDisplayName($data['model']),
                    ratio: $data['ratio'],
                    count: $data['count']
                );
                
                $this->dispatch('generationCompleted');
                
                Log::info('✅ Eventos de finalización disparados para OpenAI');
                
                Log::info('Imágenes generadas con OpenAI: ' . count($generatedImages));
            } else {
                Log::warning('⚠️ No se generaron imágenes con OpenAI', [
                    'generationId' => $generationId
                ]);
                
                $this->addError('promptText', 'No se pudieron generar imágenes con OpenAI.');
                $this->dispatch('generationError');
            }

        } catch (\Exception $e) {
            Log::error('💥 Error en generarConOpenAI', [
                'model' => $data['model'],
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $errorMessage = 'Error generando con OpenAI: ' . $e->getMessage();
            $this->addError('promptText', $errorMessage);
            
            $this->dispatch('addErrorToList', 
                message: $errorMessage, 
                type: 'system', 
                tool: 'image-generator'
            );
            
            $this->dispatch('generationError');
        } finally {
            $this->isGenerating = false;
            Log::info('🏁 Finalizando generarConOpenAI', [
                'isGenerating' => $this->isGenerating
            ]);
        }
    }

    /**
     * Genera imágenes usando Seedream 4.5 (Bytedance via Replicate)
     */
    public function generarConSeedream($data): void
    {
        Log::info('🎨 Iniciando generación con Seedream 4.5', [
            'model' => $data['model'],
            'prompt' => substr($data['prompt'], 0, 50) . '...',
            'ratio' => $data['ratio'],
            'count' => $data['count']
        ]);
        
        try {
            // Llamar al servicio Bytedance
            $response = BytedanceService::generateImageSeedream(
                prompt: $data['prompt'],
                aspectRatio: $data['ratio'],
                seed: null
            );

            Log::info('📝 Respuesta de BytedanceService::generateImageSeedream', [
                'model' => $data['model'],
                'response' => $response,
                'hasError' => isset($response['error']),
                'success' => $response['success'] ?? false
            ]);

            if (!($response['success'] ?? false)) {
                $errorMessage = 'Error con Seedream 4.5: ' . ($response['error'] ?? 'Error desconocido');
                Log::error('❌ Error en respuesta de Seedream', [
                    'model' => $data['model'],
                    'error' => $response['error'] ?? 'No error details'
                ]);
                
                $this->addError('promptText', $errorMessage);
                
                $this->dispatch('addErrorToList', 
                    message: $errorMessage, 
                    type: 'generation', 
                    tool: 'image-generator'
                );
                
                $this->isGenerating = false;  // ✅ Importante: resetear estado antes de dispatch
                $this->dispatch('generationError');
                return;
            }

            // Obtener el ID de predicción para polling
            $generationId = $response['prediction_id'];
            
            Log::info('✅ ID de predicción obtenido de Seedream', [
                'model' => $data['model'],
                'generationId' => $generationId
            ]);
            
            // ✅ EMITIR AL FRONTEND - Evento genérico de Replicate
            $this->dispatch('replicateTaskStarted', 
                generationId: $generationId,
                prompt: $data['prompt'],
                model: $data['model'],
                ratio: $data['ratio'],
                count: $data['count'],
                replicateType: 'seedream'
            );

            Log::info('✅ Evento replicateTaskStarted disparado para Seedream', [
                'generationId' => $generationId,
                'model' => $data['model']
            ]);

        } catch (\Exception $e) {
            Log::error('💥 Error en generarConSeedream', [
                'model' => $data['model'],
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $errorMessage = 'Error generando con Seedream 4.5: ' . $e->getMessage();
            $this->addError('promptText', $errorMessage);
            
            $this->dispatch('addErrorToList', 
                message: $errorMessage, 
                type: 'system', 
                tool: 'image-generator'
            );
            
            $this->isGenerating = false;  // ✅ Importante: resetear estado antes de dispatch
            $this->dispatch('generationError');
        }
    }

    /**
     * Genera imágenes usando Gemini generateContent (Nano Banana Pro / Nano Banana 2).
     * Modelos: gemini-3-pro-image-preview, gemini-3.1-flash-image-preview.
     */
    public function generarConGeminiContentImage($data): void
    {
        Log::info('🎨 Iniciando generación con Gemini Content Image', [
            'model' => $data['model'],
            'prompt' => substr($data['prompt'], 0, 50) . '...',
            'ratio' => $data['ratio'],
            'resolution' => $this->resolutionNanoBanana
        ]);

        try {
            set_time_limit(180);
            $aspectRatio = $data['ratio']; // "1:1", "16:9", etc.
            $imageSize = ($data['model'] === 'gemini-3-pro-image-preview') ? $this->resolutionNanoBanana : '1K';

            $prompt = $data['prompt'];

            $files = $data['referenceImages'] ?? [];

            Log::info('📎 Imágenes de referencia para Gemini', ['count' => count($files)]);

            $response = GeminiService::generateContentImage(
                $prompt,
                $files,
                $data['model'],
                $aspectRatio,
                $imageSize
            );

            Log::info('📡 Respuesta de GeminiService::generateContentImage', [
                'model' => $data['model'],
                'success' => $response['success'] ?? false,
                'hasError' => isset($response['error']),
                'dataCount' => isset($response['data']) ? count($response['data']) : 0
            ]);

            if (!($response['success'] ?? false)) {
                $errorMessage = 'Error con Gemini: ' . ($response['error']['message'] ?? 'Error desconocido');
                Log::error('❌ Error en respuesta de Gemini Content Image', [
                    'model' => $data['model'],
                    'error' => $response['error'] ?? null
                ]);
                $this->addError('promptText', $errorMessage);
                $this->dispatch('addErrorToList', message: $errorMessage, type: 'generation', tool: 'image-generator');
                $this->dispatch('generationError');
                $this->isGenerating = false;
                return;
            }

            $images = $response['data'] ?? [];
            $usageMetadata = $response['usageMetadata'] ?? null;
            $generationId = uniqid('gen_gemini_');
            $generatedImages = [];

            foreach ($images as $index => $img) {
                $base64 = $img['base64'] ?? null;
                if (!$base64) continue;
                $mimeType = $img['mimeType'] ?? 'image/png';
                $ext = (strpos($mimeType, 'png') !== false) ? 'png' : 'jpg';
                $imageBinary = base64_decode($base64);
                $fileName = 'genesis/output-images/' . now()->format('Ymd_His') . '_gemini_' . uniqid('img_') . '.' . $ext;
                Storage::disk('s3')->put($fileName, $imageBinary);
                $url = Storage::disk('s3')->url($fileName);
                $imageData = ['url' => $url, 'model' => $this->model, 'ratio' => $this->ratio];
                $this->results[] = $imageData;
                $generatedImages[] = $imageData;
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
                                'Image Generator',
                                null,
                                null,
                                null,
                                'gemini'
                            );
                        } catch (\Exception $e) {
                            Log::error('Error al registrar uso Gemini Content Image', ['error' => $e->getMessage()]);
                        }
                    }
                } else {
                    $this->trackImageGenerationUsage($data['model'], count($generatedImages), 'gemini');
                }

                $this->dispatch('addToHistory',
                    type: 'image/generate',
                    images: $generatedImages,
                    generationId: $generationId,
                    prompt: $data['prompt'],
                    model: $this->getModelDisplayName($data['model']),
                    ratio: $data['ratio'],
                    count: count($generatedImages)
                );
                $this->dispatch('generationCompleted');
            } else {
                $this->addError('promptText', 'No se generaron imágenes.');
                $this->dispatch('generationError');
            }
        } catch (\Exception $e) {
            Log::error('💥 Error en generarConGeminiContentImage', [
                'model' => $data['model'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->addError('promptText', 'Error: ' . $e->getMessage());
            $this->dispatch('addErrorToList', message: $e->getMessage(), type: 'system', tool: 'image-generator');
            $this->dispatch('generationError');
        } finally {
            $this->isGenerating = false;
        }
    }

    /**
     * Genera imágenes usando Imagen 4 Ultra (Google via Replicate)
     * Máxima calidad para detalles finos y tipografía precisa
     */
    public function generarConImagen4Ultra($data): void
    {
        Log::info('🎨 Iniciando generación con Imagen 4 Ultra', [
            'model' => $data['model'],
            'prompt' => substr($data['prompt'], 0, 50) . '...',
            'ratio' => $data['ratio'],
            'count' => $data['count']
        ]);
        
        try {
            // Llamar al servicio Google (Replicate) - solo generación
            $response = GoogleService::generateImagen4Ultra(
                prompt: $data['prompt'],
                aspectRatio: $data['ratio']
            );

            Log::info('📝 Respuesta de GoogleService::generateImagen4Ultra', [
                'model' => $data['model'],
                'response' => $response,
                'hasError' => isset($response['error']),
                'success' => $response['success'] ?? false
            ]);

            if (!($response['success'] ?? false)) {
                $errorMessage = 'Error con Imagen 4 Ultra: ' . ($response['error'] ?? 'Error desconocido');
                Log::error('❌ Error en respuesta de Imagen 4 Ultra', [
                    'model' => $data['model'],
                    'error' => $response['error'] ?? 'No error details'
                ]);
                
                $this->addError('promptText', $errorMessage);
                
                $this->dispatch('addErrorToList', 
                    message: $errorMessage, 
                    type: 'generation', 
                    tool: 'image-generator'
                );
                
                $this->isGenerating = false;
                $this->dispatch('generationError');
                return;
            }

            // Obtener el ID de predicción para polling
            $generationId = $response['prediction_id'];
            
            Log::info('✅ ID de predicción obtenido de Imagen 4 Ultra', [
                'model' => $data['model'],
                'generationId' => $generationId
            ]);
            
            // ✅ EMITIR AL FRONTEND - Evento genérico de Replicate
            $this->dispatch('replicateTaskStarted', 
                generationId: $generationId,
                prompt: $data['prompt'],
                model: $data['model'],
                ratio: $data['ratio'],
                count: $data['count'],
                replicateType: 'imagen-4-ultra'
            );

            Log::info('✅ Evento replicateTaskStarted disparado para Imagen 4 Ultra', [
                'generationId' => $generationId,
                'model' => $data['model']
            ]);

        } catch (\Exception $e) {
            Log::error('💥 Error en generarConImagen4Ultra', [
                'model' => $data['model'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $errorMessage = 'Error generando con Imagen 4 Ultra: ' . $e->getMessage();
            $this->addError('promptText', $errorMessage);
            
            $this->dispatch('addErrorToList', 
                message: $errorMessage, 
                type: 'system', 
                tool: 'image-generator'
            );
            
            $this->isGenerating = false;
            $this->dispatch('generationError');
        }
    }

    /**
     * Genera imágenes usando Qwen Image (via Replicate)
     * Excelente para imágenes con texto renderizado
     */
    public function generarConQwen($data): void
    {
        Log::info('🎨 Iniciando generación con Qwen Image', [
            'model' => $data['model'],
            'prompt' => substr($data['prompt'], 0, 50) . '...',
            'ratio' => $data['ratio'],
            'count' => $data['count']
        ]);
        
        try {
            // Llamar al servicio Qwen
            $response = QwenService::generateImageQwen(
                prompt: $data['prompt'],
                aspectRatio: $data['ratio']
            );

            Log::info('📝 Respuesta de QwenService::generateImageQwen', [
                'model' => $data['model'],
                'response' => $response,
                'hasError' => isset($response['error']),
                'success' => $response['success'] ?? false
            ]);

            if (!($response['success'] ?? false)) {
                $errorMessage = 'Error con Qwen Image: ' . ($response['error'] ?? 'Error desconocido');
                Log::error('❌ Error en respuesta de Qwen', [
                    'model' => $data['model'],
                    'error' => $response['error'] ?? 'No error details'
                ]);
                
                $this->addError('promptText', $errorMessage);
                
                $this->dispatch('addErrorToList', 
                    message: $errorMessage, 
                    type: 'generation', 
                    tool: 'image-generator'
                );
                
                $this->isGenerating = false;
                $this->dispatch('generationError');
                return;
            }

            // Obtener el ID de predicción para polling
            $generationId = $response['prediction_id'];
            
            Log::info('✅ ID de predicción obtenido de Qwen', [
                'model' => $data['model'],
                'generationId' => $generationId
            ]);
            
            // ✅ EMITIR AL FRONTEND - Evento genérico de Replicate
            $this->dispatch('replicateTaskStarted', 
                generationId: $generationId,
                prompt: $data['prompt'],
                model: $data['model'],
                ratio: $data['ratio'],
                count: $data['count'],
                replicateType: 'qwen'
            );

            Log::info('✅ Evento replicateTaskStarted disparado para Qwen', [
                'generationId' => $generationId,
                'model' => $data['model']
            ]);

        } catch (\Exception $e) {
            Log::error('💥 Error en generarConQwen', [
                'model' => $data['model'],
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $errorMessage = 'Error generando con Qwen Image: ' . $e->getMessage();
            $this->addError('promptText', $errorMessage);
            
            $this->dispatch('addErrorToList', 
                message: $errorMessage, 
                type: 'system', 
                tool: 'image-generator'
            );
            
            $this->isGenerating = false;
            $this->dispatch('generationError');
        }
    }

    /**
     * Verifica el estado de generación de Seedream 4.5
     */
    #[On('verificarEstadoSeedream')]
    public function verificarEstadoSeedream($generationId, $prompt, $model, $ratio, $count): void
    {
        try {
            Log::info('🔍 Verificando estado de Seedream desde frontend', [
                'generationId' => $generationId,
                'model' => $model
            ]);
            
            // Consultar estado usando el servicio base de Replicate
            $result = BytedanceService::getPredictionStatus($generationId);
            
            Log::info('📡 Respuesta de BytedanceService::getPredictionStatus', [
                'generationId' => $generationId,
                'status' => $result['status'] ?? 'unknown',
                'hasOutput' => isset($result['output'])
            ]);
            
            $datos = [
                'generationId' => $generationId,
                'prompt' => $prompt,
                'model' => $model,
                'ratio' => $ratio,
                'count' => $count
            ];
            
            $status = $result['status'] ?? 'unknown';
            
            switch ($status) {
                case 'succeeded':
                    // ✅ IMAGEN LISTA
                    Log::info('✅ Seedream completado', ['id' => $generationId]);
                    $this->procesarImagenSeedream($result['output'], $datos);
                    break;
                    
                case 'starting':
                case 'processing':
                    // ⏳ AÚN PENDIENTE
                    Log::info('⏳ Seedream aún pendiente', [
                        'id' => $generationId,
                        'status' => $status
                    ]);
                    $this->dispatch('replicateStillPending', 
                        generationId: $generationId,
                        prompt: $prompt,
                        model: $model,
                        ratio: $ratio,
                        count: $count,
                        replicateType: 'seedream'
                    );
                    break;
                    
                case 'failed':
                    // ❌ ERROR
                    Log::error('❌ Seedream falló', [
                        'id' => $generationId,
                        'error' => $result['error'] ?? 'No error details'
                    ]);
                    $this->isGenerating = false;
                    $this->dispatch('generationError');
                    break;
                    
                default:
                    Log::warning('⚠️ Estado desconocido de Seedream', [
                        'id' => $generationId,
                        'status' => $status
                    ]);
                    $this->isGenerating = false;
                    $this->dispatch('generationError');
                    break;
            }
            
        } catch (\Exception $e) {
            Log::error('💥 Error verificando Seedream', ['error' => $e->getMessage()]);
            $this->isGenerating = false;
            $this->dispatch('generationError');
        }
    }

    /**
     * Procesa una imagen completada de Seedream
     */
    private function procesarImagenSeedream($output, array $datos): void
    {
        try {
            Log::info('🔄 Procesando imagen completada de Seedream', [
                'generationId' => $datos['generationId'],
                'model' => $datos['model'],
                'output' => $output
            ]);
            
            // Seedream devuelve un array de URLs o una URL única
            $imageUrls = is_array($output) ? $output : [$output];
            $generatedImages = [];
            
            foreach ($imageUrls as $index => $imageUrl) {
                if (empty($imageUrl)) continue;
                
                // Descargar la imagen desde la URL
                $imageContent = file_get_contents($imageUrl);
                if ($imageContent === false) {
                    Log::warning('⚠️ No se pudo descargar imagen de Seedream', [
                        'index' => $index,
                        'url' => $imageUrl
                    ]);
                    continue;
                }

                Log::info('📥 Imagen Seedream descargada exitosamente', [
                    'generationId' => $datos['generationId'],
                    'imageSize' => strlen($imageContent),
                    'index' => $index
                ]);

                // Guardar en S3
                $fileName = 'genesis/output-images/' . now()->format('Ymd_His') . '_seedream_' . uniqid('img_') . '.png';
                Storage::disk('s3')->put($fileName, $imageContent);
                $finalUrl = Storage::disk('s3')->url($fileName);

                Log::info('☁️ Imagen Seedream subida a S3 exitosamente', [
                    'generationId' => $datos['generationId'],
                    'fileName' => $fileName,
                    'finalUrl' => $finalUrl
                ]);

                $imageData = [
                    'url' => $finalUrl,
                    'model' => $datos['model'],
                    'ratio' => $datos['ratio'],
                ];
                
                $this->results[] = $imageData;
                $generatedImages[] = $imageData;
            }

            if (!empty($generatedImages)) {
                // Registrar el uso de generación
                $this->trackImageGenerationUsage(
                    'seedream-4.5',
                    count($generatedImages),
                    'replicate',
                    $datos['generationId'] // external_request_id para evitar duplicados
                );
                
                // Disparar evento de finalización
                $generationId = uniqid('gen_seedream_');
                $this->dispatch('addToHistory', 
                    type: 'image/generate', 
                    images: $generatedImages, 
                    generationId: $generationId,
                    prompt: $datos['prompt'],
                    model: $this->getModelDisplayName($datos['model']),
                    ratio: $datos['ratio'],
                    count: count($generatedImages)
                );
                
                Log::info('✅ Imagen Seedream procesada y agregada al historial', [
                    'originalGenerationId' => $datos['generationId'],
                    'newGenerationId' => $generationId,
                    'imagesCount' => count($generatedImages)
                ]);
                
                $this->dispatch('generationCompleted');
                // Marcar el ID original como completado para detener el polling
                $this->dispatch('replicateCompleted', 
                    generationId: $datos['generationId'],
                    replicateType: 'seedream'
                );
            } else {
                throw new \Exception('No se pudieron procesar las imágenes de Seedream');
            }
            
        } catch (\Exception $e) {
            Log::error('💥 Error procesando imagen Seedream', [
                'generationId' => $datos['generationId'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $errorMessage = 'Error procesando imagen Seedream: ' . $e->getMessage();
            $this->addError('promptText', $errorMessage);
            
            $this->dispatch('addErrorToList', 
                message: $errorMessage, 
                type: 'system', 
                tool: 'image-generator'
            );
            
            $this->dispatch('generationError');
        } finally {
            $this->isGenerating = false;
        }
    }

    /**
     * ========== MÉTODO GENÉRICO PARA VERIFICAR ESTADO DE REPLICATE ==========
     * Funciona SOLO para modelos de la API de Replicate (Seedream, Kling, Veo3.1, etc.)
     * NO incluye Flux (Flux usa su propio servicio FluxService)
     * 
     * El parámetro replicateType determina qué servicio usar:
     * - 'seedream' → BytedanceService
     * - 'kling' → KwaivgiService (futuro)
     * - 'veo31' → GoogleService (futuro)
     */
    #[On('verificarEstadoReplicate')]
    public function verificarEstadoReplicate($generationId, $prompt, $model, $ratio, $count, $replicateType): void
    {
        try {
            Log::info('🔍 [Replicate] Verificando estado', [
                'generationId' => $generationId,
                'model' => $model,
                'replicateType' => $replicateType
            ]);
            
            // Obtener estado - TODOS los modelos de Replicate usan el mismo método base
            $result = $this->obtenerEstadoReplicate($generationId, $replicateType);
            
            $datos = [
                'generationId' => $generationId,
                'prompt' => $prompt,
                'model' => $model,
                'ratio' => $ratio,
                'count' => $count,
                'replicateType' => $replicateType
            ];
            
            // Replicate siempre usa estos estados: 'starting', 'processing', 'succeeded', 'failed'
            $status = $result['status'] ?? 'unknown';
            
            if ($status === 'succeeded') {
                // ✅ Verificar si ya se procesó para evitar duplicados
                if (in_array($generationId, self::$processedGenerationIds)) {
                    Log::info('⏭️ [Replicate] Ya procesado, ignorando', ['id' => $generationId]);
                    return;
                }
                
                // Marcar como procesado ANTES de procesar
                self::$processedGenerationIds[] = $generationId;
                
                Log::info('✅ [Replicate] Completado', ['id' => $generationId, 'type' => $replicateType]);
                
                // Emitir evento ANTES de procesar para detener polling
                $this->dispatch('replicateCompleted', generationId: $generationId);
                
                $this->procesarResultadoReplicate($result, $datos);
            } elseif (in_array($status, ['starting', 'processing'])) {
                Log::info('⏳ [Replicate] Aún pendiente', ['id' => $generationId, 'type' => $replicateType, 'status' => $status]);
                $this->dispatch('replicateStillPending', 
                    generationId: $generationId,
                    prompt: $prompt,
                    model: $model,
                    ratio: $ratio,
                    count: $count,
                    replicateType: $replicateType
                );
            } else {
                Log::error('❌ [Replicate] Error o estado desconocido', [
                    'id' => $generationId,
                    'status' => $status,
                    'error' => $result['error'] ?? 'Estado desconocido'
                ]);
                $this->isGenerating = false;
                $this->dispatch('generationError');
            }
            
        } catch (\Exception $e) {
            Log::error('💥 [Replicate] Error verificando estado', [
                'error' => $e->getMessage(),
                'generationId' => $generationId
            ]);
            $this->isGenerating = false;
            $this->dispatch('generationError');
        }
    }
    
    /**
     * Obtiene el estado según el tipo de modelo de Replicate
     * Todos usan ReplicateBaseService::getPredictionStatus() pero con diferentes servicios
     */
    private function obtenerEstadoReplicate(string $generationId, string $replicateType): array
    {
        // Por ahora todos los modelos de Replicate usan el mismo método getPredictionStatus
        // que está en ReplicateBaseService. Cada servicio específico lo hereda.
        switch ($replicateType) {
            case 'seedream':
                return BytedanceService::getPredictionStatus($generationId);
            case 'qwen':
                return QwenService::getPredictionStatus($generationId);
            case 'imagen-4-ultra':
                return GoogleService::getPredictionStatus($generationId);
            // Futuros modelos de Replicate:
            // case 'kling':
            //     return KwaivgiService::getPredictionStatus($generationId);
            // case 'veo31':
            //     return GoogleService::getPredictionStatus($generationId);
            default:
                Log::warning('⚠️ [Replicate] Tipo desconocido, usando getPredictionStatus genérico', ['type' => $replicateType]);
                return BytedanceService::getPredictionStatus($generationId);
        }
    }
    
    /**
     * Procesa el resultado según el tipo de modelo de Replicate
     * Replicate siempre devuelve el output en 'output' (array de URLs o string)
     */
    private function procesarResultadoReplicate(array $result, array $datos): void
    {
        $replicateType = $datos['replicateType'];
        $output = $result['output'];
        
        switch ($replicateType) {
            case 'seedream':
                $this->procesarImagenSeedream($output, $datos);
                break;
            case 'qwen':
            case 'imagen-4-ultra':
                // Qwen e Imagen 4 Ultra devuelven imágenes igual que otros modelos de Replicate
                $this->procesarImagenReplicate($output, $datos);
                break;
            // Futuros modelos de Replicate:
            // case 'kling':
            //     $this->procesarVideoKling($output, $datos);
            //     break;
            default:
                Log::warning('⚠️ [Replicate] Tipo de procesamiento desconocido, usando genérico', ['type' => $replicateType]);
                $this->procesarImagenReplicate($output, $datos);
        }
    }
    
    /**
     * Procesa imágenes de modelos de Replicate genéricos (Qwen, etc.)
     * Similar a procesarImagenSeedream pero sin lógica específica
     */
    private function procesarImagenReplicate($output, array $datos): void
    {
        try {
            Log::info('🔄 Procesando imagen de Replicate', [
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
                    Log::warning('⚠️ No se pudo descargar imagen de Replicate', [
                        'index' => $index,
                        'url' => $imageUrl
                    ]);
                    continue;
                }

                Log::info('📥 Imagen descargada exitosamente', [
                    'generationId' => $datos['generationId'],
                    'imageSize' => strlen($imageContent),
                    'index' => $index
                ]);

                // Guardar en S3
                $fileName = 'genesis/output-images/' . now()->format('Ymd_His') . '_' . ($datos['replicateType'] ?? 'replicate') . '_' . uniqid('img_') . '.png';
                Storage::disk('s3')->put($fileName, $imageContent);
                $finalUrl = Storage::disk('s3')->url($fileName);

                Log::info('☁️ Imagen subida a S3 exitosamente', [
                    'generationId' => $datos['generationId'],
                    'fileName' => $fileName,
                    'finalUrl' => $finalUrl
                ]);

                $imageData = [
                    'url' => $finalUrl,
                    'model' => $datos['model'],
                    'ratio' => $datos['ratio'],
                ];
                
                $this->results[] = $imageData;
                $generatedImages[] = $imageData;
            }

            if (!empty($generatedImages)) {
                // Registrar el uso de generación
                // Mapear replicateType a nombre de modelo
                $modelName = match($datos['replicateType'] ?? 'unknown') {
                    'imagen-4-ultra' => 'imagen-4-ultra',
                    'qwen' => 'qwen-image',
                    default => $datos['model'] ?? 'unknown'
                };
                
                $this->trackImageGenerationUsage(
                    $modelName,
                    count($generatedImages),
                    'replicate',
                    $datos['generationId'] // external_request_id para evitar duplicados
                );
                
                // Disparar evento de finalización
                $generationId = uniqid('gen_' . ($datos['replicateType'] ?? 'replicate') . '_');
                $this->dispatch('addToHistory', 
                    type: 'image/generate', 
                    images: $generatedImages, 
                    generationId: $generationId,
                    prompt: $datos['prompt'],
                    model: $this->getModelDisplayName($datos['model']),
                    ratio: $datos['ratio'],
                    count: count($generatedImages)
                );
                
                Log::info('✅ Imagen procesada y agregada al historial', [
                    'originalGenerationId' => $datos['generationId'],
                    'newGenerationId' => $generationId,
                    'imagesCount' => count($generatedImages)
                ]);
                
                $this->dispatch('generationCompleted');
                // Marcar el ID original como completado para detener el polling
                $this->dispatch('replicateCompleted', 
                    generationId: $datos['generationId'],
                    replicateType: $datos['replicateType'] ?? 'unknown'
                );
            } else {
                throw new \Exception('No se pudieron procesar las imágenes');
            }
            
        } catch (\Exception $e) {
            Log::error('💥 Error procesando imagen de Replicate', [
                'generationId' => $datos['generationId'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $errorMessage = 'Error procesando imagen: ' . $e->getMessage();
            $this->addError('promptText', $errorMessage);
            
            $this->dispatch('addErrorToList', 
                message: $errorMessage, 
                type: 'system', 
                tool: 'image-generator'
            );
            
            $this->dispatch('generationError');
        } finally {
            $this->isGenerating = false;
        }
    }

    /**
     * Registra el uso de un modelo que cobra por generación
     * 
     * @param string $modelName Nombre del modelo (ej: 'flux-kontext-max', 'gpt-image-1.5')
     * @param int $generationsCount Número de imágenes generadas
     * @param string|null $serviceType Tipo de servicio (ej: 'gemini', 'flux', 'replicate')
     * @param string|null $externalRequestId ID externo para evitar duplicados (opcional)
     */
    private function trackImageGenerationUsage(string $modelName, int $generationsCount = 1, ?string $serviceType = null, ?string $externalRequestId = null): void
    {
        // ✅ Usar accountId del componente
        $userId = Auth::id();
        
        if (!$userId) {
            Log::warning('⚠️ No se pudo obtener userId para registrar uso de generación de imagen', [
                'model' => $modelName,
                'accountId' => $this->accountId
            ]);
            return;
        }

        if ($generationsCount <= 0) {
            Log::warning('⚠️ Número de generaciones inválido', [
                'model' => $modelName,
                'generationsCount' => $generationsCount
            ]);
            return;
        }

        try {
            CostCalculationService::trackUsage(
                $this->accountId,  // ✅ Usar accountId del componente
                $userId,
                $modelName,
                [
                    'generations' => $generationsCount
                ],
                null, // usageDate (usa ahora)
                'Image Generator', // request_type
                $externalRequestId, // external_request_id (para evitar duplicados en Replicate)
                null, // generated_id (no hay Generated para imágenes simples)
                null, // step
                $serviceType // service_type
            );
            
            Log::info('✅ Uso registrado exitosamente para generación de imagen', [
                'model' => $modelName,
                'generations' => $generationsCount,
                'accountId' => $this->accountId,
                'userId' => $userId
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Error al registrar uso de generación de imagen', [
                'model' => $modelName,
                'generations' => $generationsCount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function render()
    {
        return view('livewire.generador.herramientas.image-generator');
    }
}



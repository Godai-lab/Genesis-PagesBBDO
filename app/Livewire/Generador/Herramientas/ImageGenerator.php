<?php

namespace App\Livewire\Generador\Herramientas;

use App\Services\GeminiService;
use App\Services\FluxService;
use App\Services\OpenAiService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Validate;
use Livewire\Component;
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
    /** Texto del prompt */
    #[Validate('required|string|min:3')]
    public string $promptText = '';

    /** Modelo Gemini Imagen */
   
    public string $model = 'imagen-4.0-generate-001';
   
    /** Relación de aspecto */
    public string $ratio = '1:1';
    public bool $isGenerating = false;

    /** Cantidad de imágenes a generar */
    #[Validate('integer|min:1|max:4')]
    public int $count = 1;

    /** Resultados generados recientemente */
    public array $results = [];

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
    // 'gemini-2.5-flash-image-preview' => [
    //     'name' => 'Nano Banana',
    //     'price' => '$0.039',
    //     'priceUnit' => 'por imagen',
    //     'description' => 'Modelo más avanzado con mejor calidad y detalles',
    //     'bestFor' => 'Imágenes profesionales, arte conceptual, fotografías realistas',
    //     'speed' => 'Rápido',
    //     'quality' => 'Excelente'
    // ],
    'gemini-3.1-flash-image-preview' => [
        'name' => 'Nano banana 2',
        'price' => '$0.039',
        'priceUnit' => 'por imagen',
        'description' => 'Modelo más avanzado con mejor calidad y detalles',
        'bestFor' => 'Imágenes profesionales, arte conceptual, fotografías realistas',
        'speed' => 'Rápido',
        'quality' => 'Excelente'
    ],
    'imagen-4.0-generate-001' => [
        'name' => 'Image4',
        'price' => '$0.06',
        'priceUnit' => 'por imagen',
        'description' => 'Modelo más avanzado con mejor calidad y detalles',
        'bestFor' => 'Imágenes profesionales, arte conceptual, fotografías realistas',
        'speed' => 'Rápido',
        'quality' => 'Excelente'
    ],
    // 'imagen-3.0-generate-002' => [
    //     'name' => 'Image3', 
    //     'price' => '$0.03',
    //     'priceUnit' => 'por imagen',
    //     'description' => 'Modelo equilibrado entre calidad y costo',
    //     'bestFor' => 'Uso general, prototipos, contenido web',
    //     'speed' => 'Muy rápido',
    //     'quality' => 'Buena'
    // ],
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
   'gpt-image-1' => [
    'name' => 'ChatGPT Imagen',
    'price' => '$0.10',
    'priceUnit' => 'por imagen',
    'description' => 'Modelo de OpenAI para generación de imágenes de alta calidad',
    'bestFor' => 'Ilustraciones creativas, diseño gráfico, arte conceptual',
    'speed' => 'Lento',
    'quality' => 'Alta-Media-Baja-'
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
            'imagen-4.0-generate-001',
            'imagen-3.0-generate-002',
            'gpt-image-1' // OpenAI también soporta múltiples imágenes
        ]);
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
        Log::info('🔧 ImageGenerator montado correctamente');
        
        // Verificar si hay datos pendientes de prompt
        $this->dispatch('imageGeneratorReady');
    }
    
    public function generate(): void
{
    $this->validate();
    
    Log::info('🚀 Iniciando proceso de generación de imagen', [
        'model' => $this->model,
        'prompt' => substr($this->promptText, 0, 50) . '...',
        'ratio' => $this->ratio,
        'count' => $this->count,
        'supportsMultipleImages' => $this->supportsMultipleImages
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
    
    // 3. DISPARAR EVENTO para iniciar generación REAL (con delay)
    $this->dispatch('startImageGeneration', [
        'prompt' => $this->promptText,
        'model' => $this->model,
        'count' => $this->count,
        'ratio' => $this->ratio
    ]);
    
    Log::info('📡 Evento startImageGeneration disparado con datos', [
        'prompt' => substr($this->promptText, 0, 50) . '...',
        'model' => $this->model,
        'count' => $this->count,
        'ratio' => $this->ratio
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
       switch ($data['model']) {
        case 'gemini-2.5-flash-image-preview':
        case 'gemini-3.1-flash-image-preview':
            Log::info('🎨 Generando con Gemini Image4', ['model' => $data['model']]);
            $this->generarConGemini25Flash($data);
            break;
        case 'imagen-4.0-generate-001':
            Log::info('🎨 Generando con Gemini Image4', ['model' => $data['model']]);
            $this->generarConGemini($data);
            break;
        case 'imagen-3.0-generate-002':
            Log::info('🎨 Generando con Gemini Image3', ['model' => $data['model']]);
            $this->generarConGemini($data);
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
        case 'gpt-image-1':
            Log::info('🎨 Generando con OpenAI DALL-E', ['model' => $data['model']]);
            $this->generarConOpenAI($data);
            break;
        default:
            Log::warning('⚠️ Modelo no reconocido', ['model' => $data['model']]);
            break;
       }

    } catch (\Exception $e) {
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
            aspectRatio: $data['ratio'],
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
                'ratio' => $data['ratio'],
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

public function generarConGemini($data): void
    {
        Log::info('🎨 Iniciando generación con Gemini', [
            'model' => $data['model'],
            'prompt' => substr($data['prompt'], 0, 50) . '...',
            'ratio' => $data['ratio'],
            'count' => $data['count']
        ]);
        
        try {

            $modelo = $data['model'] === 'imagen-4.0-generate-001'
            ? 'imagen-4.0-generate-001'
            : 'imagen-3.0-generate-002';
            
            Log::info('🔧 Modelo Gemini seleccionado', [
                'inputModel' => $data['model'],
                'selectedModel' => $modelo
            ]);
            
            // dd($modelo);
            $response = GeminiService::generateImage(
                prompt: $data['prompt'],
                model: $modelo,
                numberOfImages: $data['count'],
                aspectRatio: $data['ratio'],
                
            );

            Log::info('📡 Respuesta de GeminiService::generateImage', [
                'model' => $modelo,
                'success' => $response['success'] ?? false,
                'hasError' => isset($response['error']),
                'predictionsCount' => count($response['data'] ?? []),
                'responseKeys' => array_keys($response)
            ]);

            if (!($response['success'] ?? false)) {
                $errorMessage = $response['error']['message'] ?? 'No se pudo generar la imagen.';
                Log::error('❌ Error en respuesta de Gemini', [
                    'model' => $modelo,
                    'error' => $response['error'] ?? 'No error details'
                ]);
                
                $this->addError('promptText', $errorMessage);
                
                // Enviar error al componente principal para mostrar en la UI
                $this->dispatch('addErrorToList', 
                    message: $errorMessage, 
                    type: 'generation', 
                    tool: 'image-generator'
                );
                
                $this->dispatch('generationError');
                return;
            }

            // Procesar imágenes...
            $generationId = uniqid('gen_');
            $generatedImages = [];
            
            Log::info('🔄 Procesando imágenes generadas por Gemini', [
                'generationId' => $generationId,
                'predictionsCount' => count($response['data'] ?? [])
            ]);
            
            $predictions = $response['data'] ?? [];
            foreach ($predictions as $index => $prediction) {
                $base64 = $prediction['bytesBase64Encoded'] ?? null;
                if (!$base64) {
                    Log::warning('⚠️ Predicción sin base64', [
                        'index' => $index,
                        'predictionKeys' => array_keys($prediction)
                    ]);
                    continue;
                }

                Log::info('🖼️ Procesando imagen Gemini', [
                    'index' => $index,
                    'base64Length' => strlen($base64),
                    'generationId' => $generationId
                ]);

                $imageBinary = base64_decode($base64);
                $fileName = 'genesis/output-images/' . now()->format('Ymd_His') . '_' . uniqid('img_') . '.png';
                
                Log::info('☁️ Subiendo imagen Gemini a S3', [
                    'fileName' => $fileName,
                    'imageSize' => strlen($imageBinary)
                ]);
                
                Storage::disk('s3')->put($fileName, $imageBinary);
                $url = Storage::disk('s3')->url($fileName);

                Log::info('✅ Imagen Gemini subida exitosamente', [
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

            Log::info('📊 Resumen de generación Gemini', [
                'generationId' => $generationId,
                'totalImages' => count($generatedImages),
                'successfulImages' => count($generatedImages)
            ]);

            // 5. DISPARAR EVENTO de finalización
            if (!empty($generatedImages)) {
                Log::info('🎉 Generación Gemini completada exitosamente', [
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
                
                Log::info('✅ Eventos de finalización disparados para Gemini');
                
            } else {
                Log::warning('⚠️ No se generaron imágenes con Gemini', [
                    'generationId' => $generationId
                ]);
            }

        } catch (\Exception $e) {
            Log::error('💥 Error en generarConGemini', [
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
        } finally {
            $this->isGenerating = false;
            Log::info('🏁 Finalizando generarConGemini', [
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
            'flux_create_keys' => is_array($response) ? array_keys($response) : [],
            'polling_url' => $response['polling_url'] ?? null,
            'hasError' => isset($response['error']),
            'hasData' => isset($response['data']),
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
        $pollingUrl = $response['polling_url'] ?? null;
        
        Log::info('✅ ID de generación obtenido de Flux', [
            'model' => $modelo,
            'generationId' => $generationId,
            'polling_url' => $pollingUrl,
        ]);
        
        // ✅ EMITIR AL FRONTEND (sintaxis corregida)
        $this->dispatch('fluxTaskStarted', 
            generationId: $generationId,
            prompt: $data['prompt'],
            model: $data['model'],
            ratio: $data['ratio'],
            count: $data['count'],
            pollingUrl: $pollingUrl
        );

        Log::info('✅ Evento fluxTaskStarted disparado para generación real', [
            'generationId' => $generationId,
            'model' => $data['model'],
            'pollingUrl' => $pollingUrl,
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
        $fallbackPollUrl = 'https://api.bfl.ai/v1/get_result?id=' . rawurlencode((string) $generationId);

        Log::info('🔍 Verificando estado desde frontend', [
            'generationId' => $generationId,
            'model' => $model,
            'prompt' => substr($prompt, 0, 50) . '...',
            'ratio' => $ratio,
            'count' => $count,
            'source' => 'image-generator',
            'hasOriginalImages' => false,
            'polling_url_from_create' => $pollingUrl,
            'fallback_get_result_url' => $fallbackPollUrl,
        ]);
        
        $result = FluxService::GetResult($generationId, $pollingUrl);
        
        Log::info('📡 Respuesta del FluxService', [
            'generationId' => $generationId,
            'model' => $model,
            'status' => $result['status'] ?? 'unknown',
            'hasData' => isset($result['data']),
            'responseKeys' => array_keys($result),
            'flux_poll_raw_status' => $result['raw']['status'] ?? null,
            'flux_poll_raw_id' => $result['raw']['id'] ?? null,
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
                // ✅ IMAGEN LISTA
                Log::info('✅ Flux completado', [
                    'id' => $generationId,
                    'model' => $model,
                    'status' => $result['status']
                ]);
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
                    'pollingUrl' => $pollingUrl,
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
                'flux_create_keys' => is_array($response) ? array_keys($response) : [],
                'polling_url' => $response['polling_url'] ?? null,
                'hasError' => isset($response['error']),
                'hasData' => isset($response['data']),
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
            $pollingUrl = $response['polling_url'] ?? null;
            
            Log::info('✅ ID de generación obtenido de Flux Pro 1.1', [
                'model' => $data['model'],
                'generationId' => $generationId,
                'polling_url' => $pollingUrl,
            ]);
            
            // ✅ EMITIR AL FRONTEND para iniciar polling (reutilizamos el evento de flux-kontext)
            $this->dispatch('fluxTaskStarted', 
                generationId: $generationId,
                prompt: $data['prompt'],
                model: $data['model'],
                ratio: $data['ratio'],
                count: $data['count'],
                pollingUrl: $pollingUrl
            );

            Log::info('✅ Evento fluxTaskStarted disparado para Flux Pro 1.1', [
                'generationId' => $generationId,
                'model' => $data['model'],
                'pollingUrl' => $pollingUrl,
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
                'flux_create_keys' => is_array($response) ? array_keys($response) : [],
                'polling_url' => $response['polling_url'] ?? null,
                'hasError' => isset($response['error']),
                'hasData' => isset($response['data']),
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
            $pollingUrl = $response['polling_url'] ?? null;
            
            Log::info('✅ ID de generación obtenido de Flux Ultra', [
                'model' => $data['model'],
                'generationId' => $generationId,
                'polling_url' => $pollingUrl,
            ]);
            
            // ✅ EMITIR AL FRONTEND para iniciar polling (reutilizamos el evento de flux-kontext)
            $this->dispatch('fluxTaskStarted', 
                generationId: $generationId,
                prompt: $data['prompt'],
                model: $data['model'],
                ratio: $data['ratio'],
                count: $data['count'],
                pollingUrl: $pollingUrl
            );

            Log::info('✅ Evento fluxTaskStarted disparado para Flux Ultra', [
                'generationId' => $generationId,
                'model' => $data['model'],
                'pollingUrl' => $pollingUrl,
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
            
            // Llamar al servicio de OpenAI
            $response = OpenAiService::generateImage(
                $data['prompt'], 
                'gpt-image-1', 
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
                'responseKeys' => array_keys($response)
            ]);
            
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

    public function render()
    {
        return view('livewire.generador.herramientas.image-generator');
    }
}



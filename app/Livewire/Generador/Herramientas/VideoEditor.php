<?php

namespace App\Livewire\Generador\Herramientas;

use App\Http\Traits\ValidatesCreditLimit;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Services\RunWayService;
use App\Supports\CostCalculationService;

/**
 * Editor de Videos
 *
 * Permite transformar videos existentes usando diferentes modelos de IA.
 * Estructura modular y escalable para agregar nuevos modelos fácilmente.
 */
class VideoEditor extends Component
{
    use WithFileUploads;
    use ValidatesCreditLimit;
    
    protected string $toolName = 'Editor de Videos';

    /** ✅ Account ID recibido del componente padre - Reactive para sincronizar automáticamente */
    #[Reactive]
    public ?int $accountId = null;

    /** Video a editar */
    public $videoFile = null;
    public $videoUrl = null;

    /** Texto del prompt para la transformación */
    public string $promptText = '';

    /** Modelo de edición seleccionado */
    public string $model = 'gen4_aleph';

    /** Configuración de transformación */
    public string $ratio = '1280:720';
    public int $duration = 4; // Runway siempre genera 4 segundos, no acepta parámetro de duración

    /** Estados de procesamiento */
    public bool $isGenerating = false;
    public bool $isUploading = false;

    /** Resultados de edición */
    public array $results = [];

    /** Indicador si el video viene del historial */
    public bool $fromHistory = false;
    
    /** Metadata del video del historial */
    public array $historyMetadata = [];

    /** Catálogo de modelos disponibles para edición */
    public array $availableModels = [
        'gen4_aleph' => [
            'name' => 'Gen4-Aleph',
            'price' => '$0.10',
            'priceUnit' => 'por segundo',
            'description' => 'Modelo avanzado para transformaciones de video de alta calidad',
            'bestFor' => 'Ediciones profesionales, efectos complejos, cambios de estilo',
            'speed' => 'Medio',
            'quality' => 'Excelente'
        ]
    ];

    /** Ratios disponibles para edición según el modelo */
    public array $availableRatios = [
        '1280:720' => '16:9 Horizontal',
        '720:1280' => '9:16 Vertical',
        '1104:832' => '4:3 Horizontal',
        '832:1104' => '3:4 Vertical',
        '960:960' => '1:1 Cuadrado',
        '1584:672' => '21:9 Ultra panorámico',
        '848:480' => '16:9 Compacto',
        '640:480' => '4:3 Clásico'
    ];

    /** Duraciones disponibles - NOTA: Runway siempre genera 4 segundos, no acepta parámetro de duración */
    public array $availableDurations = [
        4 => '4 segundos'
    ];

    /**
     * Obtiene los ratios disponibles según el modelo seleccionado
     */
    public function getAvailableRatiosForModel(): array
    {
        // Por ahora todos los modelos soportan todos los ratios
        // En el futuro se puede filtrar según el modelo
        return $this->availableRatios;
    }

    /**
     * Obtiene las duraciones disponibles según el modelo seleccionado
     */
    public function getAvailableDurationsForModel(): array
    {
        // Por ahora todos los modelos soportan las mismas duraciones
        return $this->availableDurations;
    }

    /**
     * Obtiene el nombre amigable del modelo
     */
    private function getModelDisplayName($modelKey): string
    {
        return $this->availableModels[$modelKey]['name'] ?? $modelKey;
    }

    #[On('video-editor-model-selected')]
    public function updateModel($key)
    {
        $this->model = $key;
        Log::info('🎯 Modelo de editor actualizado', [
            'newModel' => $key,
            'currentModel' => $this->model
        ]);
        
        // Validar que el ratio actual sea compatible con el nuevo modelo
        $this->validarRatioCompatible();
    }

    /**
     * Valida que el ratio seleccionado sea compatible con el modelo actual
     */
    private function validarRatioCompatible(): void
    {
        $ratiosDisponibles = $this->getAvailableRatiosForModel();
        
        if (!array_key_exists($this->ratio, $ratiosDisponibles)) {
            // Cambiar al primer ratio disponible
            $nuevoRatio = array_key_first($ratiosDisponibles);
            $this->ratio = $nuevoRatio;
            
            Log::info("⚠️ Ratio cambiado automáticamente", [
                'ratioAnterior' => $this->ratio,
                'nuevoRatio' => $nuevoRatio,
                'modelo' => $this->model,
                'razon' => 'Ratio no compatible con el modelo seleccionado'
            ]);
            
            // Notificar al usuario
            $this->addError('ratio', "El ratio seleccionado no es compatible con {$this->getModelDisplayName($this->model)}. Se cambió automáticamente a {$ratiosDisponibles[$nuevoRatio]}.");
        }
    }

    /**
     * Validación personalizada por modelo
     */
    private function validarPorModelo(): bool
    {
        // Limpiar errores previos
        $this->resetErrorBag();
        
        $hasErrors = false;
        $errorMessage = '';
        
        // Validar que hay un video seleccionado (subido o del historial)
        if (!$this->videoFile && !$this->videoUrl) {
            $errorMessage = 'Es necesario seleccionar un video para editar o elegir uno del historial.';
            $this->addError('videoFile', $errorMessage);
            $hasErrors = true;
        }
        
        // Validar que hay un prompt
        if (empty(trim($this->promptText))) {
            $errorMessage = 'Es necesario escribir un prompt que describa la transformación deseada.';
            $this->addError('promptText', $errorMessage);
            $hasErrors = true;
        }
        
        if ($hasErrors) {
            // Enviar error al componente principal
            $this->dispatch('addErrorToList', 
                message: $errorMessage, 
                type: 'validation', 
                tool: 'video-editor'
            );
            
            Log::info('❌ Validación fallida', [
                'model' => $this->model,
                'errorMessage' => $errorMessage,
                'errors' => $this->getErrorBag()->toArray()
            ]);
            return false;
        }
        
        Log::info('✅ Validación exitosa', [
            'model' => $this->model,
            'hasPrompt' => !empty(trim($this->promptText)),
            'hasVideo' => !empty($this->videoFile) || !empty($this->videoUrl),
            'fromHistory' => $this->fromHistory
        ]);
        
        return true;
    }

    /**
     * Listener para cargar video desde el historial para edición
     */
    #[On('loadVideoFromHistory')]
    public function loadVideoFromHistory($videoUrl, $generationId, $originalModel, $originalRatio): void
    {
        try {
            Log::info('🎬 Cargando video del historial para edición', [
                'videoUrl' => $videoUrl,
                'generationId' => $generationId,
                'originalModel' => $originalModel,
                'originalRatio' => $originalRatio
            ]);

            // Limpiar video previo
            $this->quitarVideo();
            
            // ✅ SIMPLIFICACIÓN: Usar la misma lógica que los videos subidos
            // Simulamos que es un video "subido" pero desde el historial
            $this->videoUrl = $videoUrl;
            $this->fromHistory = true;
            $this->historyMetadata = [
                'videoUrl' => $videoUrl,
                'generationId' => $generationId,
                'originalModel' => $originalModel,
                'originalRatio' => $originalRatio
            ];
            
            // Configurar el ratio basado en el video original
            if ($originalRatio && isset($this->availableRatios[$originalRatio])) {
                $this->ratio = $originalRatio;
            }
            
            // Dispatch el mismo evento que los videos subidos para compatibilidad
            $this->dispatch('videoLoadedForEditing', url: $this->videoUrl);
            
            Log::info('✅ Video del historial cargado exitosamente para edición');
            
        } catch (\Exception $e) {
            Log::error('❌ Error cargando video del historial para edición: ' . $e->getMessage());
            
            $this->dispatch('addErrorToList', 
                message: 'Error al cargar el video del historial para edición: ' . $e->getMessage(), 
                type: 'system', 
                tool: 'video-editor'
            );
        }
    }

    public function mount()
    {
        // Inicialización del componente
        Log::info('🎬 VideoEditor component mounted', [
            'accountId' => $this->accountId
        ]);
        
        // Notificar que el componente está listo
        $this->dispatch('videoEditorReady');
    }

    /**
     * ✅ NUEVO: Listener para actualizar cuenta cuando cambia en el padre
     */
    #[On('accountChanged')]
    public function updateAccount(?int $accountId): void
    {
        $this->accountId = $accountId;
        
        Log::info('🔄 Cuenta actualizada en VideoEditor', [
            'accountId' => $accountId
        ]);
    }

    /**
     * Observer para cuando se selecciona un video
     */
    public function updatedVideoFile()
    {
        if ($this->videoFile) {
            // 🔄 LIMPIAR VIDEO DEL HISTORIAL si se sube video manualmente
            if ($this->fromHistory) {
                Log::info('🧹 Limpiando video del historial al subir video manual');
                $this->fromHistory = false;
                $this->historyMetadata = [];
            }
            
            $this->videoUrl = null; // Limpiar URL previa
            Log::info("Video seleccionado para editar", [
                'filename' => $this->videoFile->getClientOriginalName(),
                'size' => $this->videoFile->getSize()
            ]);
            
            // Iniciar subida automática
            $this->dispatch('iniciarSubidaVideo');
        }
    }

    /**
     * Subir video a S3 para procesamiento
     */
    #[On('iniciarSubidaVideo')]
    public function subirVideoAS3(): void
    {
        if (!$this->videoFile) {
            $this->addError('videoFile', 'No hay video seleccionado para subir');
            return;
        }
        
        try {
            $this->isUploading = true;
            
            Log::info("Iniciando subida de video a S3");
            
            // Generar nombre único
            $fileName = 'genesis/input-videos/' . now()->format('Ymd_His') . '_editor_' . uniqid() . '.' . $this->videoFile->getClientOriginalExtension();
            
            // Subir a S3
            $videoContent = file_get_contents($this->videoFile->getRealPath());
            Storage::disk('s3')->put($fileName, $videoContent);
            
            // Construir la URL pública del archivo
            $bucket = config('filesystems.disks.s3.bucket');
            $region = config('filesystems.disks.s3.region');
            $customBaseUrl = config('filesystems.disks.s3.url');
            $baseUrl = $customBaseUrl ?: "https://{$bucket}.s3.{$region}.amazonaws.com";
            $this->videoUrl = rtrim($baseUrl, '/') . "/{$fileName}";
            
            Log::info("Video subido exitosamente a S3", [
                'fileName' => $fileName,
                'url' => $this->videoUrl,
                'size' => strlen($videoContent)
            ]);
            
        } catch (\Exception $e) {
            $errorMessage = 'Error al subir el video: ' . $e->getMessage();
            $this->addError('videoFile', $errorMessage);
            
            $this->dispatch('addErrorToList', 
                message: $errorMessage, 
                type: 'upload', 
                tool: 'video-editor'
            );
            
            Log::error('Error subiendo video a S3: ' . $e->getMessage());
        } finally {
            $this->isUploading = false;
        }
    }

    /**
     * Quitar video seleccionado
     */
    public function quitarVideo(): void
    {
        $this->videoFile = null;
        $this->videoUrl = null;
        
        // Limpiar datos del historial
        $this->fromHistory = false;
        $this->historyMetadata = [];
        
        $this->resetErrorBag(['videoFile']);
        
        Log::info("Video removido del editor");
    }



    /**
     * Método principal para procesar/editar el video
     */
    public function processVideo(): void
    {
        // Validación personalizada por modelo
        if (!$this->validarPorModelo()) {
            return; // No continuar si hay errores de validación
        }        
        // Activar inmediatamente el spinner
        $this->isGenerating = true;
        $this->results = [];
        
        // Disparar evento para mostrar spinner en frontend
        $this->dispatch('videoEditStarted');
        
        // Disparar evento para iniciar edición real (con delay)
        $this->dispatch('startVideoEditing', [
            'prompt' => $this->promptText,
            'model' => $this->model,
            'ratio' => $this->ratio,
            'duration' => $this->duration,
            'videoUrl' => $this->videoUrl
        ]);
    }

    /**
     * Método que hace la edición real
     */
    #[On('startVideoEditing')]
    public function executeEditing($data): void
    {
        try {
            // ✅ Validar que haya una cuenta seleccionada
            if (!$this->accountId) {
                $errorMessage = 'Debes seleccionar una cuenta antes de editar videos';
                $this->addError('promptText', $errorMessage);
                
                $this->dispatch('addErrorToList', 
                    message: $errorMessage, 
                    type: 'validation', 
                    tool: 'video-editor'
                );
                
                $this->dispatch('generationError');
                $this->isGenerating = false;
                return;
            }
            
            // ✅ Validar límite de créditos usando la cuenta del componente
            $this->validateCreditLimit($this->accountId);
            
            // Delegar según el modelo
            switch ($data['model']) {
                case 'gen4_aleph':
                    $this->editarConGen4Aleph($data);
                    break;
                default:
                    throw new \Exception('Modelo no soportado para edición');
            }

        } catch (\App\Exceptions\CreditLimitExceededException $e) {
            Log::warning('Límite de créditos excedido en Editor de Videos', [
                'message' => $e->getMessage(),
                'accountId' => $this->accountId
            ]);
            
            $this->addError('promptText', $e->getMessage());
            
            // Enviar error al componente principal
            $this->dispatch('addErrorToList', 
                message: $e->getMessage(), 
                type: 'credit_limit', 
                tool: 'video-editor'
            );
            
            $this->dispatch('videoEditError');
            $this->isGenerating = false;
        } catch (\Exception $e) {
            Log::error('Error en Editor de Videos', [
                'message' => $e->getMessage(),
                'accountId' => $this->accountId,
                'trace' => $e->getTraceAsString()
            ]);
            
            $errorMessage = 'Ha ocurrido un error al editar el video. Por favor, intenta nuevamente.';
            $this->addError('promptText', $errorMessage);
            
            // Enviar error al componente principal
            $this->dispatch('addErrorToList', 
                message: $errorMessage, 
                type: 'system', 
                tool: 'video-editor'
            );
            
            $this->dispatch('videoEditError');
            $this->isGenerating = false;
        }
    }

    /**
     * Editar video con Gen4-Aleph (Runway)
     */
    private function editarConGen4Aleph($data): void
    {
        try {
        //     dd($data);
        //     if($data){
        //           // Disparar evento para iniciar polling
        //     $this->dispatch('videoEditTaskStarted', 
        //     generationId: "33c53e6a-6825-455b-ae9c-6960d0c28f1a",
        //     prompt: $data['prompt'],
        //     model: $data['model'],
        //     ratio: $data['ratio'],
        //     count: 1 // Para compatibilidad
        // );
        //         return;
        //     }
            Log::info('🚀 Iniciando edición con Gen4-Aleph', $data);
            
            // Llamar al servicio Runway para transformación de video
            $response = RunWayService::generateVideoFromVideo(
                $data['videoUrl'],
                $data['prompt'],
                'gen4_aleph',
                4294967295, // Seed por defecto
                $data['ratio'],
                [], // Referencias vacías por ahora
                ['publicFigureThreshold' => 'auto'],
                $data['duration']
            );
            
            if (!($response['success'] ?? false)) {
                $errorMessage = 'Error con Runway: ' . ($response['error'] ?? 'Error desconocido');
                throw new \Exception($errorMessage);
            }
            
            // Obtener el ID de tarea
            $taskId = $response['data']['id'] ?? null;
            if (!$taskId) {
                throw new \Exception('No se recibió ID de tarea de Runway');
            }
            
            Log::info("✅ Edición Runway iniciada correctamente", [
                'taskId' => $taskId,
                'model' => $data['model']
            ]);
            
            // Disparar evento para iniciar polling
            $this->dispatch('videoEditTaskStarted', 
                generationId: $taskId,
                prompt: $data['prompt'],
                model: $data['model'],
                ratio: $data['ratio'],
                count: 1, // Para compatibilidad
                duration: $data['duration'] // Pasar la duración para el tracking
            );
            
        } catch (\Exception $e) {
            $errorMessage = 'Error editando con Gen4-Aleph: ' . $e->getMessage();
            $this->addError('promptText', $errorMessage);
            
            $this->dispatch('addErrorToList', 
                message: $errorMessage, 
                type: 'system', 
                tool: 'video-editor'
            );
            
            $this->dispatch('videoEditError');
            $this->isGenerating = false;
        }
    }

    /**
     * Verifica el estado de edición de video
     */
    #[On('verificarEstadoVideoEditor')]
    public function verificarEstadoVideoEditor($generationId, $prompt, $model, $ratio, $count, $duration = null): void
    {
        try {
            // Usar la duración pasada como parámetro, o la del componente, o 4 por defecto (Runway siempre genera 4 segundos)
            $finalDuration = $duration ?? $this->duration ?? 4;
            
            Log::info('🔍 Verificando estado de edición de video', [
                'generationId' => $generationId,
                'model' => $model,
                'duration' => $finalDuration,
                'durationSource' => $duration ? 'parameter' : ($this->duration ? 'component' : 'default')
            ]);
            
            $datos = [
                'generationId' => $generationId,
                'prompt' => $prompt,
                'model' => $model,
                'ratio' => $ratio,
                'count' => $count, // Para compatibilidad con VideoGenerator
                'duration' => $finalDuration // Duración del video editado para calcular costo
            ];
            
            // Delegar según el modelo
            switch ($model) {
                case 'gen4_aleph':
                    $this->verificarEstadoRunway($generationId, $datos);
                    break;
                default:
                $this->verificarEstadoRunway($generationId, $datos);
                    break;
            }
            
        } catch (\Exception $e) {
            Log::error('💥 Error verificando estado de edición', [
                'error' => $e->getMessage(),
                'model' => $model,
                'generationId' => $generationId
            ]);
            $this->isGenerating = false;
            $this->dispatch('videoEditError');
        }
    }

    /**
     * Verifica el estado específico de Runway para edición
     */
    private function verificarEstadoRunway(string $generationId, array $datos): void
    {
        Log::info('🎬 Consultando estado de edición Runway', [
            'taskId' => $generationId,
            'model' => $datos['model']
        ]);
        
        $result = RunWayService::checkVideoGenerationStatus($generationId);
        
        if (!($result['success'] ?? false)) {
            throw new \Exception('Error verificando estado de Runway: ' . ($result['error'] ?? 'Error desconocido'));
        }
        
        $taskData = $result['data'] ?? [];
        $taskStatus = $taskData['status'] ?? 'unknown';
        
        Log::info('📊 Estado de la tarea de edición Runway', [
            'status' => $taskStatus,
            'hasOutput' => isset($taskData['output']),
            'outputCount' => count($taskData['output'] ?? [])
        ]);
        
        if ($taskStatus === 'SUCCEEDED') {
            // Video editado listo - Procesar resultado
            Log::info('✅ Edición Runway completada', ['id' => $generationId]);
            $this->procesarVideoEditado($taskData, $datos);
        } elseif (in_array($taskStatus, ['PENDING', 'RUNNING'])) {
            // Aún pendiente - Emitir al frontend para nuevo delay
            Log::info('⏳ Edición Runway aún pendiente', [
                'id' => $generationId,
                'status' => $taskStatus
            ]);
            $this->dispatch('videoEditStillPending', 
                generationId: $datos['generationId'],
                prompt: $datos['prompt'],
                model: $datos['model'],
                ratio: $datos['ratio'],
                count: $datos['count'],
                duration: $datos['duration'] // Pasar la duración para el tracking
            );
        } else {
            // Error o estado desconocido
            Log::error('❌ Edición Runway falló o estado desconocido', [
                'id' => $generationId,
                'status' => $taskStatus
            ]);
            throw new \Exception('Estado desconocido de edición Runway: ' . $taskStatus);
        }
    }

    /**
     * Verifica el estado genérico para modelos no reconocidos
     * Para el editor, por ahora solo soportamos gen4_aleph, así que este método
     * debería manejar casos no implementados correctamente
     */
    private function verificarEstadoGenerico(string $generationId, array $datos): void
    {
        Log::warning('⚠️ Modelo no soportado para edición', [
            'id' => $generationId,
            'model' => $datos['model']
        ]);
        
        // Para modelos no soportados, marcar como error
        $this->isGenerating = false;
        $this->dispatch('videoEditError');
        
        $this->dispatch('addErrorToList', 
            message: "El modelo {$datos['model']} no está soportado para edición de videos", 
            type: 'system', 
            tool: 'video-editor'
        );
    }

    /**
     * Procesa un video editado completado
     */
    private function procesarVideoEditado(array $response, array $datos): void
    {
        try {
            Log::info('🎬 Procesando video editado completado', [
                'hasOutput' => isset($response['output']),
                'outputCount' => count($response['output'] ?? []),
                'model' => $datos['model']
            ]);
            
            // Verificar si hay videos en la respuesta
            if (!isset($response['output']) || empty($response['output'])) {
                throw new \Exception('No se encontraron videos editados en la respuesta');
            }
            
            $outputUrls = $response['output'];
            $totalVideos = count($outputUrls);
            
            Log::info("📹 Encontrados {$totalVideos} video(s) editado(s)");
            
            $videos = [];
            $processedCount = 0;
            
            foreach ($outputUrls as $index => $videoUrl) {
                try {
                    // Descargar el video editado
                    Log::info("📥 Descargando video editado #{$index}", ['url' => $videoUrl]);
                    $videoContent = file_get_contents($videoUrl);
                    
                    if ($videoContent === false) {
                        Log::warning("⚠️ No se pudo descargar el video editado #{$index}", ['url' => $videoUrl]);
                        continue;
                    }
                    
                    // Guardar en S3
                    $fileName = 'genesis/output-videos/' . now()->format('Ymd_His') . '_edited_' . uniqid('video_') . '.mp4';
                    Storage::disk('s3')->put($fileName, $videoContent);
                    
                    // Construir la URL pública del archivo
                    $bucket = config('filesystems.disks.s3.bucket');
                    $region = config('filesystems.disks.s3.region');
                    $customBaseUrl = config('filesystems.disks.s3.url');
                    $baseUrl = $customBaseUrl ?: "https://{$bucket}.s3.{$region}.amazonaws.com";
                    $finalUrl = rtrim($baseUrl, '/') . "/{$fileName}";
                    
                    Log::info("💾 Video editado #{$index} guardado en S3", [
                        'fileName' => $fileName,
                        'finalUrl' => $finalUrl,
                        'size' => strlen($videoContent)
                    ]);
                    
                    // Crear datos del video editado
                    $videoData = [
                        'url' => $finalUrl,
                        'model' => $datos['model'],
                        'ratio' => $datos['ratio'],
                        'prompt' => $datos['prompt'],
                        'status' => 'completed',
                        'created_at' => now()->toISOString()
                    ];
                    
                    $this->results[] = $videoData;
                    $videos[] = $videoData;
                    $processedCount++;
                    
                    Log::info("✅ Video editado #{$index} procesado", [
                        'originalUrl' => $videoUrl,
                        's3Url' => $finalUrl,
                        'index' => $index + 1,
                        'total' => $totalVideos,
                        'processed' => $processedCount
                    ]);
                    
                } catch (\Exception $e) {
                    Log::error("❌ Error procesando video editado #{$index}", [
                        'error' => $e->getMessage(),
                        'url' => $videoUrl
                    ]);
                    continue;
                }
            }
            
            if (!empty($videos)) {
                $videoCount = count($videos);
                
                // Registrar el uso del video editado (gen4_aleph cobra por segundo)
                // Runway siempre genera 4 segundos, no acepta parámetro de duración
                $durationSeconds = $datos['duration'] ?? 4; // Por defecto 4 segundos
                // Si hay múltiples videos, cada uno tiene la misma duración
                $totalSeconds = $videoCount * $durationSeconds;
                
                $this->trackVideoEditUsage(
                    'gen4_aleph',
                    $totalSeconds, // Total de segundos de todos los videos editados
                    'runway',
                    $datos['generationId'] // external_request_id para evitar duplicados
                );
                
                Log::info("🎬 Preparando para agregar {$videoCount} video(s) editado(s) al historial", [
                    'videos' => $videos,
                    'prompt' => $datos['prompt'],
                    'model' => $datos['model'],
                    'durationSeconds' => $durationSeconds,
                    'totalSeconds' => $totalSeconds
                ]);
                
                // Disparar evento de finalización
                $this->dispatch('addToHistory', 
                    type: 'video/generate', 
                    images: $videos,
                    generationId: $datos['generationId'],
                    prompt: $datos['prompt'],
                    model: $this->getModelDisplayName($datos['model']),
                    ratio: $datos['ratio'],
                    count: $videoCount
                );
                
                $this->dispatch('videoEditCompleted');
                Log::info("🎉 {$videoCount} video(s) editado(s) agregados exitosamente al historial", [
                    'count' => $videoCount,
                    'generationId' => $datos['generationId']
                ]);
            } else {
                throw new \Exception('No se pudieron procesar los videos editados');
            }
            
        } catch (\Exception $e) {
            $errorMessage = 'Error procesando video editado: ' . $e->getMessage();
            $this->addError('promptText', $errorMessage);
            
            $this->dispatch('addErrorToList', 
                message: $errorMessage, 
                type: 'system', 
                tool: 'video-editor'
            );
            
            $this->dispatch('videoEditError');
        } finally {
            $this->isGenerating = false;
        }
    }



    /**
     * Registra el uso de un modelo que cobra por segundo (edición de video)
     * 
     * @param string $modelName Nombre del modelo (ej: 'gen4_aleph')
     * @param float $seconds Duración del video editado en segundos
     * @param string|null $serviceType Tipo de servicio (ej: 'runway')
     * @param string|null $externalRequestId ID externo para evitar duplicados (opcional)
     */
    private function trackVideoEditUsage(string $modelName, float $seconds, ?string $serviceType = null, ?string $externalRequestId = null): void
    {
        // ✅ Usar accountId del componente
        $userId = Auth::id();
        
        if (!$userId) {
            Log::warning('⚠️ No se pudo obtener userId para registrar uso de edición de video', [
                'model' => $modelName,
                'accountId' => $this->accountId
            ]);
            return;
        }

        if ($seconds <= 0) {
            Log::warning('⚠️ Duración de video inválida', [
                'model' => $modelName,
                'seconds' => $seconds
            ]);
            return;
        }

        try {
            CostCalculationService::trackUsage(
                $this->accountId,  // ✅ Usar accountId del componente
                $userId,
                $modelName,
                [
                    'seconds' => $seconds
                ],
                null, // usageDate (usa ahora)
                'Video Editor', // request_type
                $externalRequestId, // external_request_id (para evitar duplicados)
                null, // generated_id (no hay Generated para ediciones simples)
                null, // step
                $serviceType // service_type
            );
            
            Log::info('✅ Uso registrado exitosamente para edición de video', [
                'model' => $modelName,
                'seconds' => $seconds,
                'accountId' => $this->accountId,
                'userId' => $userId
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Error al registrar uso de edición de video', [
                'model' => $modelName,
                'seconds' => $seconds,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function render()
    {
        return view('livewire.generador.herramientas.video-editor');
    }
}

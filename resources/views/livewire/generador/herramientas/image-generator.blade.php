
<div>
    <div class="w-full mx-auto space-y-1">
    <style>
        textarea.no-border { border: none !important; box-shadow: none !important; }
        .input-container {
             position: sticky; bottom: 0; 
             background-color: #ffffff;
              z-index: 10; }
        @media (max-width: 768px) {
            .input-container { bottom: 0; }
        }
    </style>
    <!-- Caja de herramienta  -->
    <div class="w-full max-w-4xl px-0 mx-auto">
    <div class="input-container bg-black rounded-xl p-2 shadow-lg ">
        <div class="relative bg-white rounded-lg">
            <textarea 
                wire:model.live="promptText" 
                class="w-full outline-none resize-none text-sm min-h-[60px] text-gray-700 no-border" 
                placeholder="Describe una imagen para generar..."
                @keydown.enter.prevent="$event.shiftKey || $wire.generate()"
            ></textarea>
            @error('promptText')
                <p class="px-3 pb-2 text-xs text-red-500">
                    {{ $message === 'The prompt text field is required.' || str_contains($message, 'required')
                        ? 'Escribe qué quieres generar, o añade instrucciones de estilo en el Editor de contexto.'
                        : $message }}
                </p>
            @enderror

            <div class="flex items-center justify-between px-4 pb-3">
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Ratio dropdown -->
                    <div x-data="{ open: false }" class="relative">
                        <button 
                            @click="open = !open"
                            class="flex items-center space-x-1 bg-gray-100 hover:bg-gray-200 rounded-full px-3 py-1 text-sm shadow-sm text-gray-700"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                            </svg>
                            <span>{{ $ratio }}</span>
                        </button>
                        <div 
                            x-show="open" 
                            x-cloak
                            @click.away="open = false"
                            class="absolute bottom-full left-0 mb-1 bg-white border border-gray-200 rounded-xl p-4 w-[200px] z-20 shadow-lg"
                        >
                            <div class="text-center mb-2 text-gray-600 font-medium">Relación de aspecto</div>
                            <div class="grid grid-cols-1 gap-2">
                                @foreach($availableRatios as $value => $label)
                                    <button 
                                        wire:click="$set('ratio', '{{ $value }}')"
                                        @click="open = false"
                                        class="bg-{{ $ratio === $value ? 'black text-white' : 'gray-100 hover:bg-gray-200 text-gray-800' }} rounded text-center py-2 text-sm flex justify-between items-center px-3"
                                    >
                                        <span>{{ $value }}</span>
                                        <span class="text-xs text-gray-500">{{ $label }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Cantidad dropdown - Solo para modelos que soportan múltiples imágenes -->
                    @if($this->supportsMultipleImages)
                    <div x-data="{ open: false }" class="relative">
                        <button 
                            @click="open = !open"
                            class="flex items-center space-x-1 bg-gray-100 hover:bg-gray-200 rounded-full px-3 py-1 text-sm shadow-sm text-gray-700"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                            </svg>
                            <span>{{ $count }} imagen{{ $count > 1 ? 'es' : '' }}</span>
                        </button>
                        <div 
                            x-show="open" 
                            x-cloak
                            @click.away="open = false"
                            class="absolute bottom-full left-0 mb-1 bg-white border border-gray-200 rounded-xl p-4 w-[200px] z-20 shadow-lg"
                        >
                            <div class="text-center mb-2 text-gray-600 font-medium">Cantidad de imágenes</div>
                            <div class="grid grid-cols-1 gap-2">
                                @foreach([1,2,3,4] as $n)
                                    <button 
                                        wire:click="$set('count', {{ $n }})"
                                        @click="open = false"
                                        class="bg-{{ $count === $n ? 'black text-white' : 'gray-100 hover:bg-gray-200 text-gray-800' }} rounded text-center py-2 text-sm flex justify-between items-center px-3"
                                    >
                                        <span>{{ $n }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Calidad dropdown - Solo para modelos OpenAI -->
                    @if(in_array($model, ['gpt-image-1', 'gpt-image-1.5']))
                    <div x-data="{ open: false }" class="relative">
                        <button 
                            @click="open = !open"
                            class="flex items-center space-x-1 bg-gray-100 hover:bg-gray-200 rounded-full px-3 py-1 text-sm shadow-sm text-gray-700"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>{{ $calidadesDisponibles[$calidadImagen] }}</span>
                        </button>
                        <div 
                            x-show="open" 
                            x-cloak
                            @click.away="open = false"
                            class="absolute bottom-full left-0 mb-1 bg-white border border-gray-200 rounded-xl p-4 w-[200px] z-20 shadow-lg"
                        >
                            <div class="text-center mb-2 text-gray-600 font-medium">Calidad de imagen</div>
                            <div class="grid grid-cols-1 gap-2">
                                @foreach($calidadesDisponibles as $value => $label)
                                    <button 
                                        wire:click="$set('calidadImagen', '{{ $value }}')"
                                        @click="open = false"
                                        class="bg-{{ $calidadImagen === $value ? 'black text-white' : 'gray-100 hover:bg-gray-200 text-gray-800' }} rounded text-center py-2 text-sm flex justify-between items-center px-3"
                                    >
                                        <span>{{ $label }}</span>
                                        @if($value === 'auto')
                                            <span class="text-xs text-gray-500">Por defecto</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Resolución dropdown - Nano Banana Pro y Nano Banana 2 (Gemini 3) -->
                    @if(in_array($model, ['gemini-3-pro-image-preview', 'gemini-3.1-flash-image-preview']))
                    <div x-data="{ open: false }" class="relative">
                        <button 
                            @click="open = !open"
                            class="flex items-center space-x-1 bg-gray-100 hover:bg-gray-200 rounded-full px-3 py-1 text-sm shadow-sm text-gray-700"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                            </svg>
                            <span>{{ $resolutionNanoBanana }}</span>
                        </button>
                        <div 
                            x-show="open" 
                            x-cloak
                            @click.away="open = false"
                            class="absolute bottom-full left-0 mb-1 bg-white border border-gray-200 rounded-xl p-4 w-[200px] z-20 shadow-lg"
                        >
                            <div class="text-center mb-2 text-gray-600 font-medium">Resolución</div>
                            <div class="grid grid-cols-1 gap-2">
                                @foreach(['1K' => '1K', '2K' => '2K', '4K' => '4K'] as $value => $label)
                                    <button 
                                        wire:click="$set('resolutionNanoBanana', '{{ $value }}')"
                                        @click="open = false"
                                        class="bg-{{ $resolutionNanoBanana === $value ? 'black text-white' : 'gray-100 hover:bg-gray-200 text-gray-800' }} rounded text-center py-2 text-sm flex justify-between items-center px-3"
                                    >
                                        <span>{{ $value }}</span>
                                        <span class="text-xs text-gray-500">{{ $label }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Botón Contexto: solo para Nano Banana (3.1 / 3 Pro) -->
                    @if($this->supportsContextSidebar)
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            wire:click="toggleContextSidebar"
                            class="flex items-center space-x-1 rounded-full px-3 py-1 text-sm {{ ($showContextSidebar || $contextEnabled) ? 'bg-black text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-700' }}"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
                            </svg>
                            <span>Contexto</span>
                            @if($contextEnabled && !$showContextSidebar)
                                <span class="ml-1 w-1.5 h-1.5 rounded-full bg-green-400 inline-block"></span>
                            @endif
                        </button>
                        @if($contextEnabled && !$showContextSidebar)
                        <button
                            type="button"
                            wire:click="clearContext"
                            title="Limpiar contexto"
                            class="flex items-center justify-center w-6 h-6 rounded-full bg-gray-200 hover:bg-red-100 hover:text-red-600 text-gray-500 transition-colors"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                        @endif
                    </div>
                    @endif
                </div>

                <button 
                    wire:click="generate"
                    class="bg-black text-white px-4 py-2 rounded-lg hover:bg-gray-800 transition-colors text-sm flex items-center"
                >
                    
                    Generar
                </button>
            </div>
        </div>
    </div>
    </div>

    {{-- Sidebar de contexto (Nano Banana): imágenes de referencia + texto de contexto --}}
    @if($this->supportsContextSidebar)
    <div
        x-data="{ open: @entangle('showContextSidebar') }"
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-x-4"
        x-transition:enter-end="opacity-100 translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-x-0"
        x-transition:leave-end="opacity-0 translate-x-4"
        class="fixed right-0 top-0 bottom-0 w-full max-w-md bg-white dark:bg-zinc-900 border-l border-gray-200 dark:border-zinc-700 shadow-xl z-30 flex flex-col overflow-hidden"
        style="display: none;"
    >
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Editor de contexto</h3>
            <div class="flex items-center gap-3">
                {{-- Toggle "Habilitar contexto" --}}
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <span class="text-xs text-gray-500 dark:text-gray-400">Habilitar contexto</span>
                    <button
                        type="button"
                        wire:click="$toggle('contextEnabled')"
                        class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none
                               {{ $contextEnabled ? 'bg-black dark:bg-white' : 'bg-gray-300 dark:bg-zinc-600' }}"
                        role="switch"
                        aria-checked="{{ $contextEnabled ? 'true' : 'false' }}"
                    >
                        <span class="inline-block h-3.5 w-3.5 transform rounded-full transition-transform
                                     {{ $contextEnabled ? 'translate-x-4 bg-white dark:bg-black' : 'translate-x-1 bg-white' }}">
                        </span>
                    </button>
                </label>
                <button
                    type="button"
                    wire:click="$set('showContextSidebar', false)"
                    class="p-2 rounded-lg hover:bg-gray-200 dark:hover:bg-zinc-700 text-gray-600 dark:text-gray-400"
                    aria-label="Cerrar"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
        <x-generador.context-sidebar :max-reference-images="$this->maxReferenceImages">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Instrucciones de estilo y contexto
                    </label>
                    <textarea
                        wire:model.live="contextText"
                        rows="3"
                        class="w-full rounded-lg border border-gray-300 dark:border-zinc-600 dark:bg-zinc-800 dark:text-gray-200 text-sm px-3 py-2 focus:ring-2 focus:ring-black focus:border-transparent"
                        placeholder="Ej: estilo cinematográfico, paleta de colores cálidos, que el personaje lleve ropa casual..."
                    ></textarea>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Referencias visuales
                        <span class="text-gray-500 font-normal">(hasta {{ $this->maxReferenceImages }})</span>
                    </label>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Sube moodboards, personajes o productos como guía de estilo. El modelo se inspirará en ellas, no las editará directamente.</p>
                    <div
                        x-data="{ pendingCount: 0 }"
                        x-init="$watch(() => $wire.referenceImagesData.length, () => { pendingCount = 0 })"
                    >
                        @if(count($referenceImagesData) < $this->maxReferenceImages)
                        <label class="relative flex items-center justify-center w-full h-24 border-2 border-dashed border-gray-300 dark:border-zinc-600 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-zinc-800 transition-colors">
                            <input
                                type="file"
                                wire:model="newReferenceImages"
                                accept="image/*"
                                class="hidden"
                                multiple
                                @change="pendingCount = $event.target.files.length"
                            />
                            {{-- Alpine controla el texto: sin pendingCount muestra el label normal, con pendingCount muestra procesando --}}
                            <span x-show="pendingCount === 0" class="text-sm text-gray-500 dark:text-gray-400">
                                + Añadir imagen
                            </span>
                            <span x-show="pendingCount > 0" x-cloak class="flex items-center gap-2 text-xs text-gray-400 dark:text-gray-500">
                                <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                                </svg>
                                Procesando...
                            </span>
                        </label>
                        @endif

                        @error('newReferenceImages.*')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror

                        <div class="mt-2 grid grid-cols-4 gap-2">
                            {{-- Skeletons: Alpine los muestra/oculta por pendingCount, sin wire:loading --}}
                            <template x-for="i in pendingCount" :key="i">
                                <div class="aspect-square rounded-lg bg-gray-200 dark:bg-zinc-700 animate-pulse"></div>
                            </template>

                            {{-- Imágenes ya procesadas --}}
                            @foreach($referenceImagesData as $idx => $imgData)
                                <div class="relative group aspect-square rounded-lg overflow-hidden bg-gray-100 dark:bg-zinc-800">
                                    <img
                                        src="data:{{ $imgData['mime_type'] }};base64,{{ $imgData['data'] }}"
                                        alt="Referencia {{ $idx + 1 }}"
                                        class="w-full h-full object-cover"
                                    />
                                    <button
                                        type="button"
                                        wire:click="removeReferenceImage({{ $idx }})"
                                        class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity text-white text-xs"
                                    >Quitar</button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="flex flex-col gap-2 pt-2 border-t border-gray-200 dark:border-zinc-700">
                    @php $hasContext = trim($contextText) !== '' || count($referenceImagesData) > 0; @endphp
                    <button
                        type="button"
                        wire:click="acceptContext"
                        @disabled(!$hasContext)
                        class="w-full py-2.5 rounded-lg transition-colors text-sm font-medium
                               {{ $hasContext
                                   ? 'bg-black text-white hover:bg-gray-800 cursor-pointer'
                                   : 'bg-gray-200 dark:bg-zinc-700 text-gray-400 dark:text-zinc-500 cursor-not-allowed' }}"
                    >
                        Aceptar
                    </button>
                    @if(!$hasContext)
                    <p class="text-xs text-gray-400 dark:text-gray-500 text-center">
                        Añade instrucciones o imágenes de referencia para continuar.
                    </p>
                    @endif

                    @if(trim($contextText) !== '' || count($referenceImagesData) > 0)
                    <button
                        type="button"
                        wire:click="clearContext"
                        class="w-full bg-gray-100 dark:bg-zinc-700 text-gray-600 dark:text-gray-400 py-2 rounded-lg hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400 transition-colors text-sm"
                    >
                        Limpiar contexto
                    </button>
                    @endif
                    @if($hasContext)
                    <p class="text-xs text-gray-500 dark:text-gray-400 text-center">
                        Aceptar cierra el panel y activa el contexto en la caja principal.
                    </p>
                    @endif
                </div>
            </div>
        </x-generador.context-sidebar>
    </div>
    @endif

    
    <livewire:generador.components.generating-status
    :show="$isGenerating"
    message="Generando imagen..."
    :subtitle="'Espere por favor...'"
    {{-- wire:key="generating-status-{{ uniqid() }}"  --}}
    />
<!-- Selector de modelos flotante, reutilizable -->
   
<livewire:generador.components.model-selector
    :models="$availableModels"
    :selected="$model"
    :eventName="'image-generator-model-selected'"
    title="Modelo de IA"
    wire:key="model-selector-island" />
 
</div>




{{-- image-generator.blade.php --}}
@script
<script>
(function(){
    /* ---------- helpers (sin arrow, sin const/let en raíz) ---------- */
    var $ = function(id) { return document.getElementById(id); };
    var q = function(sel) { return document.querySelector(sel); };

    function toggleGenSpinner(show) {
        var s = $('generation-spinner');
        if (s) s.style.display = show ? 'flex' : 'none';
    }
    function toggleGenClass(add) {
        var box = q('.results-container');
        if (box) box.classList.toggle('generating', add);
    }

    /* ---------- listeners ---------- */
    Livewire.on('generationStarted', function() {
        console.log('🚀 Iniciando generación...');
        toggleGenSpinner(true);
        toggleGenClass(true);
    });

    Livewire.on('generationCompleted', function() {
        console.log('✅ Generación completada');
        setTimeout(function() {
            toggleGenSpinner(false);
            toggleGenClass(false);
        }, 500);
    });

    Livewire.on('generationError', function() {
        console.log('❌ Error en generación');
        toggleGenSpinner(false);
        toggleGenClass(false);
    });

    /* ========== TRACKING DE IDs COMPLETADOS ========== */
    const completedGenerations = new Set();

    /* ========== FLUX POLLING (usa FluxService - NO es Replicate) ========== */
    function startFluxPolling(data) {
        console.log('⏰ [Flux] Iniciando polling:', data.generationId);
        setTimeout(function() { checkFluxStatus(data); }, 10_000);
    }

    function checkFluxStatus(data) {
        if (completedGenerations.has(data.generationId)) {
            console.log('⏭️ [Flux] Ya completado, ignorando:', data.generationId);
            return;
        }
        console.log('🔍 [Flux] Verificando:', data.generationId);
        Livewire.dispatch('verificarEstadoFluxKontext', data);
    }

    Livewire.on('fluxTaskStarted', function(e) { startFluxPolling(e); });
    Livewire.on('fluxStillPending', function(e) {
        console.log('⏳ [Flux] Aún pendiente');
        setTimeout(function() { checkFluxStatus(e); }, 10_000);
    });
    Livewire.on('fluxCompleted', function(e) {
        completedGenerations.add(e.generationId);
        console.log('✅ [Flux] Completado:', e.generationId);
    });

    /* ========== REPLICATE POLLING (Seedream, Kling, Veo3.1, etc.) ========== */
    function startReplicatePolling(data) {
        console.log('⏰ [Replicate] Iniciando polling:', data.generationId, '| Tipo:', data.replicateType);
        setTimeout(function() { checkReplicateStatus(data); }, 10_000);
    }

    function checkReplicateStatus(data) {
        if (completedGenerations.has(data.generationId)) {
            console.log('⏭️ [Replicate] Ya completado, ignorando:', data.generationId);
            return;
        }
        console.log('🔍 [Replicate] Verificando:', data.generationId, '| Tipo:', data.replicateType);
        Livewire.dispatch('verificarEstadoReplicate', data);
    }

    Livewire.on('replicateTaskStarted', function(e) { startReplicatePolling(e); });
    Livewire.on('replicateStillPending', function(e) {
        console.log('⏳ [Replicate] Aún pendiente:', e.replicateType);
        setTimeout(function() { checkReplicateStatus(e); }, 10_000);
    });
    Livewire.on('replicateCompleted', function(e) {
        completedGenerations.add(e.generationId);
        console.log('✅ [Replicate] Completado:', e.generationId, '| Tipo:', e.replicateType);
    });

    console.log('📜 ImageGenerator: listeners registrados (sin Alpine errors)');
})();
</script>
@endscript
</div>



@props([
    'maxReferenceImages' => 14,
])

{{-- Contenido del sidebar de contexto (Nano Banana). El aside y la visibilidad se controlan en la vista Livewire. --}}
<div class="flex-1 overflow-y-auto p-4 space-y-4">
    <p class="text-xs text-gray-600 dark:text-gray-400">
        Contextualiza tu proyecto y preferencias con Nano Banana. Añade instrucciones o imágenes de referencia para influir en tus generaciones.
    </p>
    {{ $slot }}
</div>

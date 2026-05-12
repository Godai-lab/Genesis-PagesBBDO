<x-app-layout>
    <x-slot name="title">Génesis - Asistente-Chat </x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-black dark:text-gray-200 leading-tight">
            {{ __('Asistente-Chat') }}
        </h2>
    </x-slot>
<!-- Componente de chat -->
@livewire('chat-component')
</x-app-layout>
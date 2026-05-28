<x-app-layout>
    <x-slot name="title">Génesis - Proveedores - Crear</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Proveedores') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="max-w-2xl mx-auto">
                        <form action="{{ route('providers.store')}}" method="POST" data-validate="true">
                            @csrf 
        
                            <x-dynamic-form 
                                :fields="[
                                    ['label'=>'Nombre','type'=>'text', 'name'=>'name', 'id'=>'name', 'col'=>'sm:col-span-4', 'value'=>old('name'), 'attr'=>'data-validation-rules=required|max:255 data-field-name=nombre'],
                                    ['label'=>'Estado','type'=>'switch', 'name'=>'status', 'id'=>'status', 'col'=>'sm:col-span-4', 'value'=>old('status', true)]
                                    ]" 
                                >
                                <h2 class="text-base font-semibold leading-7 text-black">Registro de proveedor</h2>
                                <p class="mt-1 text-sm leading-6 text-black">Por favor, complete los siguientes campos:</p>
                            
                            </x-dynamic-form>
       
                            <div class="mt-6 flex items-center justify-end gap-x-6">
                                <x-dynamic-button-link :type="'cancel'" :action="route('providers.index')" />
                                <x-dynamic-button-link :type="'save'" />
                            </div>
                        </form>
                        
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</x-app-layout>


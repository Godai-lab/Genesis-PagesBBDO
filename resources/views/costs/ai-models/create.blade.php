<x-app-layout>
    <x-slot name="title">Génesis - Modelos IA - Crear</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Modelos IA') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="max-w-2xl mx-auto">
                        <form action="{{ route('ai-models.store')}}" method="POST" data-validate="true">
                            @csrf 
        
                            @php
                                $providerList = $providers->map(function($provider) {
                                    return ['id' => $provider->id, 'name' => $provider->name];
                                })->toArray();
                                
                                $modelTypeList = [
                                    ['id' => 'text', 'name' => 'Texto'],
                                    ['id' => 'image', 'name' => 'Imagen'],
                                    ['id' => 'video', 'name' => 'Video'],
                                    ['id' => 'audio', 'name' => 'Audio'],
                                    ['id' => 'service', 'name' => 'Servicio'],
                                    ['id' => 'presentation', 'name' => 'Presentación']
                                ];
                                
                                $pricingTypeList = [
                                    ['id' => 'per_token', 'name' => 'Por Token'],
                                    ['id' => 'per_generation', 'name' => 'Por Generación'],
                                    ['id' => 'per_second', 'name' => 'Por Segundo'],
                                    ['id' => 'per_credit', 'name' => 'Por Crédito']
                                ];
                            @endphp

                            <div x-data="{ pricingType: '{{ old('pricing_type', '') }}' }">
                                <x-dynamic-form 
                                    :fields="[
                                        ['label'=>'Proveedor','type'=>'select', 'name'=>'provider_id', 'id'=>'provider_id', 'col'=>'sm:col-span-4', 'value'=>old('provider_id'), 'attr'=>'data-validation-rules=required data-field-name=proveedor', 'list'=>$providerList],
                                        ['label'=>'Nombre','type'=>'text', 'name'=>'name', 'id'=>'name', 'col'=>'sm:col-span-4', 'value'=>old('name'), 'attr'=>'data-validation-rules=required|max:255 data-field-name=nombre'],
                                        ['label'=>'Slug (Nombre real del modelo en API)','type'=>'text', 'name'=>'slug', 'id'=>'slug', 'col'=>'sm:col-span-4', 'value'=>old('slug'), 'attr'=>'data-field-name=slug', 'placeholder'=>'Ej: gemini3.0-flash (opcional)'],
                                        ['label'=>'Tipo de modelo','type'=>'select', 'name'=>'model_type', 'id'=>'model_type', 'col'=>'sm:col-span-4', 'value'=>old('model_type'), 'attr'=>'data-validation-rules=required data-field-name=tipo_de_modelo', 'list'=>$modelTypeList],
                                        ['label'=>'Disponible hasta','type'=>'date', 'name'=>'available_until', 'id'=>'available_until', 'col'=>'sm:col-span-4', 'value'=>old('available_until'), 'attr'=>'data-field-name=disponible_hasta', 'placeholder'=>'Dejar vacío si es permanente'],
                                        ['label'=>'Estado','type'=>'switch', 'name'=>'status', 'id'=>'status', 'col'=>'sm:col-span-4', 'value'=>old('status', true)]
                                        ]" 
                                    >
                                    <h2 class="text-base font-semibold leading-7 text-black">Registro de modelo IA</h2>
                                    <p class="mt-1 text-sm leading-6 text-black">Por favor, complete los siguientes campos:</p>
                                
                                </x-dynamic-form>
                                
                                <x-dynamic-form 
                                    :fields="[
                                        ['label'=>'Fecha efectiva desde','type'=>'date', 'name'=>'effective_from', 'id'=>'effective_from', 'col'=>'sm:col-span-3', 'value'=>old('effective_from', date('Y-m-d')), 'attr'=>'data-validation-rules=required data-field-name=fecha_efectiva_desde'],
                                        ['label'=>'Fecha efectiva hasta','type'=>'date', 'name'=>'effective_to', 'id'=>'effective_to', 'col'=>'sm:col-span-3', 'value'=>old('effective_to'), 'attr'=>'data-field-name=fecha_efectiva_hasta'],
                                        ['label'=>'Margen de ganancia (%)','type'=>'number', 'name'=>'markup_percentage', 'id'=>'markup_percentage', 'col'=>'sm:col-span-3', 'value'=>old('markup_percentage'), 'attr'=>'step=0.01 min=0 max=100 data-field-name=margen_de_ganancia', 'placeholder'=>'Opcional'],
                                        ['label'=>'Estado del precio','type'=>'switch', 'name'=>'pricing_status', 'id'=>'pricing_status', 'col'=>'sm:col-span-3', 'value'=>old('pricing_status', true)]
                                        ]" 
                                    >
                                    <h2 class="text-base font-semibold leading-7 text-black pt-4">Información de precios</h2>
                                    <p class="mt-1 text-sm leading-6 text-black">Complete los datos de precios para este modelo:</p>
                                
                                </x-dynamic-form>
                                
                                <!-- Tipo de precio y campos dinámicos -->
                                <div class="border-b border-gray-700 pb-12 mb-6">
                                    <div class="mt-10 grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
                                        <!-- Tipo de precio -->
                                        <div class="sm:col-span-6">
                                            <label for="pricing_type" class="block text-sm font-medium leading-6 text-black dark:text-gray-100">
                                                Tipo de precio
                                                <span class="text-red-500">*</span>
                                            </label>
                                            <div class="mt-2">
                                                <select 
                                                    name="pricing_type" 
                                                    id="pricing_type"
                                                    x-model="pricingType"
                                                    data-validation-rules="required"
                                                    data-field-name="tipo_de_precio"
                                                    class="block w-full rounded-lg border-1 py-1.5 text-black dark:text-gray-100 shadow-sm ring-1 ring-inset ring-gray-600 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-black dark:focus:ring-indigo-600 sm:text-sm sm:leading-6 bg-transparent dark:bg-gray-700"
                                                >
                                                    <option value="">Seleccione un tipo</option>
                                                    @foreach($pricingTypeList as $type)
                                                        <option value="{{ $type['id'] }}" {{ old('pricing_type') == $type['id'] ? 'selected' : '' }}>
                                                            {{ $type['name'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <x-input-error :messages="$errors->get('pricing_type')" class="mt-2" />
                                        </div>
                                        
                                        <!-- Campos dinámicos según el tipo de pricing -->
                                        <!-- Campos para per_token -->
                                        <div x-show="pricingType === 'per_token'" x-cloak class="col-span-full">
                                            <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
                                                <div class="sm:col-span-3">
                                                    <label for="input_price" class="block text-sm font-medium leading-6 text-black dark:text-gray-100">
                                                        Precio por millón de tokens de entrada (USD)
                                                        <span class="text-red-500">*</span>
                                                    </label>
                                                    <div class="mt-2">
                                                        <input 
                                                            type="number" 
                                                            name="input_price" 
                                                            id="input_price" 
                                                            step="0.01"
                                                            min="0"
                                                            value="{{ old('input_price') }}"
                                                            placeholder="1.25"
                                                            class="block w-full rounded-lg border-1 py-1.5 text-black dark:text-gray-100 shadow-sm ring-1 ring-inset ring-gray-600 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-black dark:focus:ring-indigo-600 sm:text-sm sm:leading-6 bg-transparent dark:bg-gray-700"
                                                            :data-validation-rules="pricingType === 'per_token' ? 'required' : ''"
                                                            :disabled="pricingType !== 'per_token'"
                                                            data-field-name="precio_por_millon_tokens_entrada"
                                                        >
                                                    </div>
                                                    <p class="mt-1 text-xs text-gray-500">Precio por millón de tokens de entrada</p>
                                                    <x-input-error :messages="$errors->get('input_price')" class="mt-2" />
                                                </div>
                                                <div class="sm:col-span-3">
                                                    <label for="output_price" class="block text-sm font-medium leading-6 text-black dark:text-gray-100">
                                                        Precio por millón de tokens de salida (USD)
                                                        <span class="text-red-500">*</span>
                                                    </label>
                                                    <div class="mt-2">
                                                        <input 
                                                            type="number" 
                                                            name="output_price" 
                                                            id="output_price" 
                                                            step="0.01"
                                                            min="0"
                                                            value="{{ old('output_price') }}"
                                                            placeholder="10.00"
                                                            class="block w-full rounded-lg border-1 py-1.5 text-black dark:text-gray-100 shadow-sm ring-1 ring-inset ring-gray-600 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-black dark:focus:ring-indigo-600 sm:text-sm sm:leading-6 bg-transparent dark:bg-gray-700"
                                                            :data-validation-rules="pricingType === 'per_token' ? 'required' : ''"
                                                            :disabled="pricingType !== 'per_token'"
                                                            data-field-name="precio_por_millon_tokens_salida"
                                                        >
                                                    </div>
                                                    <p class="mt-1 text-xs text-gray-500">Precio por millón de tokens de salida</p>
                                                    <x-input-error :messages="$errors->get('output_price')" class="mt-2" />
                                                </div>
                                                
                                                <!-- Campos opcionales para modelos especiales (ej: sonar-deep-research) -->
                                                <div class="sm:col-span-6 mt-4 pt-4 border-t border-gray-300 dark:border-gray-600">
                                                    <p class="text-sm font-medium text-black dark:text-gray-100 mb-4">
                                                        Precios adicionales (Opcional - Solo para modelos con costos especiales)
                                                    </p>
                                                    <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-3">
                                                        <div class="sm:col-span-1">
                                                            <label for="citation_price" class="block text-sm font-medium leading-6 text-black dark:text-gray-100">
                                                                Precio por millón de tokens de citación (USD)
                                                            </label>
                                                            <div class="mt-2">
                                                                <input 
                                                                    type="number" 
                                                                    name="citation_price" 
                                                                    id="citation_price" 
                                                                    step="0.01"
                                                                    min="0"
                                                                    value="{{ old('citation_price') }}"
                                                                    placeholder="2.00"
                                                                    class="block w-full rounded-lg border-1 py-1.5 text-black dark:text-gray-100 shadow-sm ring-1 ring-inset ring-gray-600 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-black dark:focus:ring-indigo-600 sm:text-sm sm:leading-6 bg-transparent dark:bg-gray-700"
                                                                    :disabled="pricingType !== 'per_token'"
                                                                    data-field-name="precio_por_millon_tokens_citacion"
                                                                >
                                                            </div>
                                                            <p class="mt-1 text-xs text-gray-500">Opcional: Para modelos con tokens de citación</p>
                                                            <x-input-error :messages="$errors->get('citation_price')" class="mt-2" />
                                                        </div>
                                                        <div class="sm:col-span-1">
                                                            <label for="reasoning_price" class="block text-sm font-medium leading-6 text-black dark:text-gray-100">
                                                                Precio por millón de tokens de razonamiento (USD)
                                                            </label>
                                                            <div class="mt-2">
                                                                <input 
                                                                    type="number" 
                                                                    name="reasoning_price" 
                                                                    id="reasoning_price" 
                                                                    step="0.01"
                                                                    min="0"
                                                                    value="{{ old('reasoning_price') }}"
                                                                    placeholder="3.00"
                                                                    class="block w-full rounded-lg border-1 py-1.5 text-black dark:text-gray-100 shadow-sm ring-1 ring-inset ring-gray-600 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-black dark:focus:ring-indigo-600 sm:text-sm sm:leading-6 bg-transparent dark:bg-gray-700"
                                                                    :disabled="pricingType !== 'per_token'"
                                                                    data-field-name="precio_por_millon_tokens_razonamiento"
                                                                >
                                                            </div>
                                                            <p class="mt-1 text-xs text-gray-500">Opcional: Para modelos con tokens de razonamiento</p>
                                                            <x-input-error :messages="$errors->get('reasoning_price')" class="mt-2" />
                                                        </div>
                                                        <div class="sm:col-span-1">
                                                            <label for="search_query_price" class="block text-sm font-medium leading-6 text-black dark:text-gray-100">
                                                                Precio por mil consultas de búsqueda (USD)
                                                            </label>
                                                            <div class="mt-2">
                                                                <input 
                                                                    type="number" 
                                                                    name="search_query_price" 
                                                                    id="search_query_price" 
                                                                    step="0.01"
                                                                    min="0"
                                                                    value="{{ old('search_query_price') }}"
                                                                    placeholder="5.00"
                                                                    class="block w-full rounded-lg border-1 py-1.5 text-black dark:text-gray-100 shadow-sm ring-1 ring-inset ring-gray-600 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-black dark:focus:ring-indigo-600 sm:text-sm sm:leading-6 bg-transparent dark:bg-gray-700"
                                                                    :disabled="pricingType !== 'per_token'"
                                                                    data-field-name="precio_por_mil_consultas_busqueda"
                                                                >
                                                            </div>
                                                            <p class="mt-1 text-xs text-gray-500">Opcional: Precio por mil (1K) consultas de búsqueda</p>
                                                            <x-input-error :messages="$errors->get('search_query_price')" class="mt-2" />
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Campos para per_generation -->
                                        <div x-show="pricingType === 'per_generation'" x-cloak class="sm:col-span-4">
                                            <label for="price_per_generation" class="block text-sm font-medium leading-6 text-black dark:text-gray-100">
                                                Precio por generación (USD)
                                                <span class="text-red-500">*</span>
                                            </label>
                                            <div class="mt-2">
                                                <input 
                                                    type="number" 
                                                    name="price_per_generation" 
                                                    id="price_per_generation" 
                                                    step="0.00000001"
                                                    min="0"
                                                    value="{{ old('price_per_generation') }}"
                                                    placeholder="0.04"
                                                    class="block w-full rounded-lg border-1 py-1.5 text-black dark:text-gray-100 shadow-sm ring-1 ring-inset ring-gray-600 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-black dark:focus:ring-indigo-600 sm:text-sm sm:leading-6 bg-transparent dark:bg-gray-700"
                                                    :data-validation-rules="pricingType === 'per_generation' ? 'required' : ''"
                                                    :disabled="pricingType !== 'per_generation'"
                                                    data-field-name="precio_por_generacion"
                                                >
                                            </div>
                                            <x-input-error :messages="$errors->get('price_per_generation')" class="mt-2" />
                                        </div>
                                        
                                        <!-- Campos para per_second -->
                                        <div x-show="pricingType === 'per_second'" x-cloak class="col-span-full">
                                            <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
                                                <div class="sm:col-span-3">
                                                    <label for="price_per_second" class="block text-sm font-medium leading-6 text-black dark:text-gray-100">
                                                        Precio por segundo (USD)
                                                        <span class="text-red-500">*</span>
                                                    </label>
                                                    <div class="mt-2">
                                                        <input 
                                                            type="number" 
                                                            name="price_per_second" 
                                                            id="price_per_second" 
                                                            step="0.00000001"
                                                            min="0"
                                                            value="{{ old('price_per_second') }}"
                                                            placeholder="0.01"
                                                            class="block w-full rounded-lg border-1 py-1.5 text-black dark:text-gray-100 shadow-sm ring-1 ring-inset ring-gray-600 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-black dark:focus:ring-indigo-600 sm:text-sm sm:leading-6 bg-transparent dark:bg-gray-700"
                                                            :data-validation-rules="pricingType === 'per_second' ? 'required' : ''"
                                                            :disabled="pricingType !== 'per_second'"
                                                            data-field-name="precio_por_segundo"
                                                        >
                                                    </div>
                                                    <x-input-error :messages="$errors->get('price_per_second')" class="mt-2" />
                                                </div>
                                                <div class="sm:col-span-3">
                                                    <label for="minimum_seconds" class="block text-sm font-medium leading-6 text-black dark:text-gray-100">
                                                        Segundos mínimos
                                                        <span class="text-red-500">*</span>
                                                    </label>
                                                    <div class="mt-2">
                                                        <input 
                                                            type="number" 
                                                            name="minimum_seconds" 
                                                            id="minimum_seconds" 
                                                            step="1"
                                                            min="1"
                                                            value="{{ old('minimum_seconds', 1) }}"
                                                            placeholder="1"
                                                            class="block w-full rounded-lg border-1 py-1.5 text-black dark:text-gray-100 shadow-sm ring-1 ring-inset ring-gray-600 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-black dark:focus:ring-indigo-600 sm:text-sm sm:leading-6 bg-transparent dark:bg-gray-700"
                                                            :data-validation-rules="pricingType === 'per_second' ? 'required' : ''"
                                                            :disabled="pricingType !== 'per_second'"
                                                            data-field-name="segundos_minimos"
                                                        >
                                                    </div>
                                                    <x-input-error :messages="$errors->get('minimum_seconds')" class="mt-2" />
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Campos para per_credit -->
                                        <div x-show="pricingType === 'per_credit'" x-cloak class="sm:col-span-4">
                                            <label for="price_per_credit" class="block text-sm font-medium leading-6 text-black dark:text-gray-100">
                                                Precio por crédito (USD)
                                                <span class="text-red-500">*</span>
                                            </label>
                                            <div class="mt-2">
                                                <input 
                                                    type="number" 
                                                    name="price_per_credit" 
                                                    id="price_per_credit" 
                                                    step="0.0001"
                                                    min="0"
                                                    value="{{ old('price_per_credit') }}"
                                                    placeholder="0.004"
                                                    class="block w-full rounded-lg border-1 py-1.5 text-black dark:text-gray-100 shadow-sm ring-1 ring-inset ring-gray-600 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-black dark:focus:ring-indigo-600 sm:text-sm sm:leading-6 bg-transparent dark:bg-gray-700"
                                                    :data-validation-rules="pricingType === 'per_credit' ? 'required' : ''"
                                                    :disabled="pricingType !== 'per_credit'"
                                                    data-field-name="precio_por_credito"
                                                >
                                            </div>
                                            <p class="mt-1 text-xs text-gray-500">Precio por cada crédito consumido (ej: 0.004 = $0.004 USD por crédito)</p>
                                            <x-input-error :messages="$errors->get('price_per_credit')" class="mt-2" />
                                        </div>
                                    </div>
                                </div>
                            </div>
       
                            <div class="mt-6 flex items-center justify-end gap-x-6">
                                <x-dynamic-button-link :type="'cancel'" :action="route('ai-models.index')" />
                                <x-dynamic-button-link :type="'save'" />
                            </div>
                        </form>
                        
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</x-app-layout>


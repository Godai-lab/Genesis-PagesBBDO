<x-app-layout>
    <x-slot name="title">Génesis - Modelos IA - Index</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Modelos IA') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="block">
                        <form method="GET" action="{{route('ai-models.index')}}">
                            <div class="flex items-center flex-wrap justify-start gap-3">
                                <div class="w-72">
                                    <x-dynamic-input :id="'search'" :name="'search'" :value="(isset($_GET['search']))?$_GET['search']:''" :type="'text'" :label="'Buscar'" class=""  />
                                </div>
                                <div class="w-72">
                                    <div class="relative h-10 w-full min-w-[200px]">
                                        <select
                                            name="provider_id"
                                            id="provider_id"
                                            class="peer h-full w-full rounded-[7px] border border-black border-t-transparent bg-transparent px-3 py-2.5 text-sm font-normal text-black outline outline-0 transition-all placeholder-shown:border placeholder-shown:border-black placeholder-shown:border-t-black focus:border-2 focus:border-black focus:border-t-transparent focus:outline-0 disabled:border-0 disabled:bg-black"
                                            style="box-shadow: none;"
                                        >
                                            <option value="">Todos los proveedores</option>
                                            @foreach($providers as $provider)
                                                <option value="{{ $provider->id }}" {{ (isset($_GET['provider_id']) && $_GET['provider_id'] == $provider->id) ? 'selected' : '' }}>{{ $provider->name }}</option>
                                            @endforeach
                                        </select>
                                        <label 
                                            class="before:content[' '] after:content[' '] pointer-events-none absolute left-0 -top-1.5 flex h-full w-full select-none text-[11px] font-normal leading-tight text-black transition-all before:pointer-events-none before:mt-[6.5px] before:mr-1 before:box-border before:block before:h-1.5 before:w-2.5 before:rounded-tl-md before:border-t before:border-l before:border-black before:transition-all after:pointer-events-none after:mt-[6.5px] after:ml-1 after:box-border after:block after:h-1.5 after:w-2.5 after:flex-grow after:rounded-tr-md after:border-t after:border-r after:border-black after:transition-all peer-placeholder-shown:text-sm peer-placeholder-shown:leading-[3.75] peer-placeholder-shown:text-black peer-placeholder-shown:before:border-transparent peer-placeholder-shown:after:border-transparent peer-focus:text-[11px] peer-focus:leading-tight peer-focus:text-black peer-focus:before:border-t-2 peer-focus:before:border-l-2 peer-focus:before:border-black peer-focus:after:border-t-2 peer-focus:after:border-r-2 peer-focus:after:border-black peer-disabled:text-transparent peer-disabled:before:border-transparent peer-disabled:after:border-transparent peer-disabled:peer-placeholder-shown:text-gray-600">
                                            Proveedor
                                        </label>
                                    </div>
                                </div>
                                <div class="w-72">
                                    <div class="relative h-10 w-full min-w-[200px]">
                                        <select
                                            name="model_type"
                                            id="model_type"
                                            class="peer h-full w-full rounded-[7px] border border-black border-t-transparent bg-transparent px-3 py-2.5 text-sm font-normal text-black outline outline-0 transition-all placeholder-shown:border placeholder-shown:border-black placeholder-shown:border-t-black focus:border-2 focus:border-black focus:border-t-transparent focus:outline-0 disabled:border-0 disabled:bg-black"
                                            style="box-shadow: none;"
                                        >
                                            <option value="">Todos los tipos</option>
                                            <option value="text" {{ (isset($_GET['model_type']) && $_GET['model_type'] == 'text') ? 'selected' : '' }}>Texto</option>
                                            <option value="image" {{ (isset($_GET['model_type']) && $_GET['model_type'] == 'image') ? 'selected' : '' }}>Imagen</option>
                                            <option value="video" {{ (isset($_GET['model_type']) && $_GET['model_type'] == 'video') ? 'selected' : '' }}>Video</option>
                                            <option value="audio" {{ (isset($_GET['model_type']) && $_GET['model_type'] == 'audio') ? 'selected' : '' }}>Audio</option>
                                        </select>
                                        <label 
                                            class="before:content[' '] after:content[' '] pointer-events-none absolute left-0 -top-1.5 flex h-full w-full select-none text-[11px] font-normal leading-tight text-black transition-all before:pointer-events-none before:mt-[6.5px] before:mr-1 before:box-border before:block before:h-1.5 before:w-2.5 before:rounded-tl-md before:border-t before:border-l before:border-black before:transition-all after:pointer-events-none after:mt-[6.5px] after:ml-1 after:box-border after:block after:h-1.5 after:w-2.5 after:flex-grow after:rounded-tr-md after:border-t after:border-r after:border-black after:transition-all peer-placeholder-shown:text-sm peer-placeholder-shown:leading-[3.75] peer-placeholder-shown:text-black peer-placeholder-shown:before:border-transparent peer-placeholder-shown:after:border-transparent peer-focus:text-[11px] peer-focus:leading-tight peer-focus:text-black peer-focus:before:border-t-2 peer-focus:before:border-l-2 peer-focus:before:border-black peer-focus:after:border-t-2 peer-focus:after:border-r-2 peer-focus:after:border-black peer-disabled:text-transparent peer-disabled:before:border-transparent peer-disabled:after:border-transparent peer-disabled:peer-placeholder-shown:text-gray-600">
                                            Tipo
                                        </label>
                                    </div>
                                </div>
                                <div class="w-72">
                                    <div class="relative h-10 w-full min-w-[200px]">
                                        <select
                                            name="status"
                                            id="status"
                                            class="peer h-full w-full rounded-[7px] border border-black border-t-transparent bg-transparent px-3 py-2.5 text-sm font-normal text-black outline outline-0 transition-all placeholder-shown:border placeholder-shown:border-black placeholder-shown:border-t-black focus:border-2 focus:border-black focus:border-t-transparent focus:outline-0 disabled:border-0 disabled:bg-black"
                                            style="box-shadow: none;"
                                        >
                                            <option value="">Todos</option>
                                            <option value="active" {{ (isset($_GET['status']) && $_GET['status'] == 'active') ? 'selected' : '' }}>Activo</option>
                                            <option value="inactive" {{ (isset($_GET['status']) && $_GET['status'] == 'inactive') ? 'selected' : '' }}>Inactivo</option>
                                        </select>
                                        <label 
                                            class="before:content[' '] after:content[' '] pointer-events-none absolute left-0 -top-1.5 flex h-full w-full select-none text-[11px] font-normal leading-tight text-black transition-all before:pointer-events-none before:mt-[6.5px] before:mr-1 before:box-border before:block before:h-1.5 before:w-2.5 before:rounded-tl-md before:border-t before:border-l before:border-black before:transition-all after:pointer-events-none after:mt-[6.5px] after:ml-1 after:box-border after:block after:h-1.5 after:w-2.5 after:flex-grow after:rounded-tr-md after:border-t after:border-r after:border-black after:transition-all peer-placeholder-shown:text-sm peer-placeholder-shown:leading-[3.75] peer-placeholder-shown:text-black peer-placeholder-shown:before:border-transparent peer-placeholder-shown:after:border-transparent peer-focus:text-[11px] peer-focus:leading-tight peer-focus:text-black peer-focus:before:border-t-2 peer-focus:before:border-l-2 peer-focus:before:border-black peer-focus:after:border-t-2 peer-focus:after:border-r-2 peer-focus:after:border-black peer-disabled:text-transparent peer-disabled:before:border-transparent peer-disabled:after:border-transparent peer-disabled:peer-placeholder-shown:text-gray-600">
                                            Estado
                                        </label>
                                    </div>
                                </div>
                                <div class="flex">
                                    <div class="flex align-middle gap-2">
                                        <x-dynamic-button-link :type="'search'"  />
                                        <x-dynamic-button-link :type="'clean'" :action="route('ai-models.index')" />
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="block p-3"></div>
                    <x-dynamic-table 
                        :thead="['Nombre','Proveedor','Tipo','Estado','Disponible hasta','Fecha creación','Fecha actualización']" 
                        :tbody="['name','provider.name','model_type','status','available_until','created_at','updated_at']" 
                        :route="'ai-models'"
                        :data="$models" >
                    </x-dynamic-table>
                    
                </div>
            </div>
        </div>
    </div>
</x-app-layout>


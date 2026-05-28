<div>
    <style>
        [x-cloak] { display: none !important; }
    </style>
    
    <x-slot name="title">Génesis - Detalle de Uso</x-slot>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-black dark:text-gray-200 leading-tight">
                {{ __('Detalle de Uso de Herramientas') }}
            </h2>
            <a href="{{ route('usage-metrics') }}" 
               class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-black dark:text-gray-100 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition inline-flex items-center gap-2">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Volver
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Información del Usuario y Cuenta -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Información del Usuario -->
                        <div>
                            <h3 class="text-lg font-semibold text-black dark:text-gray-100 mb-3">
                                Usuario
                            </h3>
                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-gray-500 dark:text-gray-400 w-20">Nombre:</span>
                                    <span class="text-sm font-medium text-black dark:text-gray-100">{{ $user->name }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-gray-500 dark:text-gray-400 w-20">Email:</span>
                                    <span class="text-sm text-black dark:text-gray-100">{{ $user->email }}</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Información de la Cuenta -->
                        <div>
                            <h3 class="text-lg font-semibold text-black dark:text-gray-100 mb-3">
                                Cuenta
                            </h3>
                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-gray-500 dark:text-gray-400 w-20">Nombre:</span>
                                    <span class="text-sm font-medium text-black dark:text-gray-100">{{ $account->name }}</span>
                                </div>
                                @if($dateFrom && $dateTo)
                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-gray-500 dark:text-gray-400 w-20">Período:</span>
                                    <span class="text-sm text-black dark:text-gray-100">
                                        {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} - 
                                        {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}
                                    </span>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Métricas Resumidas -->
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-3 mb-6">
                <!-- Total de Usos -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-gray-100 dark:bg-gray-700 rounded-lg p-3">
                                <svg class="h-6 w-6 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                            <div class="ml-4 flex-1">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total de Usos</p>
                                <p class="text-2xl font-bold text-black dark:text-gray-100">{{ number_format($totalRecords) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Costo Real -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-gray-100 dark:bg-gray-700 rounded-lg p-3">
                                <svg class="h-6 w-6 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="ml-4 flex-1">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Costo Real</p>
                                <p class="text-2xl font-bold text-black dark:text-gray-100">${{ $this->formatCurrency($totalCostReal) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Costo Final -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-gray-100 dark:bg-gray-700 rounded-lg p-3">
                                <svg class="h-6 w-6 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                            <div class="ml-4 flex-1">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Costo Final</p>
                                <p class="text-2xl font-bold text-black dark:text-gray-100">${{ $this->formatCurrency($totalCostFinal) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Resumen por Herramienta -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-black dark:text-gray-100 mb-4">
                        Resumen por Herramienta
                    </h3>
                    @if($toolsSummary->count() > 0)
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($toolsSummary as $tool)
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="text-sm font-semibold text-black dark:text-gray-100">
                                            {{ $tool['name'] }}
                                        </h4>
                                        <span class="text-xs font-medium px-2 py-1 bg-gray-200 dark:bg-gray-600 text-gray-800 dark:text-gray-200 rounded">
                                            {{ $tool['count'] }} usos
                                        </span>
                                    </div>
                                    <div class="flex items-baseline justify-between">
                                        <span class="text-lg font-bold text-black dark:text-gray-100">
                                            ${{ $this->formatCurrency($tool['total_cost']) }}
                                        </span>
                                        <span class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ number_format($tool['percentage'], 1) }}%
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">No hay datos disponibles</p>
                    @endif
                </div>
            </div>

            <!-- Tabla Detallada de Usos -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-black dark:text-gray-100 mb-4">
                        Detalle de Todos los Usos
                    </h3>
                    @if($detailedRecords->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            ID
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Fecha y Hora
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Herramienta
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Modelos
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Generado
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Tokens In
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Tokens Out
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Costo Real
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Costo Final
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700" x-data="{ openRows: {} }">
                                    @foreach($detailedRecords as $index => $record)
                                        <!-- Fila principal -->
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                <div class="flex items-center gap-2">
                                                    @if($record['has_processes'])
                                                        <button @click="openRows[{{ $index }}] = !openRows[{{ $index }}]" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300 focus:outline-none">
                                                            <svg x-show="!openRows[{{ $index }}]" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                            </svg>
                                                            <svg x-show="openRows[{{ $index }}]" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-cloak>
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                            </svg>
                                                        </button>
                                                    @endif
                                                    #{{ $record['id'] }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-black dark:text-gray-100">
                                                    {{ $record['date']->format('d/m/Y') }}
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $record['date']->format('H:i:s') }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-200 text-gray-800 dark:bg-gray-600 dark:text-gray-200">
                                                    {{ $record['tool'] }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                @if(count($record['models']) > 0)
                                                    <div class="flex flex-col gap-2">
                                                        @foreach($record['models'] as $model)
                                                            <div class="flex flex-col gap-1">
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-black text-white dark:bg-white dark:text-black">
                                                                    {{ $model['name'] }}
                                                                </span>
                                                                @if(isset($model['available_until']) && $model['available_until'])
                                                                    @if($model['availability_status'] === 'disponible')
                                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs {{ $model['days_until_expiration'] <= 30 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100' : 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' }}">
                                                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                            </svg>
                                                                            Expira en {{ $model['days_until_expiration'] }} {{ $model['days_until_expiration'] == 1 ? 'día' : 'días' }}
                                                                        </span>
                                                                    @elseif($model['availability_status'] === 'expirado')
                                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">
                                                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                            </svg>
                                                                            Expirado
                                                                        </span>
                                                                    @endif
                                                                @else
                                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-600 dark:bg-gray-600 dark:text-gray-300">
                                                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                                        </svg>
                                                                        Permanente
                                                                    </span>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <span class="text-sm text-gray-400 dark:text-gray-500 italic">
                                                        Sin modelo
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4">
                                                @if($record['generated_name'])
                                                    <div class="text-sm font-medium text-black dark:text-gray-100">
                                                        {{ $record['generated_name'] }}
                                                    </div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                                        ID: {{ $record['generated_id'] }}
                                                    </div>
                                                @else
                                                    <span class="text-sm text-gray-400 dark:text-gray-500 italic">
                                                        Sin generado asociado
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500 dark:text-gray-400">
                                                {{ $record['tokens_input'] > 0 ? number_format($record['tokens_input']) : '-' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500 dark:text-gray-400">
                                                {{ $record['tokens_output'] > 0 ? number_format($record['tokens_output']) : '-' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-black dark:text-gray-100">
                                                ${{ $this->formatCurrency($record['cost_real']) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold text-black dark:text-gray-100">
                                                ${{ $this->formatCurrency($record['cost_final']) }}
                                            </td>
                                        </tr>
                                        
                                        <!-- Fila expandible con detalles de procesos -->
                                        @if($record['has_processes'])
                                        <tr x-show="openRows[{{ $index }}]" x-transition x-cloak>
                                            <td colspan="9" class="px-0 py-0">
                                                <div class="bg-gray-50 dark:bg-gray-900 px-6 py-4">
                                                    <h4 class="text-sm font-semibold text-black dark:text-gray-100 mb-3 flex items-center gap-2">
                                                        <svg class="h-5 w-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                        </svg>
                                                        Detalle de Sub-Procesos ({{ count($record['processes_detail']['processes']) }} procesos)
                                                    </h4>
                                                    
                                                    <div class="space-y-3">
                                                        @foreach($record['processes_detail']['processes'] as $processIndex => $process)
                                                        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                                                            <div class="flex items-start justify-between mb-3">
                                                                <div class="flex items-center gap-2">
                                                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-300 text-xs font-semibold">
                                                                        {{ $processIndex + 1 }}
                                                                    </span>
                                                                    <div>
                                                                        <h5 class="text-sm font-semibold text-black dark:text-gray-100">
                                                                            @php
                                                                                $stepName = $process['step'] ?? null;
                                                                                $formattedName = 'Proceso ' . ($processIndex + 1);
                                                                                if ($stepName && $stepName !== 'unknown') {
                                                                                    // Convertir camelCase/PascalCase a espacios
                                                                                    $formatted = preg_replace('/(?<!^)[A-Z]/', ' $0', $stepName);
                                                                                    // Reemplazar guiones y guiones bajos con espacios
                                                                                    $formatted = str_replace(['_', '-'], ' ', $formatted);
                                                                                    // Capitalizar cada palabra
                                                                                    $formattedName = ucwords(strtolower($formatted));
                                                                                }
                                                                                echo $formattedName;
                                                                            @endphp
                                                                        </h5>
                                                                        @if(isset($process['step']) && $process['step'] !== 'unknown')
                                                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $process['step'] }}</p>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                                @if(isset($process['model']))
                                                                <div class="flex flex-col items-end gap-1">
                                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-black text-white dark:bg-white dark:text-black">
                                                                        {{ $process['model'] }}
                                                                    </span>
                                                                    @if(isset($process['model_availability']))
                                                                        @php
                                                                            $availability = $process['model_availability'];
                                                                        @endphp
                                                                        @if($availability['available_until'])
                                                                            @if($availability['availability_status'] === 'disponible')
                                                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium {{ $availability['days_until_expiration'] <= 30 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100' : 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' }}">
                                                                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                                    </svg>
                                                                                    {{ $availability['days_until_expiration'] }} {{ $availability['days_until_expiration'] == 1 ? 'día' : 'días' }}
                                                                                </span>
                                                                            @elseif($availability['availability_status'] === 'expirado')
                                                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">
                                                                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                                    </svg>
                                                                                    Expirado
                                                                                </span>
                                                                            @endif
                                                                        @else
                                                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-600 dark:text-gray-300">
                                                                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                                                </svg>
                                                                                Permanente
                                                                            </span>
                                                                        @endif
                                                                    @endif
                                                                </div>
                                                                @endif
                                                            </div>
                                                            
                                                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                                                <!-- Tokens Input -->
                                                                @if(isset($process['usage_metrics']['tokens']['input']))
                                                                <div class="bg-blue-50 dark:bg-blue-900/20 rounded p-2">
                                                                    <div class="text-xs text-blue-600 dark:text-blue-400 font-medium">Tokens Input</div>
                                                                    <div class="text-sm font-semibold text-black dark:text-gray-100">
                                                                        {{ number_format($process['usage_metrics']['tokens']['input']) }}
                                                                    </div>
                                                                </div>
                                                                @endif
                                                                
                                                                <!-- Tokens Output -->
                                                                @if(isset($process['usage_metrics']['tokens']['output']))
                                                                <div class="bg-green-50 dark:bg-green-900/20 rounded p-2">
                                                                    <div class="text-xs text-green-600 dark:text-green-400 font-medium">Tokens Output</div>
                                                                    <div class="text-sm font-semibold text-black dark:text-gray-100">
                                                                        {{ number_format($process['usage_metrics']['tokens']['output']) }}
                                                                    </div>
                                                                </div>
                                                                @endif
                                                                
                                                                <!-- Costo Real -->
                                                                @if(isset($process['cost_total_usd']))
                                                                <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded p-2">
                                                                    <div class="text-xs text-yellow-600 dark:text-yellow-400 font-medium">Costo Real</div>
                                                                    <div class="text-sm font-semibold text-black dark:text-gray-100">
                                                                        ${{ number_format($process['cost_total_usd'], 6) }}
                                                                    </div>
                                                                </div>
                                                                @endif
                                                                
                                                                <!-- Costo Final -->
                                                                @if(isset($process['cost_final_user_usd']))
                                                                <div class="bg-purple-50 dark:bg-purple-900/20 rounded p-2">
                                                                    <div class="text-xs text-purple-600 dark:text-purple-400 font-medium">Costo Final</div>
                                                                    <div class="text-sm font-semibold text-black dark:text-gray-100">
                                                                        ${{ number_format($process['cost_final_user_usd'], 6) }}
                                                                    </div>
                                                                </div>
                                                                @endif
                                                            </div>
                                                            
                                                            <!-- Detalles adicionales si existen -->
                                                            @if(isset($process['prompt']) || isset($process['response']))
                                                            <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                                                                @if(isset($process['prompt']))
                                                                <details class="mb-2">
                                                                    <summary class="cursor-pointer text-xs font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200">
                                                                        Ver Prompt
                                                                    </summary>
                                                                    <div class="mt-2 text-xs text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 rounded p-2 max-h-32 overflow-y-auto">
                                                                        {{ Str::limit($process['prompt'], 500) }}
                                                                    </div>
                                                                </details>
                                                                @endif
                                                                
                                                                @if(isset($process['response']))
                                                                <details>
                                                                    <summary class="cursor-pointer text-xs font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200">
                                                                        Ver Respuesta
                                                                    </summary>
                                                                    <div class="mt-2 text-xs text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 rounded p-2 max-h-32 overflow-y-auto">
                                                                        {{ Str::limit($process['response'], 500) }}
                                                                    </div>
                                                                </details>
                                                                @endif
                                                            </div>
                                                            @endif
                                                        </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        @endif
                                    @endforeach
                                    <!-- Fila de totales -->
                                    <tr class="bg-gray-100 dark:bg-gray-900 font-bold">
                                        <td colspan="5" class="px-6 py-4 text-sm text-black dark:text-gray-100">
                                            TOTAL
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-black dark:text-gray-100">
                                            {{ number_format($detailedRecords->sum('tokens_input')) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-black dark:text-gray-100">
                                            {{ number_format($detailedRecords->sum('tokens_output')) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-black dark:text-gray-100">
                                            ${{ $this->formatCurrency($totalCostReal) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-black dark:text-gray-100">
                                            ${{ $this->formatCurrency($totalCostFinal) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">No hay registros de uso para este usuario en el período seleccionado</p>
                    @endif
                </div>
            </div>

        </div>
    </div>
</div>

<div>
    <x-slot name="title">Génesis - Métricas de Uso</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-black dark:text-gray-200 leading-tight">
            {{ __('Métricas de Uso de IA') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Filtros -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <!-- Botones rápidos de período -->
                    <div class="mb-4 flex flex-wrap gap-2">
                        <button 
                            wire:click="setQuickPeriod('today')"
                            class="px-3 py-1 text-sm bg-gray-100 dark:bg-gray-700 text-black dark:text-gray-100 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition"
                        >
                            Hoy
                        </button>
                        <button 
                            wire:click="setQuickPeriod('week')"
                            class="px-3 py-1 text-sm bg-gray-100 dark:bg-gray-700 text-black dark:text-gray-100 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition"
                        >
                            Esta semana
                        </button>
                        <button 
                            wire:click="setQuickPeriod('month')"
                            class="px-3 py-1 text-sm bg-gray-100 dark:bg-gray-700 text-black dark:text-gray-100 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition"
                        >
                            Este mes
                        </button>
                        <button 
                            wire:click="setQuickPeriod('lastMonth')"
                            class="px-3 py-1 text-sm bg-gray-100 dark:bg-gray-700 text-black dark:text-gray-100 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition"
                        >
                            Mes anterior
                        </button>
                        <button 
                            wire:click="setQuickPeriod('year')"
                            class="px-3 py-1 text-sm bg-gray-100 dark:bg-gray-700 text-black dark:text-gray-100 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition"
                        >
                            Este año
                        </button>
                        <button 
                            wire:click="setQuickPeriod('all')"
                            class="px-3 py-1 text-sm bg-gray-100 dark:bg-gray-700 text-black dark:text-gray-100 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition"
                        >
                            Todo el tiempo
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-6">
                        <!-- Filtro de fecha inicio -->
                        <div>
                            <label for="dateFrom" class="block text-sm font-medium text-black dark:text-gray-100 mb-2">
                                Fecha Inicio
                            </label>
                            <input 
                                type="date" 
                                wire:model.live="dateFrom" 
                                id="dateFrom"
                                class="block w-full rounded-lg border-1 py-2 px-3 text-black dark:text-gray-100 shadow-sm ring-1 ring-inset ring-gray-600 focus:ring-2 focus:ring-inset focus:ring-black dark:focus:ring-indigo-600 sm:text-sm bg-transparent dark:bg-gray-700"
                            >
                        </div>
                        
                        <!-- Filtro de fecha fin -->
                        <div>
                            <label for="dateTo" class="block text-sm font-medium text-black dark:text-gray-100 mb-2">
                                Fecha Fin
                            </label>
                            <input 
                                type="date" 
                                wire:model.live="dateTo" 
                                id="dateTo"
                                class="block w-full rounded-lg border-1 py-2 px-3 text-black dark:text-gray-100 shadow-sm ring-1 ring-inset ring-gray-600 focus:ring-2 focus:ring-inset focus:ring-black dark:focus:ring-indigo-600 sm:text-sm bg-transparent dark:bg-gray-700"
                            >
                        </div>
                        
                        <!-- Filtro de cuenta -->
                        <div>
                            <label for="account" class="block text-sm font-medium text-black dark:text-gray-100 mb-2">
                                Cuenta
                            </label>
                            <select 
                                wire:model.live="selectedAccountId" 
                                id="account"
                                class="block w-full rounded-lg border-1 py-2 px-3 text-black dark:text-gray-100 shadow-sm ring-1 ring-inset ring-gray-600 focus:ring-2 focus:ring-inset focus:ring-black dark:focus:ring-indigo-600 sm:text-sm bg-transparent dark:bg-gray-700"
                            >
                                <option value="">Todas las cuentas</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <!-- Filtro de usuario -->
                        <div>
                            <label for="user" class="block text-sm font-medium text-black dark:text-gray-100 mb-2">
                                Usuario
                            </label>
                            <select 
                                wire:model.live="selectedUserId" 
                                id="user"
                                class="block w-full rounded-lg border-1 py-2 px-3 text-black dark:text-gray-100 shadow-sm ring-1 ring-inset ring-gray-600 focus:ring-2 focus:ring-inset focus:ring-black dark:focus:ring-indigo-600 sm:text-sm bg-transparent dark:bg-gray-700"
                                @if(!$selectedAccountId) disabled @endif
                            >
                                @if(!$selectedAccountId)
                                    <option value="">Selecciona una cuenta primero</option>
                                @else
                                    <option value="">Todos los usuarios</option>
                                    @foreach($availableUsers as $user)
                                        <option value="{{ $user['id'] }}">{{ $user['name'] }} ({{ $user['email'] }})</option>
                                    @endforeach
                                    @if(empty($availableUsers))
                                        <option value="" disabled>Esta cuenta no tiene usuarios asignados</option>
                                    @endif
                                @endif
                            </select>
                        </div>
                        
                        <!-- Filtro de herramienta -->
                        <div>
                            <label for="tool" class="block text-sm font-medium text-black dark:text-gray-100 mb-2">
                                Herramienta
                            </label>
                            <select 
                                wire:model.live="selectedTool" 
                                id="tool"
                                class="block w-full rounded-lg border-1 py-2 px-3 text-black dark:text-gray-100 shadow-sm ring-1 ring-inset ring-gray-600 focus:ring-2 focus:ring-inset focus:ring-black dark:focus:ring-indigo-600 sm:text-sm bg-transparent dark:bg-gray-700"
                            >
                                <option value="">Todas las herramientas</option>
                                @foreach($availableTools as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <!-- Botón para limpiar filtros -->
                        <div class="flex items-end">
                            <button 
                                wire:click="resetFilters"
                                class="w-full px-4 py-2 bg-gray-200 dark:bg-gray-700 text-black dark:text-gray-100 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition"
                            >
                                Limpiar filtros
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tarjetas de métricas principales -->
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-6">
                <!-- Total de registros -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-gray-100 dark:bg-gray-700 rounded-lg p-3">
                                <svg class="h-6 w-6 text-black dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div class="ml-4 flex-1">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total de Registros</p>
                                <p class="text-2xl font-bold text-black dark:text-gray-100">{{ number_format($totalRecords) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Costo total (real) -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-gray-100 dark:bg-gray-700 rounded-lg p-3">
                                <svg class="h-6 w-6 text-black dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

                <!-- Costo final (con margen) -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-gray-100 dark:bg-gray-700 rounded-lg p-3">
                                <svg class="h-6 w-6 text-black dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                            <div class="ml-4 flex-1">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Costo Final</p>
                                <p class="text-2xl font-bold text-black dark:text-gray-100">${{ $this->formatCurrency($totalCostFinal) }}</p>
                                @if($growthPercentage != 0)
                                    <p class="text-xs mt-1 {{ $growthPercentage > 0 ? 'text-red-600' : 'text-green-600' }}">
                                        {{ $growthPercentage > 0 ? '↑' : '↓' }} {{ number_format(abs($growthPercentage), 1) }}% vs período anterior
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ganancia (margen) -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-gray-100 dark:bg-gray-700 rounded-lg p-3">
                                <svg class="h-6 w-6 text-black dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                </svg>
                            </div>
                            <div class="ml-4 flex-1">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Ganancia</p>
                                <p class="text-2xl font-bold text-black dark:text-gray-100">${{ $this->formatCurrency($totalRevenue) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tarjetas de tokens -->
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-3 mb-6">
                <!-- Total de tokens -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-gray-100 dark:bg-gray-700 rounded-lg p-3">
                                <svg class="h-6 w-6 text-black dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                                </svg>
                            </div>
                            <div class="ml-4 flex-1">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total de Tokens</p>
                                <p class="text-2xl font-bold text-black dark:text-gray-100">{{ number_format($totalTokens) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tokens de entrada -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-gray-100 dark:bg-gray-700 rounded-lg p-3">
                                <svg class="h-6 w-6 text-black dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18" />
                                </svg>
                            </div>
                            <div class="ml-4 flex-1">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Tokens Entrada</p>
                                <p class="text-2xl font-bold text-black dark:text-gray-100">{{ number_format($totalInputTokens) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tokens de salida -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-gray-100 dark:bg-gray-700 rounded-lg p-3">
                                <svg class="h-6 w-6 text-black dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                </svg>
                            </div>
                            <div class="ml-4 flex-1">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Tokens Salida</p>
                                <p class="text-2xl font-bold text-black dark:text-gray-100">{{ number_format($totalOutputTokens) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top modelos y cuentas -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 mb-6">
                <!-- Top 5 Modelos -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-black dark:text-gray-100 mb-4">
                            Top 5 Modelos Más Usados
                        </h3>
                        @if($topModels->count() > 0)
                            <div class="space-y-4">
                                @foreach($topModels->take(5) as $model)
                                    <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                        <div class="flex items-center justify-between mb-1">
                                            <div class="flex-1">
                                                <p class="text-sm font-medium text-black dark:text-gray-100">{{ $model['name'] }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $model['provider'] }}</p>
                                            </div>
                                            <div class="text-right">
                                                <p class="text-sm font-bold text-black dark:text-gray-100">${{ $this->formatCurrency($model['total_cost']) }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $model['count'] }} usos</p>
                                            </div>
                                        </div>
                                        @if($model['available_until'])
                                            <div class="mt-2 pt-2 border-t border-gray-200 dark:border-gray-600">
                                                @if($model['availability_status'] === 'disponible')
                                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium {{ $model['days_until_expiration'] <= 30 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100' : 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' }}">
                                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                        Expira en {{ $model['days_until_expiration'] }} {{ $model['days_until_expiration'] == 1 ? 'día' : 'días' }}
                                                    </span>
                                                @elseif($model['availability_status'] === 'expirado')
                                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">
                                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                        Expiró hace {{ abs($model['days_until_expiration']) }} {{ abs($model['days_until_expiration']) == 1 ? 'día' : 'días' }}
                                                    </span>
                                                @endif
                                            </div>
                                        @else
                                            <div class="mt-2 pt-2 border-t border-gray-200 dark:border-gray-600">
                                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-600 dark:text-gray-300">
                                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                    Permanente
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-500 dark:text-gray-400">No hay datos disponibles</p>
                        @endif
                    </div>
                </div>

                <!-- Top 5 Cuentas -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-black dark:text-gray-100 mb-4">
                            Top 5 Cuentas
                        </h3>
                        @if($topAccounts->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Cuenta</th>
                                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Usos</th>
                                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Costo USD</th>
                                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Límite Efectivo</th>
                                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">% Usado</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($topAccounts as $account)
                                            <tr>
                                                <td class="px-4 py-3 text-sm font-medium text-black dark:text-gray-100">
                                                    {{ $account['name'] }}
                                                </td>
                                                <td class="px-4 py-3 text-sm text-right text-black dark:text-gray-100">
                                                    {{ $account['count'] }}
                                                </td>
                                                <td class="px-4 py-3 text-sm text-right text-black dark:text-gray-100">
                                                    ${{ $this->formatCurrency($account['total_cost']) }}
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                                        ({{ \App\Supports\CreditHelper::formatCredits($account['usage_credits']) }} créditos)
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-right text-black dark:text-gray-100">
                                                    @if($account['effective_limit'] !== null)
                                                        {{ \App\Supports\CreditHelper::formatCredits($account['effective_limit']) }} créditos
                                                    @else
                                                        <span class="text-gray-400">Ilimitado</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-sm text-right">
                                                    @if($account['effective_limit'] !== null)
                                                        <span class="px-2 py-1 rounded text-xs font-semibold
                                                            @if($account['percentage_used'] < 70) bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100
                                                            @elseif($account['percentage_used'] < 90) bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100
                                                            @else bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100
                                                            @endif">
                                                            {{ number_format($account['percentage_used'], 1) }}%
                                                        </span>
                                                    @else
                                                        <span class="text-gray-400">-</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-sm text-gray-500 dark:text-gray-400">No hay datos disponibles</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Gastos por Usuario (solo cuando hay cuenta seleccionada) -->
            @if($selectedAccountId && $usersByAccount->count() > 0)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-black dark:text-gray-100 mb-4">
                        Gastos por Usuario de la Cuenta Seleccionada
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Usuario
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Usos
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Costo Real
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Costo Final
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Promedio/Uso
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        % del Total
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($usersByAccount as $userStats)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-black dark:text-gray-100">
                                                {{ $userStats['name'] }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $userStats['email'] }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right">
                                            <div class="text-sm text-black dark:text-gray-100">
                                                {{ number_format($userStats['count']) }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right">
                                            <div class="text-sm text-black dark:text-gray-100">
                                                ${{ $this->formatCurrency($userStats['total_cost_real']) }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right">
                                            <div class="text-sm font-bold text-black dark:text-gray-100">
                                                ${{ $this->formatCurrency($userStats['total_cost_final']) }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right">
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                ${{ $this->formatCurrency($userStats['avg_cost']) }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right">
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ $totalCostFinal > 0 ? number_format(($userStats['total_cost_final'] / $totalCostFinal) * 100, 1) : 0 }}%
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <a href="{{ route('usage-records.detail', ['userId' => $userStats['id'], 'accountId' => $selectedAccountId, 'dateFrom' => $dateFrom, 'dateTo' => $dateTo]) }}" 
                                               class="inline-flex items-center gap-1 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg transition">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                Ver Detalle
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                                <!-- Fila de totales -->
                                <tr class="bg-gray-100 dark:bg-gray-900 font-bold">
                                    <td class="px-6 py-4 text-sm text-black dark:text-gray-100">
                                        TOTAL
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-black dark:text-gray-100">
                                        {{ number_format($usersByAccount->sum('count')) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-black dark:text-gray-100">
                                        ${{ $this->formatCurrency($usersByAccount->sum('total_cost_real')) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-black dark:text-gray-100">
                                        ${{ $this->formatCurrency($usersByAccount->sum('total_cost_final')) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500 dark:text-gray-400">
                                        ${{ $this->formatCurrency($usersByAccount->sum('count') > 0 ? $usersByAccount->sum('total_cost_final') / $usersByAccount->sum('count') : 0) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-black dark:text-gray-100">
                                        {{ $totalCostFinal > 0 ? number_format(($usersByAccount->sum('total_cost_final') / $totalCostFinal) * 100, 1) : 0 }}%
                                    </td>
                                    <td class="px-6 py-4"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            <!-- Todas las Herramientas - Tabla completa -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-black dark:text-gray-100 mb-4">
                        Todas las Herramientas Usadas
                    </h3>
                    @if($allTools->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Herramienta
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Usos
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Costo Real
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Costo Final
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Promedio/Uso
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            % del Total
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($allTools as $tool)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-black dark:text-gray-100">
                                                    {{ $tool['name'] }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                <div class="text-sm text-black dark:text-gray-100">
                                                    {{ number_format($tool['count']) }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                <div class="text-sm text-black dark:text-gray-100">
                                                    ${{ $this->formatCurrency($tool['total_cost_real']) }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                <div class="text-sm font-bold text-black dark:text-gray-100">
                                                    ${{ $this->formatCurrency($tool['total_cost']) }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    ${{ $this->formatCurrency($tool['avg_cost']) }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    {{ $totalCostFinal > 0 ? number_format(($tool['total_cost'] / $totalCostFinal) * 100, 1) : 0 }}%
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                    <!-- Fila de totales -->
                                    <tr class="bg-gray-100 dark:bg-gray-900 font-bold">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-black dark:text-gray-100">
                                            TOTAL
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-black dark:text-gray-100">
                                            {{ number_format($totalRecords) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-black dark:text-gray-100">
                                            ${{ $this->formatCurrency($totalCostReal) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-black dark:text-gray-100">
                                            ${{ $this->formatCurrency($totalCostFinal) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500 dark:text-gray-400">
                                            ${{ $this->formatCurrency($totalRecords > 0 ? $totalCostFinal / $totalRecords : 0) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-black dark:text-gray-100">
                                            100%
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">No hay datos disponibles</p>
                    @endif
                </div>
            </div>

            <!-- Todos los Modelos - Tabla completa -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-black dark:text-gray-100 mb-4">
                        Todos los Modelos de IA Usados
                    </h3>
                    @if($topModels->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Modelo
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Proveedor
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Disponibilidad
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Usos
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Tokens
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Costo Total
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Promedio/Uso
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            % del Total
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($topModels as $model)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-black dark:text-gray-100">
                                                    {{ $model['name'] }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                                    {{ $model['provider'] }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($model['available_until'])
                                                    @if($model['availability_status'] === 'disponible')
                                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium {{ $model['days_until_expiration'] <= 30 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100' : 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' }}">
                                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                            {{ $model['days_until_expiration'] }} {{ $model['days_until_expiration'] == 1 ? 'día' : 'días' }}
                                                        </span>
                                                    @elseif($model['availability_status'] === 'expirado')
                                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">
                                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                            Expirado
                                                        </span>
                                                    @endif
                                                @else
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-600 dark:text-gray-300">
                                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                        Permanente
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                <div class="text-sm text-black dark:text-gray-100">
                                                    {{ number_format($model['count']) }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                                    {{ isset($model['total_tokens']) ? number_format($model['total_tokens']) : '-' }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                <div class="text-sm font-bold text-black dark:text-gray-100">
                                                    ${{ $this->formatCurrency($model['total_cost']) }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    ${{ $this->formatCurrency($model['count'] > 0 ? $model['total_cost'] / $model['count'] : 0) }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    {{ $totalCostFinal > 0 ? number_format(($model['total_cost'] / $totalCostFinal) * 100, 1) : 0 }}%
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">No hay datos disponibles</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>


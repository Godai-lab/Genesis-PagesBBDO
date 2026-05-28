<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <h2 class="text-2xl font-semibold mb-2">Mis Créditos - {{ $monthName }}</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Monitorea tu uso de créditos y límites mensuales.
                </p>
            </div>
        </div>

        @if($userAccounts->isEmpty())
            <!-- Sin cuentas asignadas -->
            <div class="bg-gray-100 border border-gray-400 text-gray-900 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">No tienes cuentas asignadas. Contacta con el administrador.</span>
            </div>
        @else
            <!-- Selector de cuenta (si tiene múltiples) -->
            @if($userAccounts->count() > 1)
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <label for="accountSelector" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Selecciona una cuenta
                        </label>
                        <select id="accountSelector" wire:model.live="selectedAccountId" wire:change="changeAccount($event.target.value)"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-gray-400 focus:ring focus:ring-gray-300 focus:ring-opacity-50">
                            @foreach($userAccounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            @endif

            @if($selectedAccountId)
                <!-- Card Principal: Uso de Créditos -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                            Créditos Usados - {{ $selectedAccountName }}
                        </h3>
                        
                        <!-- Uso en Créditos -->
                        <div class="mb-4">
                            <div class="flex items-baseline justify-between mb-2">
                                <div>
                                    <span class="text-4xl font-bold text-gray-900 dark:text-gray-100">
                                        {{ \App\Supports\CreditHelper::formatCredits($usageCredits) }}
                                    </span>
                                    @if($effectiveLimit !== null)
                                        <span class="text-2xl text-gray-500 dark:text-gray-400">
                                            / {{ \App\Supports\CreditHelper::formatCredits($effectiveLimit) }}
                                        </span>
                                    @endif
                                    <span class="text-lg text-gray-600 dark:text-gray-400 ml-2">créditos</span>
                                </div>
                            </div>
                            
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
                                (equivalente a {{ \App\Supports\CreditHelper::formatUsd($usageUsd) }} USD)
                            </p>
                            
                            <!-- Barra de Progreso -->
                            @if($effectiveLimit !== null)
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-4 mb-2">
                                    <div class="h-4 rounded-full transition-all duration-300
                                        @if($progressColor === 'light-gray') bg-gray-400 dark:bg-gray-500
                                        @elseif($progressColor === 'medium-gray') bg-gray-600 dark:bg-gray-400
                                        @else bg-gray-800 dark:bg-gray-200
                                        @endif"
                                        style="width: {{ min($progressPercentage, 100) }}%">
                                    </div>
                                </div>
                                <p class="text-sm text-gray-700 dark:text-gray-300 font-semibold">
                                    {{ \App\Supports\CreditHelper::formatCredits($remainingCredits) }} créditos restantes
                                </p>
                            @else
                                <p class="text-sm text-gray-500 dark:text-gray-400 font-semibold">
                                    Cuenta con créditos ilimitados
                                </p>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Card de Usos Recientes -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                            Usos Recientes (Últimos 20)
                        </h3>
                        
                        @if($recentUsages->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Fecha/Hora</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Herramienta</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Modelo</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Créditos</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($recentUsages as $usage)
                                            <tr>
                                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                                    {{ $usage->date }}
                                                </td>
                                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                                    {{ $usage->tool }}
                                                </td>
                                                <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">
                                                    {{ $usage->model }}
                                                </td>
                                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                                    {{ \App\Supports\CreditHelper::formatCredits($usage->credits) }}
                                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                                        ({{ \App\Supports\CreditHelper::formatUsd($usage->usd) }})
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-center text-gray-500 dark:text-gray-400 py-4">
                                No hay usos registrados este mes.
                            </p>
                        @endif
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>

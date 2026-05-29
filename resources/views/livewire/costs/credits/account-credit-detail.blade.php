<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Header con Breadcrumb -->
        <div class="mb-6">
            <nav class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                <a href="{{ route('costs.account-credits') }}" class="hover:text-gray-700 dark:hover:text-gray-300">Gestión de Créditos</a>
                <span class="mx-2">/</span>
                <span class="text-gray-900 dark:text-gray-100">{{ $account->name }}</span>
            </nav>
            
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100 mb-2">{{ $account->name }}</h2>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Uso total de la cuenta por mes. El límite mensual aplica a cada usuario por separado.
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <button wire:click="openRechargeModal" class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 text-sm">
                                Recargas
                            </button>
                            <button wire:click="openLimitModal" class="px-4 py-2 bg-gray-800 text-white rounded-md hover:bg-gray-900 dark:bg-gray-700 dark:hover:bg-gray-600 text-sm">
                                Configurar límite
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Flash Messages -->
        @if (session()->has('success'))
            <div class="bg-gray-100 border border-gray-400 text-gray-900 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @endif

        <!-- Selector de Mes/Año -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div class="p-6">
                <div class="flex items-center space-x-4">
                    <div>
                        <label for="month" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Mes</label>
                        <select id="month" wire:model.live="selectedMonth" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-gray-400 focus:ring focus:ring-gray-300 focus:ring-opacity-50">
                            @foreach($months as $month)
                                <option value="{{ $month['value'] }}">{{ ucfirst($month['name']) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="year" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Año</label>
                        <select id="year" wire:model.live="selectedYear" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-gray-400 focus:ring focus:ring-gray-300 focus:ring-opacity-50">
                            @foreach($years as $year)
                                <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estadísticas del Mes -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Límite mensual (cada usuario)</h3>
                    @if($stats->effective_limit)
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {{ \App\Supports\CreditHelper::formatCredits($stats->effective_limit) }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ \App\Supports\CreditHelper::formatUsd($stats->base_limit_usd) }} por usuario / mes
                        </p>
                    @else
                        <p class="text-2xl font-bold text-gray-400">Ilimitado</p>
                    @endif
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Usado (cuenta)</h3>
                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {{ \App\Supports\CreditHelper::formatCredits($stats->usage_credits) }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ \App\Supports\CreditHelper::formatUsd($stats->usage_usd) }}
                        @if($stats->effective_limit)
                            · {{ number_format($stats->percentage_used, 1) }}% de un cupo individual
                        @endif
                    </p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Saldo recarga vigente</h3>
                    @if($stats->remaining_recharge_usd !== null)
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {{ \App\Supports\CreditHelper::formatUsd($stats->remaining_recharge_usd) }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $stats->active_recharges_count }} recarga(s) activa(s) hoy
                        </p>
                    @else
                        <p class="text-2xl font-bold text-gray-400">—</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Sin recarga vigente</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <livewire:costs.credits.modals.set-limit-modal />
    <livewire:costs.credits.modals.recharge-modal />
</div>

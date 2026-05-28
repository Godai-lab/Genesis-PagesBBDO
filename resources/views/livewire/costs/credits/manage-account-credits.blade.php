<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <h2 class="text-2xl font-semibold mb-2">Gestión de Créditos por Cuenta</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Configura límites mensuales de créditos para las cuentas. Usa la búsqueda y filtros para encontrar cuentas específicas.
                </p>
            </div>
        </div>

        <!-- Flash Messages -->
        @if (session()->has('success'))
            <div class="bg-gray-100 border border-gray-400 text-gray-900 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @endif

        <!-- Tabla de Cuentas con Laravel Livewire Tables -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <livewire:costs.credits.account-credits-table />
        </div>
    </div>

    <!-- Modal -->
    <livewire:costs.credits.modals.set-limit-modal />
</div>

<div>
    @if($show)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="close"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4 max-h-[85vh] overflow-y-auto">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 mb-1">
                            Recargas de créditos — {{ $accountName }}
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                            Saldo compartido por cuenta. El uso entre las fechas de vigencia descuenta del monto recargado.
                        </p>

                        <form wire:submit.prevent="save" class="border border-gray-200 dark:border-gray-600 rounded-lg p-4 mb-6">
                            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3">Nueva recarga</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="sm:col-span-2">
                                    <label for="amountUsd" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Monto (USD)</label>
                                    <input type="number" step="0.01" id="amountUsd" wire:model.live="amountUsd"
                                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-gray-400 focus:ring focus:ring-gray-300 focus:ring-opacity-50"
                                           placeholder="Ej: 50.00">
                                    @error('amountUsd') <span class="text-red-600 dark:text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                                    @if($calculatedCredits > 0)
                                        <p class="text-sm text-gray-700 dark:text-gray-300 mt-1">
                                            ≈ {{ \App\Supports\CreditHelper::formatCredits($calculatedCredits) }} créditos
                                        </p>
                                    @endif
                                </div>
                                <div>
                                    <label for="periodStart" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Vigencia desde</label>
                                    <input type="date" id="periodStart" wire:model="periodStart"
                                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm">
                                    @error('periodStart') <span class="text-red-600 dark:text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label for="periodEnd" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Vigencia hasta</label>
                                    <input type="date" id="periodEnd" wire:model="periodEnd"
                                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm">
                                    @error('periodEnd') <span class="text-red-600 dark:text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                                </div>
                                <div class="sm:col-span-2">
                                    <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notas (opcional)</label>
                                    <textarea id="notes" wire:model="notes" rows="2"
                                              class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm"></textarea>
                                    @error('notes') <span class="text-red-600 dark:text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="mt-4 flex justify-end">
                                <button type="submit"
                                        class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-gray-800 text-sm font-medium text-white hover:bg-gray-900 dark:bg-gray-700 dark:hover:bg-gray-600">
                                    Agregar recarga
                                </button>
                            </div>
                        </form>

                        <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">Recargas registradas</h4>
                        @if(count($recharges) === 0)
                            <p class="text-sm text-gray-500 dark:text-gray-400">No hay recargas para esta cuenta.</p>
                        @else
                            <div class="overflow-x-auto border border-gray-200 dark:border-gray-600 rounded-lg">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600 text-sm">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Monto</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Vigencia</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Estado</th>
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                                        @foreach($recharges as $recharge)
                                            <tr class="{{ !$recharge['is_active'] ? 'opacity-60' : '' }}">
                                                <td class="px-3 py-2 text-gray-900 dark:text-gray-100">
                                                    {{ \App\Supports\CreditHelper::formatUsd($recharge['amount_usd']) }}
                                                    <span class="text-xs text-gray-500 block">{{ \App\Supports\CreditHelper::formatCredits($recharge['amount_credits']) }}</span>
                                                </td>
                                                <td class="px-3 py-2 text-gray-700 dark:text-gray-300 text-sm">
                                                    {{ $recharge['period_start'] }} — {{ $recharge['period_end'] }}
                                                </td>
                                                <td class="px-3 py-2">
                                                    @if(!$recharge['is_active'])
                                                        <span class="px-2 py-0.5 rounded text-xs bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-400">Desactivada</span>
                                                    @elseif($recharge['in_period'])
                                                        <span class="px-2 py-0.5 rounded text-xs bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Vigente</span>
                                                    @else
                                                        <span class="px-2 py-0.5 rounded text-xs bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Fuera de periodo</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-2 text-right space-x-2 whitespace-nowrap">
                                                    <button type="button"
                                                            wire:click="toggleRecharge({{ $recharge['id'] }})"
                                                            class="text-xs underline {{ $recharge['is_active'] ? 'text-yellow-600 hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-300' : 'text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300' }}">
                                                        {{ $recharge['is_active'] ? 'Desactivar' : 'Activar' }}
                                                    </button>
                                                    <button type="button"
                                                            wire:click="deleteRecharge({{ $recharge['id'] }})"
                                                            wire:confirm="¿Eliminar esta recarga del historial?"
                                                            class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 text-xs underline">
                                                        Eliminar
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="button" wire:click="close"
                                class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 sm:w-auto sm:text-sm">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

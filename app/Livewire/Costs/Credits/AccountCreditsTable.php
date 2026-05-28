<?php

namespace App\Livewire\Costs\Credits;

use App\Models\Account;
use App\Supports\CreditHelper;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class AccountCreditsTable extends DataTableComponent
{
    protected $model = Account::class;
    
    protected $listeners = ['creditLimitUpdated' => '$refresh'];

    public function configure(): void
    {
        $this->setPrimaryKey('id')
            ->setSearchEnabled()
            ->setSearchDebounce(500)
            ->setSearchPlaceholder('Buscar por cuenta...')
            ->setPaginationEnabled()
            ->setPerPageAccepted([10, 25, 50, 100])
            ->setPerPage(25)
            ->setDefaultSort('name', 'asc')
            ->setLoadingPlaceholderDisabled()
            ->setEmptyMessage('No se encontraron cuentas.')
            ->setColumnSelectDisabled()
            ->setQueryStringEnabled();
    }

    public function columns(): array
    {
        return [
            Column::make('Cuenta', 'name')
                ->searchable()
                ->sortable()
                ->format(function($value, $row) {
                    return '<a href="' . route('costs.credits.detail', $row->id) . '" class="text-gray-900 dark:text-gray-100 hover:text-gray-700 dark:hover:text-gray-300 underline font-medium">' 
                        . e($value) 
                        . '</a>';
                })
                ->html(),

            Column::make('Límite Mensual')
                ->label(function($row) {
                    $effectiveLimit = $row->getEffectiveCreditLimit();
                    
                    if ($effectiveLimit !== null) {
                        $baseLimitUsd = $row->creditLimit ? $row->creditLimit->monthly_base_limit_usd : 0;
                        
                        return '<div class="text-sm text-gray-900 dark:text-gray-100">' 
                            . CreditHelper::formatCredits($effectiveLimit) 
                            . '</div>'
                            . '<div class="text-xs text-gray-500 dark:text-gray-400">(' 
                            . CreditHelper::formatUsd($baseLimitUsd) 
                            . ')</div>';
                    }
                    
                    return '<span class="text-sm text-gray-400">Ilimitado</span>';
                })
                ->html(),

            Column::make('Usado Este Mes')
                ->label(function($row) {
                    $usageUsd = $row->getMonthlyUsageInUsd();
                    $usageCredits = CreditHelper::usdToCredits($usageUsd);
                    
                    return '<div class="text-sm text-gray-900 dark:text-gray-100">' 
                        . CreditHelper::formatCredits($usageCredits) 
                        . '</div>'
                        . '<div class="text-xs text-gray-500 dark:text-gray-400">(' 
                        . CreditHelper::formatUsd($usageUsd) 
                        . ')</div>';
                })
                ->html(),

            Column::make('Restante')
                ->label(function($row) {
                    $remainingCredits = $row->getRemainingCredits();
                    
                    if ($remainingCredits === null) {
                        return '<span class="text-sm text-gray-400">∞</span>';
                    }
                    
                    return '<span class="text-sm text-gray-900 dark:text-gray-100">' 
                        . CreditHelper::formatCredits($remainingCredits) 
                        . '</span>';
                })
                ->html(),

            Column::make('% Usado')
                ->label(function($row) {
                    $effectiveLimit = $row->getEffectiveCreditLimit();
                    
                    if ($effectiveLimit === null) {
                        return '<span class="text-sm text-gray-400">-</span>';
                    }
                    
                    $usageUsd = $row->getMonthlyUsageInUsd();
                    $usageCredits = CreditHelper::usdToCredits($usageUsd);
                    
                    $percentage = $effectiveLimit > 0 ? ($usageCredits / $effectiveLimit) * 100 : 0;
                    $percentage = min($percentage, 100);
                    
                    $colorClass = 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
                    if ($percentage >= 90) {
                        $colorClass = 'bg-gray-800 text-white dark:bg-gray-900 dark:text-gray-100';
                    } elseif ($percentage >= 70) {
                        $colorClass = 'bg-gray-300 text-gray-900 dark:bg-gray-600 dark:text-gray-100';
                    }
                    
                    return '<span class="px-2 py-1 rounded text-xs font-semibold ' . $colorClass . '">' 
                        . number_format($percentage, 1) . '%'
                        . '</span>';
                })
                ->html(),

            Column::make('Acciones')
                ->label(function($row) {
                    return '
                        <div class="flex space-x-2">
                            <button wire:click="$dispatch(\'openSetLimitModal\', { accountId: ' . $row->id . ' })" 
                                class="text-gray-700 hover:text-gray-900 dark:text-gray-300 dark:hover:text-gray-100 underline text-sm">
                                Modificar
                            </button>
                            <a href="' . route('costs.credits.detail', $row->id) . '" 
                                class="text-gray-700 hover:text-gray-900 dark:text-gray-300 dark:hover:text-gray-100 underline text-sm">
                                Detalle
                            </a>
                        </div>
                    ';
                })
                ->html(),
        ];
    }


    public function builder(): Builder
    {
        return Account::query()
            ->with('creditLimit')
            ->select('accounts.*');
    }
}


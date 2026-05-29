<?php

namespace App\Livewire\Costs\Credits;

use App\Models\Account;
use App\Supports\CreditHelper;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;
use Carbon\Carbon;

class AccountCreditDetail extends Component
{
    use WithPagination;
    
    public $account;
    public $selectedMonth;
    public $selectedYear;
    
    protected $listeners = [
        'creditLimitUpdated' => 'loadAccountData',
        'rechargeUpdated' => 'loadAccountData',
    ];
    
    public function mount($accountId)
    {
        Gate::authorize('haveaccess', 'costs.account-credits');

        $this->account = Account::with(['creditLimit', 'creditRecharges'])->findOrFail($accountId);
        $this->selectedMonth = now()->month;
        $this->selectedYear = now()->year;
    }
    
    public function loadAccountData()
    {
        $this->account = Account::with(['creditLimit', 'creditRecharges'])->findOrFail($this->account->id);
        $this->resetPage();
    }
    
    public function openLimitModal()
    {
        $this->dispatch('openSetLimitModal', accountId: $this->account->id);
    }

    public function openRechargeModal()
    {
        $this->dispatch('openRechargeModal', accountId: $this->account->id);
    }
    
    public function updatedSelectedMonth()
    {
        $this->resetPage();
    }
    
    public function updatedSelectedYear()
    {
        $this->resetPage();
    }
    
    public function getAccountStats()
    {
        // Límite base
        $baseLimit = $this->account->creditLimit ? $this->account->creditLimit->monthly_base_limit : null;
        $baseLimitUsd = $this->account->creditLimit ? $this->account->creditLimit->monthly_base_limit_usd : null;
        
        // Límite efectivo (ahora es igual al límite base)
        $effectiveLimit = $this->account->getEffectiveCreditLimit($this->selectedYear, $this->selectedMonth);
        
        // Uso del mes
        $usageUsd = $this->account->getMonthlyUsageInUsd($this->selectedYear, $this->selectedMonth);
        $usageCredits = CreditHelper::usdToCredits($usageUsd);
        
        $remainingRechargeUsd = $this->account->getRemainingRechargeBalance();
        $activeRecharges = $this->account->getActiveRechargesForToday();
        
        // Porcentaje: uso total de cuenta vs cupo mensual por usuario (referencia)
        $percentageUsed = $effectiveLimit ? ($usageCredits / $effectiveLimit) * 100 : 0;
        
        return (object) [
            'base_limit' => $baseLimit,
            'base_limit_usd' => $baseLimitUsd,
            'effective_limit' => $effectiveLimit,
            'usage_usd' => $usageUsd,
            'usage_credits' => $usageCredits,
            'remaining_recharge_usd' => $remainingRechargeUsd,
            'active_recharges_count' => $activeRecharges->count(),
            'percentage_used' => $percentageUsed,
        ];
    }
    
    public function render()
    {
        $stats = $this->getAccountStats();
        
        // Obtener lista de meses para el selector
        $months = collect(range(1, 12))->map(function($month) {
            return [
                'value' => $month,
                'name' => Carbon::create(null, $month, 1)->locale('es')->isoFormat('MMMM')
            ];
        });
        
        // Obtener lista de años (últimos 3 años + actual)
        $currentYear = now()->year;
        $years = collect(range($currentYear - 2, $currentYear + 1))->reverse()->values();
        
        return view('livewire.costs.credits.account-credit-detail', [
            'stats' => $stats,
            'months' => $months,
            'years' => $years,
        ])->layout('layouts.app');
    }
}

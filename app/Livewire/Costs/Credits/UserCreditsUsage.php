<?php

namespace App\Livewire\Costs\Credits;

use App\Models\Account;
use App\Models\UsageRecord;
use App\Supports\CreditHelper;
use Livewire\Component;
use Carbon\Carbon;

class UserCreditsUsage extends Component
{
    public $userAccounts; // Cuentas del usuario
    public $selectedAccountId;
    public $selectedAccountName;
    public $usageCredits; // Uso del mes en créditos
    public $usageUsd; // Uso del mes en USD
    public $effectiveLimit; // Límite efectivo
    public $remainingCredits;
    public $recentUsages; // Últimos 20 usos
    public $progressPercentage;
    public $progressColor;
    public $monthName;
    
    public function mount()
    {
        $user = auth()->user();
        
        // Obtener cuentas del usuario
        $this->userAccounts = $user->accounts;
        
        // Si no tiene cuentas, mostrar mensaje
        if ($this->userAccounts->isEmpty()) {
            $this->selectedAccountId = null;
            return;
        }
        
        // Seleccionar la primera cuenta por defecto
        $this->selectedAccountId = $this->userAccounts->first()->id;
        $this->loadUsageData();
    }
    
    public function changeAccount($accountId)
    {
        $this->selectedAccountId = $accountId;
        $this->loadUsageData();
    }
    
    public function loadUsageData()
    {
        if (!$this->selectedAccountId) {
            return;
        }
        
        $account = Account::with(['creditLimit'])->findOrFail($this->selectedAccountId);
        
        $this->selectedAccountName = $account->name;
        $this->monthName = now()->locale('es')->isoFormat('MMMM YYYY');
        
        // Obtener uso del mes
        $this->usageUsd = $account->getMonthlyUsageInUsd();
        $this->usageCredits = CreditHelper::usdToCredits($this->usageUsd);
        
        // Obtener límite efectivo y créditos restantes
        $this->effectiveLimit = $account->getEffectiveCreditLimit();
        $this->remainingCredits = $account->getRemainingCredits();
        
        // Calcular porcentaje y color
        if ($this->effectiveLimit !== null) {
            $this->progressPercentage = ($this->usageCredits / $this->effectiveLimit) * 100;
            $this->progressColor = $this->getProgressColor();
        } else {
            $this->progressPercentage = 0;
            $this->progressColor = 'gray';
        }
        
        // Obtener usos recientes del usuario en esta cuenta
        $this->recentUsages = UsageRecord::with(['aiModel', 'provider'])
            ->where('account_id', $this->selectedAccountId)
            ->where('user_id', auth()->id())
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($usage) {
                return (object) [
                    'date' => $usage->created_at->format('d/m/Y H:i'),
                    'tool' => $usage->tool_name ?? 'N/A',
                    'model' => $usage->aiModel->name ?? $usage->model_name ?? 'N/A',
                    'credits' => CreditHelper::usdToCredits($usage->cost_final_user_usd),
                    'usd' => $usage->cost_final_user_usd,
                ];
            });
    }
    
    public function getProgressColor()
    {
        if ($this->progressPercentage < 70) {
            return 'light-gray';
        } elseif ($this->progressPercentage < 90) {
            return 'medium-gray';
        } else {
            return 'dark-gray';
        }
    }
    
    public function render()
    {
        return view('livewire.costs.credits.user-credits-usage')->layout('layouts.app');
    }
}

<?php

namespace App\Livewire\Components;

use App\Models\Account;
use App\Models\UsageRecord;
use App\Supports\CreditHelper;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class MonthlyUsageBadge extends Component
{
    public $totalCost = 0; // USD
    public $totalCredits = 0; // Créditos
    public $monthName = '';
    public $isLoading = true;
    public $showInCredits = true; // true para usuarios, false para admin
    public $effectiveLimit = null; // Límite efectivo en créditos
    public $remainingCredits = null;

    public function mount()
    {
        $this->loadMonthlyUsage();
    }

    public function loadMonthlyUsage()
    {
        $this->isLoading = true;
        
        $user = Auth::user();
        if (!$user) {
            $this->totalCost = 0;
            $this->totalCredits = 0;
            $this->isLoading = false;
            return;
        }

        // Determinar si mostrar en créditos o USD
        $this->showInCredits = !$user->haveFullAccess();

        // Obtener el inicio y fin del mes actual
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        
        $this->monthName = Carbon::now()->locale('es')->isoFormat('MMMM YYYY');

        // Calcular el consumo total del mes actual del usuario
        $this->totalCost = (float) UsageRecord::where('user_id', $user->id)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('cost_final_user_usd') ?? 0.0;
        
        // Convertir a créditos
        $this->totalCredits = CreditHelper::usdToCredits($this->totalCost);
        
        // Si es un usuario normal, intentar obtener el límite de su cuenta
        if ($this->showInCredits) {
            // Obtener la primera cuenta del usuario (o la cuenta en sesión si existe)
            $accountId = session()->get('selected_account_id');
            
            if (!$accountId) {
                $firstAccount = $user->accounts->first();
                $accountId = $firstAccount?->id;
            }
            
            if ($accountId) {
                $account = Account::with('creditLimit')->find($accountId);
                if ($account) {
                    $this->effectiveLimit = $account->getEffectiveCreditLimit();
                    $this->remainingCredits = $account->getRemainingCredits();
                }
            }
        }

        $this->isLoading = false;
    }

    public function render()
    {
        return view('livewire.components.monthly-usage-badge');
    }
}


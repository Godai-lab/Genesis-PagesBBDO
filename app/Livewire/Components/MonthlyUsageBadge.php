<?php

namespace App\Livewire\Components;

use App\Models\UsageRecord;
use App\Supports\CreditHelper;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class MonthlyUsageBadge extends Component
{
    public $totalCredits = 0;
    public $isLoading = true;

    public function mount()
    {
        $this->loadMonthlyUsage();
    }

    public function loadMonthlyUsage()
    {
        $this->isLoading = true;

        $user = Auth::user();
        if (!$user) {
            $this->totalCredits = 0;
            $this->isLoading = false;
            return;
        }

        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        $accountIds = $user->accounts->pluck('id');

        $totalCost = (float) UsageRecord::where('user_id', $user->id)
            ->when($accountIds->isNotEmpty(), fn ($query) => $query->whereIn('account_id', $accountIds))
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('cost_final_user_usd');

        $this->totalCredits = CreditHelper::usdToCredits($totalCost);
        $this->isLoading = false;
    }

    public function render()
    {
        return view('livewire.components.monthly-usage-badge');
    }
}

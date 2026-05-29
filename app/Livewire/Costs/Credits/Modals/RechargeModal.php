<?php

namespace App\Livewire\Costs\Credits\Modals;

use App\Models\Account;
use App\Models\CreditRecharge;
use App\Supports\CreditHelper;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class RechargeModal extends Component
{
    public $show = false;

    public $accountId;

    public $accountName;

    public $amountUsd;

    public $periodStart;

    public $periodEnd;

    public $notes;

    public $calculatedCredits = 0;

    public $recharges = [];

    protected $rules = [
        'amountUsd' => 'required|numeric|min:0.01',
        'periodStart' => 'required|date',
        'periodEnd' => 'required|date|after_or_equal:periodStart',
        'notes' => 'nullable|string|max:2000',
    ];

    protected $messages = [
        'amountUsd.required' => 'El monto en USD es obligatorio.',
        'periodStart.required' => 'La fecha de inicio es obligatoria.',
        'periodEnd.required' => 'La fecha de fin es obligatoria.',
        'periodEnd.after_or_equal' => 'La fecha de fin debe ser igual o posterior al inicio.',
    ];

    protected $listeners = ['openRechargeModal'];

    public function openRechargeModal($accountId): void
    {
        Gate::authorize('haveaccess', 'costs.account-credits');

        $account = Account::with(['creditRecharges' => fn ($q) => $q->orderByDesc('created_at')])
            ->findOrFail($accountId);

        $this->accountId = $accountId;
        $this->accountName = $account->name;
        $this->amountUsd = null;
        $this->periodStart = now()->toDateString();
        $this->periodEnd = now()->addMonths(6)->toDateString();
        $this->notes = null;
        $this->calculatedCredits = 0;

        $today = now()->toDateString();
        $this->recharges = $account->creditRecharges->map(fn (CreditRecharge $r) => [
            'id' => $r->id,
            'amount_usd' => (float) $r->amount_usd,
            'amount_credits' => $r->amount_credits,
            'period_start' => $r->period_start->format('d/m/Y'),
            'period_end' => $r->period_end->format('d/m/Y'),
            'notes' => $r->notes,
            'created_at' => $r->created_at?->format('d/m/Y H:i'),
            'is_active' => $r->is_active,
            'in_period' => $r->period_start->toDateString() <= $today
                && $r->period_end->toDateString() >= $today,
        ])->values()->all();

        $this->show = true;
    }

    public function updatedAmountUsd($value): void
    {
        if ($value !== null && $value !== '' && is_numeric($value)) {
            $this->calculatedCredits = CreditHelper::usdToCredits((float) $value);
        } else {
            $this->calculatedCredits = 0;
        }
    }

    public function save(): void
    {
        Gate::authorize('haveaccess', 'costs.account-credits');

        $this->validate();

        CreditRecharge::create([
            'account_id' => $this->accountId,
            'amount_usd' => $this->amountUsd,
            'period_start' => $this->periodStart,
            'period_end' => $this->periodEnd,
            'notes' => $this->notes,
            'added_by_user_id' => auth()->id(),
        ]);

        session()->flash('success', 'Recarga registrada correctamente.');

        $this->dispatch('rechargeUpdated');
        $this->reloadRecharges();
        $this->resetFormFields();
    }

    public function toggleRecharge(int $rechargeId): void
    {
        Gate::authorize('haveaccess', 'costs.account-credits');

        $recharge = CreditRecharge::where('account_id', $this->accountId)->findOrFail($rechargeId);
        $recharge->update(['is_active' => !$recharge->is_active]);

        session()->flash('success', $recharge->is_active ? 'Recarga desactivada.' : 'Recarga activada.');

        $this->dispatch('rechargeUpdated');
        $this->reloadRecharges();
    }

    public function deleteRecharge(int $rechargeId): void
    {
        Gate::authorize('haveaccess', 'costs.account-credits');

        $recharge = CreditRecharge::where('account_id', $this->accountId)->findOrFail($rechargeId);
        $recharge->delete();

        session()->flash('success', 'Recarga eliminada.');

        $this->dispatch('rechargeUpdated');
        $this->reloadRecharges();
    }

    protected function reloadRecharges(): void
    {
        $account = Account::with(['creditRecharges' => fn ($q) => $q->orderByDesc('created_at')])
            ->findOrFail($this->accountId);

        $today = now()->toDateString();
        $this->recharges = $account->creditRecharges->map(fn (CreditRecharge $r) => [
            'id' => $r->id,
            'amount_usd' => (float) $r->amount_usd,
            'amount_credits' => $r->amount_credits,
            'period_start' => $r->period_start->format('d/m/Y'),
            'period_end' => $r->period_end->format('d/m/Y'),
            'notes' => $r->notes,
            'created_at' => $r->created_at?->format('d/m/Y H:i'),
            'is_active' => $r->is_active,
            'in_period' => $r->period_start->toDateString() <= $today
                && $r->period_end->toDateString() >= $today,
        ])->values()->all();
    }

    protected function resetFormFields(): void
    {
        $this->amountUsd = null;
        $this->periodStart = now()->toDateString();
        $this->periodEnd = now()->addMonths(6)->toDateString();
        $this->notes = null;
        $this->calculatedCredits = 0;
        $this->resetErrorBag();
    }

    public function close(): void
    {
        $this->show = false;
        $this->reset([
            'accountId',
            'accountName',
            'amountUsd',
            'periodStart',
            'periodEnd',
            'notes',
            'calculatedCredits',
            'recharges',
        ]);
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.costs.credits.modals.recharge-modal');
    }
}

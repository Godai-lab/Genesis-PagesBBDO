<?php

namespace App\Livewire\Costs\Credits\Modals;

use App\Models\Account;
use App\Models\AccountCreditLimit;
use App\Supports\CreditHelper;
use Livewire\Component;

class SetLimitModal extends Component
{
    public $show = false;
    public $accountId;
    public $accountName;
    public $unlimitedCredit = false;
    public $baseLimitUsd;
    public $calculatedCredits = 0;
    
    protected $rules = [
        'baseLimitUsd' => 'required_if:unlimitedCredit,false|nullable|numeric|min:0',
    ];
    
    protected $messages = [
        'baseLimitUsd.required_if' => 'El límite base es requerido.',
        'baseLimitUsd.numeric' => 'El límite debe ser un número válido.',
        'baseLimitUsd.min' => 'El límite debe ser mayor o igual a 0.',
    ];
    
    protected $listeners = ['openSetLimitModal'];
    
    public function openSetLimitModal($accountId)
    {
        $account = Account::with('creditLimit')->findOrFail($accountId);
        
        $this->accountId = $accountId;
        $this->accountName = $account->name;
        
        if ($account->creditLimit) {
            $this->unlimitedCredit = false;
            $this->baseLimitUsd = $account->creditLimit->monthly_base_limit_usd;
            $this->calculatedCredits = $account->creditLimit->monthly_base_limit;
        } else {
            $this->unlimitedCredit = true;
            $this->baseLimitUsd = null;
            $this->calculatedCredits = 0;
        }
        
        $this->show = true;
    }
    
    public function updatedBaseLimitUsd($value)
    {
        if ($value && is_numeric($value)) {
            $this->calculatedCredits = CreditHelper::usdToCredits((float)$value);
        } else {
            $this->calculatedCredits = 0;
        }
    }
    
    public function save()
    {
        if (!$this->unlimitedCredit) {
            $this->validate([
                'baseLimitUsd' => 'required|numeric|min:0',
            ]);
        }
        
        if ($this->unlimitedCredit) {
            AccountCreditLimit::where('account_id', $this->accountId)->delete();
            session()->flash('success', 'Cuenta configurada como ilimitada.');
        } else {
            AccountCreditLimit::updateOrCreate(
                ['account_id' => $this->accountId],
                ['monthly_base_limit_usd' => $this->baseLimitUsd]
            );
            session()->flash('success', 'Límite base actualizado correctamente.');
        }
        
        $this->dispatch('creditLimitUpdated');
        $this->close();
    }
    
    public function close()
    {
        $this->show = false;
        $this->reset(['accountId', 'accountName', 'unlimitedCredit', 'baseLimitUsd', 'calculatedCredits']);
        $this->resetErrorBag();
    }
    
    public function render()
    {
        return view('livewire.costs.credits.modals.set-limit-modal');
    }
}

<?php

namespace App\Livewire\Costs\Credits;

use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class ManageAccountCredits extends Component
{
    public function mount()
    {
        Gate::authorize('haveaccess', 'costs.account-credits');
    }
    
    public function render()
    {
        return view('livewire.costs.credits.manage-account-credits')
        ->layout('layouts.app');
    }
}

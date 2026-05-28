<?php

namespace App\Models;

use App\Supports\CreditHelper;
use Illuminate\Database\Eloquent\Model;

class AccountCreditLimit extends Model
{
    protected $fillable = [
        'account_id',
        'monthly_base_limit_usd',
        'monthly_base_limit',
    ];
    
    protected $casts = [
        'monthly_base_limit_usd' => 'decimal:2',
        'monthly_base_limit' => 'integer',
    ];
    
    protected static function boot()
    {
        parent::boot();
        
        // Al crear, calcular créditos automáticamente desde USD
        static::creating(function ($limit) {
            if ($limit->monthly_base_limit_usd && !$limit->monthly_base_limit) {
                $limit->monthly_base_limit = CreditHelper::usdToCredits($limit->monthly_base_limit_usd);
            }
        });
        
        // Al actualizar, recalcular créditos si cambió el USD
        static::updating(function ($limit) {
            if ($limit->isDirty('monthly_base_limit_usd')) {
                $limit->monthly_base_limit = CreditHelper::usdToCredits($limit->monthly_base_limit_usd);
            }
        });
    }
    
    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}


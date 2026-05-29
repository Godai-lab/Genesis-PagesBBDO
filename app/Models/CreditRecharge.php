<?php

namespace App\Models;

use App\Supports\CreditHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditRecharge extends Model
{
    protected $table = 'credit_recharges';

    protected $fillable = [
        'account_id',
        'amount_usd',
        'amount_credits',
        'period_start',
        'period_end',
        'added_by_user_id',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'amount_usd' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (CreditRecharge $recharge) {
            if ($recharge->isDirty('amount_usd') && $recharge->amount_usd !== null) {
                $recharge->amount_credits = CreditHelper::usdToCredits((float) $recharge->amount_usd);
            }
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function addedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }
}

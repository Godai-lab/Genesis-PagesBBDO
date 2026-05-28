<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Supports\CreditHelper;
use Carbon\Carbon;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'category',
        'status',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function configs(): HasMany
    {
        return $this->hasMany(Config::class);
    }

    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class);
    }

    public function scopeSearch($query,$search){
        if($search){
            return $query->where('name','like','%'.$search.'%');
        }
    }
    
    public function scopeDate($query, $from, $to){
        if (strtotime($from)&&strtotime($to)) {
            return $query->whereBetween('created_at',[$from.' 00:00:00',$to.' 23:59:59']);
        }
        
    }

    public function scopeFullaccess($query){
        if(!auth()->user()->haveFullAccess())
            return $query->whereIn('id',auth()->user()->accounts->pluck('id'));
    }

    public function creditLimit(): HasOne
    {
        return $this->hasOne(AccountCreditLimit::class);
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class);
    }

    public function getMonthlyUsageInUsd(int $year = null, int $month = null): float
    {
        $year = $year ?? now()->year;
        $month = $month ?? now()->month;

        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth = Carbon::create($year, $month, 1)->endOfMonth();

        return (float) $this->usageRecords()
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('cost_final_user_usd');
    }

    public function getEffectiveCreditLimit(int $year = null, int $month = null): ?int
    {
        return $this->creditLimit ? $this->creditLimit->monthly_base_limit : null;
    }

    public function hasExceededLimit(): bool
    {
        $effectiveLimit = $this->getEffectiveCreditLimit();
        if ($effectiveLimit === null) {
            return false;
        }

        $usageCredits = CreditHelper::usdToCredits($this->getMonthlyUsageInUsd());

        return $usageCredits >= $effectiveLimit;
    }

    public function getRemainingCredits(): ?int
    {
        $effectiveLimit = $this->getEffectiveCreditLimit();
        if ($effectiveLimit === null) {
            return null;
        }

        $usageCredits = CreditHelper::usdToCredits($this->getMonthlyUsageInUsd());
        $remaining = $effectiveLimit - $usageCredits;

        return max(0, $remaining);
    }
}

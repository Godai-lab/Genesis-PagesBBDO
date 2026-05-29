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

    public function creditRecharges(): HasMany
    {
        return $this->hasMany(CreditRecharge::class);
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class);
    }

    public function getUsageInPeriod(\DateTimeInterface|Carbon $start, \DateTimeInterface|Carbon $end): float
    {
        $start = $start instanceof Carbon ? $start : Carbon::parse($start);
        $end = $end instanceof Carbon ? $end : Carbon::parse($end);

        return (float) $this->usageRecords()
            ->whereBetween('created_at', [$start, $end])
            ->sum('cost_final_user_usd');
    }

    public function getActiveRechargesForToday(): \Illuminate\Database\Eloquent\Collection
    {
        $today = now()->toDateString();

        return $this->creditRecharges()
            ->where('is_active', true)
            ->where('period_start', '<=', $today)
            ->where('period_end', '>=', $today)
            ->orderBy('period_start')
            ->get();
    }

    public function getRemainingRechargeBalance(): ?float
    {
        $recharges = $this->getActiveRechargesForToday();
        if ($recharges->isEmpty()) {
            return null;
        }

        $totalUsd = (float) $recharges->sum('amount_usd');
        $minStart = $recharges->min('period_start');
        $maxEnd = $recharges->max('period_end');
        $used = $this->getUsageInPeriod($minStart, $maxEnd);

        return max(0, $totalUsd - $used);
    }

    public function hasOnlyExpiredRecharges(): bool
    {
        $today = now()->toDateString();
        $hasActive = $this->creditRecharges()
            ->where('period_start', '<=', $today)
            ->where('period_end', '>=', $today)
            ->exists();
        if ($hasActive) {
            return false;
        }

        return $this->creditRecharges()->where('period_end', '<', $today)->exists();
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

    public function getUserMonthlyUsageInUsd(int $userId, int $year = null, int $month = null): float
    {
        $year = $year ?? now()->year;
        $month = $month ?? now()->month;

        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth = Carbon::create($year, $month, 1)->endOfMonth();

        return (float) $this->usageRecords()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('cost_final_user_usd');
    }

    public function getEffectiveCreditLimit(int $year = null, int $month = null): ?int
    {
        return $this->creditLimit ? $this->creditLimit->monthly_base_limit : null;
    }

    public function hasExceededLimit(?int $userId = null): bool
    {
        $userId = $userId ?? auth()->id();
        if (!$userId) {
            return false;
        }

        $effectiveLimit = $this->getEffectiveCreditLimit();
        if ($effectiveLimit === null) {
            return false;
        }

        $usageCredits = CreditHelper::usdToCredits($this->getUserMonthlyUsageInUsd($userId));

        return $usageCredits >= $effectiveLimit;
    }

    public function getRemainingCredits(?int $userId = null): ?int
    {
        $userId = $userId ?? auth()->id();
        if (!$userId) {
            return null;
        }

        $effectiveLimit = $this->getEffectiveCreditLimit();
        if ($effectiveLimit === null) {
            return null;
        }

        $usageCredits = CreditHelper::usdToCredits($this->getUserMonthlyUsageInUsd($userId));
        $remaining = $effectiveLimit - $usageCredits;

        return max(0, $remaining);
    }
}

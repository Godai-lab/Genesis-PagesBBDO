<?php

namespace App\Supports;

use App\Models\Account;

class UsageLimitValidator
{
    /**
     * Verifica si una cuenta puede usar más recursos
     *
     * @param int|null $accountId
     * @return array ['allowed' => bool, 'message' => string, 'remaining_credits' => int|null, 'limit_credits' => int|null]
     */
    public static function checkAccountLimit(?int $accountId): array
    {
        if (!$accountId) {
            return [
                'allowed' => true,
                'message' => '',
                'remaining_credits' => null,
                'limit_credits' => null,
            ];
        }
        
        $account = Account::with('creditLimit')->find($accountId);
        
        if (!$account) {
            return [
                'allowed' => false,
                'message' => 'Cuenta no encontrada.',
                'remaining_credits' => 0,
                'limit_credits' => 0,
            ];
        }
        
        $effectiveLimit = $account->getEffectiveCreditLimit();
        if ($effectiveLimit === null) {
            return [
                'allowed' => true,
                'message' => '',
                'remaining_credits' => null,
                'limit_credits' => null,
            ];
        }
        
        $usageUsd = $account->getMonthlyUsageInUsd();
        $usageCredits = CreditHelper::usdToCredits($usageUsd);
        
        if ($usageCredits >= $effectiveLimit) {
            return [
                'allowed' => false,
                'message' => "Has alcanzado el límite mensual de " . CreditHelper::formatCredits($effectiveLimit) . " créditos para esta cuenta. Contacta con el administrador para agregar más créditos.",
                'remaining_credits' => 0,
                'limit_credits' => $effectiveLimit,
            ];
        }
        
        $remainingCredits = $effectiveLimit - $usageCredits;
        
        return [
            'allowed' => true,
            'message' => '',
            'remaining_credits' => $remainingCredits,
            'limit_credits' => $effectiveLimit,
        ];
    }
    
    /**
     * Obtiene el uso mensual de una cuenta en créditos
     */
    public static function getAccountMonthlyUsageCredits(int $accountId): int
    {
        $account = Account::find($accountId);
        if (!$account) {
            return 0;
        }
        
        $usageUsd = $account->getMonthlyUsageInUsd();
        return CreditHelper::usdToCredits($usageUsd);
    }
}

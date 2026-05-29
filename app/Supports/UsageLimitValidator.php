<?php

namespace App\Supports;

use App\Models\Account;

class UsageLimitValidator
{
    /**
     * Verifica si una cuenta puede usar más recursos
     *
     * - Super Admin / full access: siempre permitido
     * - Saldo (CreditRecharge): compartido por toda la cuenta
     * - Límite mensual (CreditLimit): por usuario individual
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

        $currentUser = auth()->user();
        if ($currentUser && $currentUser->haveFullAccess()) {
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

        // Verificar si tiene recargas activas (is_active=true) pero ya vencidas.
        // Esto indica que el "contrato" expiró y debe renovarse, aunque haya límite mensual.
        $tieneRecargaVencida = $account->creditRecharges()
            ->where('is_active', true)
            ->where('period_end', '<', now()->toDateString())
            ->exists();

        if ($tieneRecargaVencida) {
            return [
                'allowed' => false,
                'message' => 'El periodo de crédito de esta cuenta ha vencido. Contacta con el administrador para agregar una nueva recarga.',
                'remaining_credits' => 0,
                'limit_credits' => null,
            ];
        }

        $saldoCuenta = $account->getRemainingRechargeBalance();
        $haySaldo = $saldoCuenta !== null;
        $saldoPositivo = $haySaldo && $saldoCuenta > 0;

        $limiteMensual = $account->getEffectiveCreditLimit();
        $hayLimite = $limiteMensual !== null;

        $userId = $currentUser ? $currentUser->id : null;
        $usageUsd = $userId ? $account->getUserMonthlyUsageInUsd($userId) : 0;
        $usageCredits = CreditHelper::usdToCredits($usageUsd);

        if (!$haySaldo && !$hayLimite) {
            return [
                'allowed' => false,
                'message' => 'Esta cuenta no tiene saldo ni límite mensual configurado. Contacta con el administrador.',
                'remaining_credits' => 0,
                'limit_credits' => null,
            ];
        }

        if ($haySaldo && !$hayLimite) {
            if (!$saldoPositivo) {
                return [
                    'allowed' => false,
                    'message' => 'No tienes saldo disponible. Contacta con el administrador para agregar una recarga.',
                    'remaining_credits' => 0,
                    'limit_credits' => null,
                ];
            }

            return [
                'allowed' => true,
                'message' => '',
                'remaining_credits' => null,
                'limit_credits' => null,
            ];
        }

        if (!$haySaldo && $hayLimite) {
            if ($usageCredits >= $limiteMensual) {
                return [
                    'allowed' => false,
                    'message' => 'Has alcanzado tu límite mensual de ' . CreditHelper::formatCredits($limiteMensual) . ' créditos.',
                    'remaining_credits' => 0,
                    'limit_credits' => $limiteMensual,
                ];
            }

            $remainingCredits = $limiteMensual - $usageCredits;

            return [
                'allowed' => true,
                'message' => '',
                'remaining_credits' => $remainingCredits,
                'limit_credits' => $limiteMensual,
            ];
        }

        if ($haySaldo && $hayLimite) {
            if (!$saldoPositivo) {
                return [
                    'allowed' => false,
                    'message' => 'La cuenta no tiene saldo disponible, aunque tengas límite mensual. Contacta con el administrador.',
                    'remaining_credits' => 0,
                    'limit_credits' => $limiteMensual,
                ];
            }

            if ($usageCredits >= $limiteMensual) {
                return [
                    'allowed' => false,
                    'message' => 'Has alcanzado tu límite mensual de ' . CreditHelper::formatCredits($limiteMensual) . ' créditos.',
                    'remaining_credits' => 0,
                    'limit_credits' => $limiteMensual,
                ];
            }

            $remainingCredits = $limiteMensual - $usageCredits;
            $saldoCuentaCredits = CreditHelper::usdToCredits($saldoCuenta);
            $canUseCredits = min($saldoCuentaCredits, $remainingCredits);

            return [
                'allowed' => true,
                'message' => '',
                'remaining_credits' => $canUseCredits,
                'limit_credits' => $limiteMensual,
            ];
        }

        return [
            'allowed' => false,
            'message' => 'Error al validar límites.',
            'remaining_credits' => 0,
            'limit_credits' => null,
        ];
    }

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

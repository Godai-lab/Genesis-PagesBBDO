<?php

namespace App\Http\Traits;

use App\Exceptions\CreditLimitExceededException;
use App\Supports\UsageLimitValidator;
use Illuminate\Support\Facades\Log;

/**
 * Trait para validar límites de créditos en herramientas
 * 
 * Uso en controladores:
 *   use ValidatesCreditLimit;
 *   $this->validateCreditLimit($accountId);
 * 
 * Uso en componentes Livewire:
 *   use ValidatesCreditLimit;
 *   $this->validateCreditLimit($this->accountId);
 */
trait ValidatesCreditLimit
{
    /**
     * Valida que una cuenta tenga créditos disponibles
     * 
     * @param int|null $accountId ID de la cuenta a validar
     * @throws CreditLimitExceededException Si la cuenta excede su límite
     * @return void
     */
    protected function validateCreditLimit(?int $accountId): void
    {
        // Validar límite usando el servicio existente
        $limitCheck = UsageLimitValidator::checkAccountLimit($accountId);
        
        // Si no está permitido, loggear y lanzar excepción
        if (!$limitCheck['allowed']) {
            $this->logCreditLimitExceeded($accountId, $limitCheck);
            
            throw new CreditLimitExceededException(
                $limitCheck['message'],
                [
                    'remaining_credits' => $limitCheck['remaining_credits'],
                    'limit_credits' => $limitCheck['limit_credits'],
                    'account_id' => $accountId,
                ]
            );
        }
    }

    /**
     * Registra en el log cuando se bloquea una generación por límite
     * 
     * @param int|null $accountId
     * @param array $limitCheck
     * @return void
     */
    private function logCreditLimitExceeded(?int $accountId, array $limitCheck): void
    {
        Log::warning('⚠️ Generación bloqueada por límite de créditos', [
            'account_id' => $accountId,
            'user_id' => auth()->id(),
            'tool' => $this->getToolName(),
            'message' => $limitCheck['message'],
            'remaining_credits' => $limitCheck['remaining_credits'],
            'limit_credits' => $limitCheck['limit_credits'],
        ]);
    }

    /**
     * Obtiene el nombre de la herramienta actual
     * 
     * @return string
     */
    private function getToolName(): string
    {
        // Para controladores
        if (property_exists($this, 'toolName')) {
            return $this->toolName;
        }
        
        // Para Livewire components
        if (method_exists($this, 'getName')) {
            return $this->getName();
        }
        
        // Fallback: usar el nombre de la clase
        $className = class_basename($this);
        return str_replace(['Controller', 'Component'], '', $className);
    }
}


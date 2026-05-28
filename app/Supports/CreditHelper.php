<?php

namespace App\Supports;

class CreditHelper
{
    /**
     * Convierte USD a créditos
     * 1 USD = 100 créditos
     * 10 USD = 1000 créditos
     */
    public static function usdToCredits(float $usd): int
    {
        return (int) round($usd * 100);
    }
    
    /**
     * Convierte créditos a USD
     * 100 créditos = 1 USD
     * 1000 créditos = 10 USD
     */
    public static function creditsToUsd(int $credits): float
    {
        return $credits / 100;
    }
    
    /**
     * Formatea créditos para mostrar
     * Ejemplo: 125000 -> "125.000"
     */
    public static function formatCredits(int $credits): string
    {
        return number_format($credits, 0, ',', '.');
    }
    
    /**
     * Formatea USD para mostrar
     * Ejemplo: 1250.50 -> "$1,250.50"
     */
    public static function formatUsd(float $usd): string
    {
        return '$' . number_format($usd, 2, '.', ',');
    }
}

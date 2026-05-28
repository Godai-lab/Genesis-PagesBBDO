<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'user_id',
        'request_type',
        'external_request_id',
        'generated_id',
        'model_pricing_id',
        'usage_metrics',
        'pricing_snapshot',
        'processes_detail',
        'cost_input_usd',
        'cost_output_usd',
        'cost_total_usd',
        'markup_percentage_applied',
        'cost_final_user_usd',
    ];

    protected $casts = [
        'usage_metrics' => 'array',
        'pricing_snapshot' => 'array',
        'processes_detail' => 'array',
        'cost_input_usd' => 'decimal:8',
        'cost_output_usd' => 'decimal:8',
        'cost_total_usd' => 'decimal:8',
        'markup_percentage_applied' => 'decimal:2',
        'cost_final_user_usd' => 'decimal:8',
    ];

    /**
     * Obtener el usuario que hizo la petición
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Obtener la cuenta asociada
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Obtener el precio usado en este registro
     */
    public function modelPricing(): BelongsTo
    {
        return $this->belongsTo(ModelPricing::class, 'model_pricing_id');
    }

    /**
     * Obtener el modelo usado (a través de modelPricing)
     */
    public function model(): ?AiModel
    {
        return $this->modelPricing?->model;
    }

    /**
     * Obtener la generación asociada (Generated)
     */
    public function generated(): BelongsTo
    {
        return $this->belongsTo(Generated::class, 'generated_id');
    }

    /**
     * Scope para filtrar por usuario
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope para filtrar por cuenta
     */
    public function scopeByAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Scope para filtrar por rango de fechas
     */
    public function scopeByDateRange($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Scope para filtrar por modelo
     */
    public function scopeByModel($query, int $modelId)
    {
        return $query->whereHas('modelPricing', function ($q) use ($modelId) {
            $q->where('ai_model_id', $modelId);
        });
    }

    /**
     * Scope para filtrar por tipo de request
     */
    public function scopeByRequestType($query, string $requestType)
    {
        return $query->where('request_type', $requestType);
    }

    /**
     * Scope para filtrar por generated_id
     */
    public function scopeByGenerated($query, int $generatedId)
    {
        return $query->where('generated_id', $generatedId);
    }

    /**
     * Obtener el costo total
     */
    public function getTotalCost(): float
    {
        return (float) $this->cost_total_usd;
    }
}

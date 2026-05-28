<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class ModelPricing extends Model
{
    use HasFactory;

    protected $table = 'model_pricing';

    protected $fillable = [
        'ai_model_id',
        'pricing_type',
        'unit_definition',
        'effective_from',
        'effective_to',
        'markup_percentage',
        'status',
    ];

    protected $casts = [
        'unit_definition' => 'array',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'markup_percentage' => 'decimal:2',
    ];

    /**
     * Obtener el modelo asociado a este precio
     */
    public function model(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'ai_model_id');
    }

    /**
     * Obtener todos los registros de uso con este precio
     */
    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class, 'model_pricing_id');
    }

    /**
     * Scope para filtrar precios activos
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope para obtener precios vigentes (current)
     */
    public function scopeCurrent($query)
    {
        return $query->where('status', 'active')
            ->whereNull('effective_to');
    }

    /**
     * Scope para obtener precio vigente en una fecha específica
     */
    public function scopeForDate($query, $date)
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);
        $dateString = $date->format('Y-m-d');

        return $query->where('status', 'active')
            ->where('effective_from', '<=', $dateString)
            ->where(function ($q) use ($dateString) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $dateString);
            });
    }

    /**
     * Verificar si este precio es el vigente actual
     */
    public function isCurrent(): bool
    {
        return $this->status === 'active' && is_null($this->effective_to);
    }

    /**
     * Verificar si el precio está activo
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}

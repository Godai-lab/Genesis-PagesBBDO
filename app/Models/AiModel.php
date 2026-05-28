<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Carbon\Carbon;

class AiModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'name',
        'slug',
        'model_type',
        'status',
        'available_until',
    ];

    protected $casts = [
        'available_until' => 'date',
    ];

    /**
     * Obtener el proveedor de este modelo
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    /**
     * Obtener todos los precios de este modelo
     */
    public function pricings(): HasMany
    {
        return $this->hasMany(ModelPricing::class, 'ai_model_id');
    }

    /**
     * Obtener el precio vigente actual
     */
    public function currentPricing(): HasOne
    {
        return $this->hasOne(ModelPricing::class, 'ai_model_id')
            ->where('status', 'active')
            ->whereNull('effective_to');
    }

    /**
     * Obtener todos los registros de uso de este modelo
     */
    public function usageRecords(): HasMany
    {
        return $this->hasManyThrough(
            UsageRecord::class,
            ModelPricing::class,
            'ai_model_id', // Foreign key en model_pricing
            'model_pricing_id', // Foreign key en usage_records
            'id', // Local key en ai_models
            'id' // Local key en model_pricing
        );
    }

    /**
     * Obtener el precio vigente para una fecha específica
     */
    public function getCurrentPricing($date = null): ?ModelPricing
    {
        $date = $date ? Carbon::parse($date) : Carbon::now();

        return $this->pricings()
            ->where('status', 'active')
            ->where('effective_from', '<=', $date->format('Y-m-d'))
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date->format('Y-m-d'));
            })
            ->orderBy('effective_from', 'desc')
            ->first();
    }

    /**
     * Scope para filtrar modelos activos
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope para filtrar por tipo de modelo
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('model_type', $type);
    }

    /**
     * Verificar si el modelo está activo
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Scope para modelos disponibles (activos y no expirados)
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('available_until')
                    ->orWhere('available_until', '>=', Carbon::now()->format('Y-m-d'));
            });
    }

    /**
     * Scope para modelos permanentes (sin fecha de expiración)
     */
    public function scopePermanent($query)
    {
        return $query->whereNull('available_until');
    }

    /**
     * Scope para modelos que expirarán pronto
     */
    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->whereNotNull('available_until')
            ->where('available_until', '<=', Carbon::now()->addDays($days)->format('Y-m-d'))
            ->where('available_until', '>=', Carbon::now()->format('Y-m-d'));
    }

    /**
     * Scope para modelos expirados
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('available_until')
            ->where('available_until', '<', Carbon::now()->format('Y-m-d'));
    }

    /**
     * Accessor: Verificar si el modelo está disponible
     */
    public function getIsAvailableAttribute(): bool
    {
        if (is_null($this->available_until)) {
            return true; // Permanente
        }
        return $this->available_until->isFuture() || $this->available_until->isToday();
    }

    /**
     * Accessor: Días hasta la expiración
     */
    public function getDaysUntilExpirationAttribute(): ?int
    {
        if (is_null($this->available_until)) {
            return null; // Permanente
        }
        return max(0, Carbon::now()->diffInDays($this->available_until, false));
    }

    /**
     * Accessor: Estado de disponibilidad simple
     * Retorna: 'disponible', 'expirado' o 'permanente'
     */
    public function getAvailabilityStatusAttribute(): string
    {
        if (is_null($this->available_until)) {
            return 'permanente';
        }

        $days = $this->days_until_expiration;

        if ($days < 0) {
            return 'expirado';
        }

        return 'disponible';
    }

    /**
     * Accessor: Mensaje descriptivo de disponibilidad para UI
     */
    public function getAvailabilityMessageAttribute(): string
    {
        if (is_null($this->available_until)) {
            return 'Permanente';
        }

        $days = $this->days_until_expiration;

        if ($days < 0) {
            return 'Expirado';
        } elseif ($days === 0) {
            return 'Expira hoy';
        } elseif ($days <= 7) {
            return "Expira en {$days} días ⚠️";
        } elseif ($days <= 30) {
            return "Expira en {$days} días";
        }

        return "Disponible hasta " . $this->available_until->format('d/m/Y');
    }
}

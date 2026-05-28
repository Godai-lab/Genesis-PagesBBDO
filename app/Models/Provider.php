<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provider extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
    ];

    /**
     * Obtener todos los modelos de este proveedor
     */
    public function models(): HasMany
    {
        return $this->hasMany(AiModel::class, 'provider_id');
    }

    /**
     * Scope para filtrar proveedores activos
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Verificar si el proveedor está activo
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}

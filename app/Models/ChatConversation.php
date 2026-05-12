<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatConversation extends Model
{
    protected $fillable = [
        'user_id',
        'account_id',
        'agent_type',
        'model_name',
        'title',
        'context_metadata',
        'status',
        'last_message_at',
    ];
    
    protected $casts = [
        'context_metadata' => 'array',
        'last_message_at' => 'datetime',
    ];
    
    /**
     * Relación con el usuario propietario de la conversación
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Relación con la cuenta (opcional)
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
    
    /**
     * Relación con todos los mensajes de la conversación
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id')
                    ->orderBy('created_at', 'asc');
    }
    
    /**
     * Relación con mensajes activos (no eliminados)
     */
    public function activeMessages(): HasMany
    {
        return $this->messages()->where('is_deleted', false);
    }
    
    /**
     * Obtener el último mensaje de la conversación
     */
    public function getLastMessageAttribute()
    {
        return $this->activeMessages()->latest()->first();
    }
    
    /**
     * Obtener el total de tokens usados en la conversación
     */
    public function getTotalTokensAttribute(): int
    {
        return $this->activeMessages()->sum('total_tokens') ?? 0;
    }
    
    /**
     * Obtener el total de mensajes activos
     */
    public function getMessageCountAttribute(): int
    {
        return $this->activeMessages()->count();
    }
    
    /**
     * Obtener el costo estimado en USD (basado en tokens)
     * Puedes personalizar los costos por modelo
     */
    public function getEstimatedCostAttribute(): float
    {
        $totalTokens = $this->total_tokens;
        
        // Costos aproximados por 1M tokens (ajustar según modelo)
        $costPer1MTokens = match($this->agent_type) {
            'openai' => 5.00, // GPT-4
            'claude' => 15.00, // Claude
            'gemini' => 3.50, // Gemini
            default => 5.00,
        };
        
        return ($totalTokens / 1_000_000) * $costPer1MTokens;
    }
    
    /**
     * Scope: Conversaciones activas
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
    
    /**
     * Scope: Conversaciones de un usuario específico
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
    
    /**
     * Scope: Conversaciones de un agente específico
     */
    public function scopeForAgent($query, string $agentType)
    {
        return $query->where('agent_type', $agentType);
    }
    
    /**
     * Genera un título automático basado en el primer mensaje
     */
    public function generateTitle(): void
    {
        $firstUserMessage = $this->messages()
            ->where('role', 'user')
            ->where('is_deleted', false)
            ->first();
        
        if ($firstUserMessage) {
            $title = substr($firstUserMessage->content, 0, 50);
            if (strlen($firstUserMessage->content) > 50) {
                $title .= '...';
            }
            
            $this->update(['title' => $title]);
        }
    }
}


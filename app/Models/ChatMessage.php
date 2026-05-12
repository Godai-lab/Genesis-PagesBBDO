<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'content_type',
        'model_used',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'metadata',
        'attachments',
        'is_visible',
        'is_deleted',
    ];
    
    protected $casts = [
        'metadata' => 'array',
        'attachments' => 'array',
        'is_visible' => 'boolean',
        'is_deleted' => 'boolean',
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
    ];
    
    /**
     * Relación con la conversación
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }
    
    /**
     * Scope: Mensajes activos (no eliminados)
     */
    public function scopeActive($query)
    {
        return $query->where('is_deleted', false);
    }
    
    /**
     * Scope: Mensajes visibles (para mostrar al usuario)
     */
    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }
    
    /**
     * Scope: Mensajes activos y visibles (lo más común)
     */
    public function scopeActiveAndVisible($query)
    {
        return $query->where('is_deleted', false)
                    ->where('is_visible', true);
    }
    
    /**
     * Scope: Mensajes de usuario
     */
    public function scopeUserMessages($query)
    {
        return $query->where('role', 'user');
    }
    
    /**
     * Scope: Mensajes del asistente
     */
    public function scopeAssistantMessages($query)
    {
        return $query->where('role', 'assistant');
    }
    
    /**
     * Obtener el costo estimado de este mensaje
     */
    public function getEstimatedCostAttribute(): float
    {
        if (!$this->total_tokens) {
            return 0.0;
        }
        
        $conversation = $this->conversation;
        if (!$conversation) {
            return 0.0;
        }
        
        // Costos aproximados por 1M tokens
        $costPer1MTokens = match($conversation->agent_type) {
            'openai' => 5.00,
            'claude' => 15.00,
            'gemini' => 3.50,
            default => 5.00,
        };
        
        return ($this->total_tokens / 1_000_000) * $costPer1MTokens;
    }
    
    /**
     * Verifica si el mensaje tiene imágenes adjuntas
     */
    public function hasImages(): bool
    {
        return !empty($this->attachments['images'] ?? []);
    }
    
    /**
     * Obtener las URLs de imágenes adjuntas
     */
    public function getImagesAttribute(): array
    {
        return $this->attachments['images'] ?? [];
    }
    
    /**
     * Verifica si el mensaje tiene archivos adjuntos
     */
    public function hasFiles(): bool
    {
        return !empty($this->attachments['files'] ?? []);
    }
    
    /**
     * Obtener información de archivos adjuntos
     */
    public function getFilesAttribute(): array
    {
        return $this->attachments['files'] ?? [];
    }
    
    /**
     * Obtener un preview corto del contenido
     */
    public function getPreviewAttribute(): string
    {
        $preview = substr($this->content, 0, 100);
        if (strlen($this->content) > 100) {
            $preview .= '...';
        }
        return $preview;
    }
}


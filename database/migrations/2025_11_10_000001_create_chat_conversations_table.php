<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('account_id')->nullable()->constrained()->onDelete('set null');
            
            // Información del agente
            $table->string('agent_type', 50)->index(); // 'openai', 'claude', 'gemini'
            $table->string('model_name', 100); // 'gpt-5-nano', 'claude-sonnet-4-5'
            
            // Información de la conversación
            $table->string('title', 255)->nullable();
            $table->json('context_metadata')->nullable(); // Metadata adicional (document_id, session_key, etc.)
            
            // Control
            $table->enum('status', ['active', 'archived', 'deleted'])->default('active')->index();
            $table->timestamp('last_message_at')->nullable();
            
            $table->timestamps();
            
            // Índices compuestos para búsquedas comunes
            $table->index(['user_id', 'agent_type', 'status']);
            $table->index(['status', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};


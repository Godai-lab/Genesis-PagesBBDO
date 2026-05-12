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
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('chat_conversations')->onDelete('cascade');
            
            // Información del mensaje
            $table->enum('role', ['user', 'assistant', 'system', 'tool'])->index();
            $table->text('content'); // Contenido del mensaje
            $table->string('content_type', 50)->default('text'); // 'text', 'image', 'file', 'structured'
            
            // Metadata de LLM (para análisis de costos)
            $table->string('model_used', 100)->nullable(); // Modelo específico usado
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable()->index(); // Para análisis
            
            // Metadata adicional
            $table->json('metadata')->nullable(); // Tool calls, structured content, usage details
            $table->json('attachments')->nullable(); // URLs de imágenes/archivos
            
            // Control de visibilidad y eliminación
            $table->boolean('is_visible')->default(true)->index(); // Tools/system = false, User/Assistant = true
            $table->boolean('is_deleted')->default(false)->index();
            
            $table->timestamps();
            
            // Índices para búsquedas comunes (nombres cortos para MySQL)
            $table->index(['conversation_id', 'is_visible', 'is_deleted', 'created_at'], 'idx_msg_conv_visible');
            $table->index(['role', 'created_at'], 'idx_msg_role_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};


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
        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('providers')->onDelete('cascade');
            $table->string('name');
            $table->string('slug')->nullable()->comment('Nombre real del modelo usado en la API (ej: gemini3.0-flash para nano-banana-pro)');
            $table->enum('model_type', ['text', 'image', 'video', 'audio', 'service', 'presentation']);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->date('available_until')->nullable()->comment('Fecha hasta la cual el modelo estará disponible. NULL = Permanente/Indefinido');
            $table->timestamps();
            
            $table->index('provider_id');
            $table->index('model_type');
            $table->index('status');
            $table->index('slug');
            $table->index('available_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_models');
    }
};

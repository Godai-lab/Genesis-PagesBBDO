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
        Schema::create('usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('request_type', 100)->nullable()->comment('Tipo de generación completa: Genesis, Brief, Asistente Creativo, Asistente Social Media, Image Generator, Video Generator, Investigacion, etc.');
            $table->string('external_request_id', 255)->nullable()->comment('ID de la solicitud externa (ej: ID de tarea asíncrona de Perplexity, OpenAI, etc.) para evitar duplicados');
            $table->foreignId('generated_id')->nullable()->constrained('generateds')->onDelete('cascade')->comment('ID de la generación (Generated) a la que pertenece este registro de uso. Permite agrupar todos los costos de una generación completa (ej: un Genesis completo)');
            $table->foreignId('model_pricing_id')->nullable()->constrained('model_pricing')->onDelete('restrict')->comment('ID del pricing del modelo principal (opcional cuando hay múltiples modelos en processes_detail)');
            $table->json('usage_metrics')->nullable()->comment('Métricas de uso del último proceso o resumen (opcional cuando se usa processes_detail)');
            $table->json('pricing_snapshot')->nullable()->comment('Snapshot del pricing del modelo principal (opcional cuando hay múltiples modelos)');
            $table->json('processes_detail')->nullable()->comment('JSON con el detalle de todos los procesos/llamadas a IA que componen esta generación. Estructura: {processes: [...], summary: {...}}');
            $table->decimal('cost_input_usd', 20, 8)->nullable()->comment('Costo total de input de todos los procesos combinados');
            $table->decimal('cost_output_usd', 20, 8)->nullable()->comment('Costo total de output de todos los procesos combinados');
            $table->decimal('cost_total_usd', 20, 8)->comment('Costo total de todos los procesos combinados (suma de todos los costos en processes_detail)');
            $table->decimal('markup_percentage_applied', 5, 2);
            $table->decimal('cost_final_user_usd', 20, 8);
            $table->timestamps();
            
            $table->index('account_id');
            $table->index('user_id');
            $table->index('request_type');
            $table->index('external_request_id');
            $table->index('generated_id');
            $table->index(['model_pricing_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_records');
    }
};

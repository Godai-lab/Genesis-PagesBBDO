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
        Schema::create('model_pricing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_model_id')->constrained('ai_models')->onDelete('cascade');
            $table->enum('pricing_type', ['per_token', 'per_generation', 'per_second', 'per_credit']);
            $table->json('unit_definition');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('markup_percentage', 5, 2)->nullable()->comment('Margen de ganancia. NULL usa el global');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            
            $table->index('ai_model_id');
            $table->index(['effective_from', 'effective_to', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_pricing');
    }
};

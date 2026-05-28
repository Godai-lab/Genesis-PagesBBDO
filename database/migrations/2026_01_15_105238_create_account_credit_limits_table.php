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
        Schema::create('account_credit_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->decimal('monthly_base_limit_usd', 10, 2)->comment('Límite base mensual en USD ingresado por admin');
            $table->unsignedInteger('monthly_base_limit')->comment('Límite base mensual en créditos (calculado automáticamente)');
            $table->timestamps();
            
            $table->unique('account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_credit_limits');
    }
};

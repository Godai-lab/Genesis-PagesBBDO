<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Saldo/recarga por cuenta: monto USD válido entre period_start y period_end.
     * No se modifica una recarga usada; al agregar más saldo se crea un nuevo registro.
     */
    public function up(): void
    {
        Schema::create('credit_recharges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->decimal('amount_usd', 12, 2)->comment('Saldo en USD para este periodo');
            $table->unsignedInteger('amount_credits')->nullable()->comment('Equivalente en créditos');
            $table->date('period_start')->comment('Inicio del periodo de uso');
            $table->date('period_end')->comment('Fin: el saldo es válido hasta esta fecha');
            $table->foreignId('added_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable()->comment('Notas o razón de la recarga');
            $table->boolean('is_active')->default(true)->comment('Permite desactivar sin borrar (historial)');
            $table->timestamps();

            $table->index(['account_id', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_recharges');
    }
};

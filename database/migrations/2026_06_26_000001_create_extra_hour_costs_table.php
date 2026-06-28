<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migración para crear la tabla de costos por hora extra.
     * Permite configurar costos por día de la semana o rangos (entre semana / fin de semana).
     */
    public function up(): void
    {
        Schema::create('extra_hour_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();
            // Día de la semana (0=Domingo, 6=Sábado). NULL significa "aplica a todos los días no configurados".
            $table->unsignedTinyInteger('day_of_week')->nullable();
            // Tipo de rango: 'weekday' (L-V), 'weekend' (S-D), 'specific' (día concreto)
            $table->enum('range_type', ['weekday', 'weekend', 'specific'])->default('specific');
            // Costo por hora extra (en la moneda configurada)
            $table->decimal('cost_per_hour', 10, 2)->default(0);
            $table->timestamps();

            // Índice compuesto para búsquedas rápidas
            $table->unique(['payroll_id', 'day_of_week', 'range_type'], 'uq_extra_hour_cost');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_hour_costs');
    }
};

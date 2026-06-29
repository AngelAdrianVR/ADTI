<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega soporte para costos por hora extra específicos por usuario.
     * Un user_id NULL indica costo general (para todos).
     * Un user_id específico sobreescribe el costo general para ese usuario.
     */
    public function up(): void
    {
        Schema::table('extra_hour_costs', function (Blueprint $table) {
            // Eliminar el unique constraint anterior
            $table->dropUnique('uq_extra_hour_cost');

            // Agregar columna user_id (nullable = costo general)
            $table->foreignId('user_id')
                  ->nullable()
                  ->after('payroll_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // Nuevo unique: permite un costo por usuario (o general) + día + tipo
            $table->unique(['payroll_id', 'user_id', 'day_of_week', 'range_type'], 'uq_extra_hour_cost_v2');
        });
    }

    public function down(): void
    {
        Schema::table('extra_hour_costs', function (Blueprint $table) {
            $table->dropUnique('uq_extra_hour_cost_v2');
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->unique(['payroll_id', 'day_of_week', 'range_type'], 'uq_extra_hour_cost');
        });
    }
};

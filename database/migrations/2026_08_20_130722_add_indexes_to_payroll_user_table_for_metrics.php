<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega índices a payroll_user para optimizar las consultas de métricas
     * de costos y horas extra por proyecto (filtro por project_id + fecha +
     * estado de aprobación).
     */
    public function up(): void
    {
        Schema::table('payroll_user', function (Blueprint $table) {
            $table->index('project_id', 'idx_payroll_user_project_id');
            $table->index(['date', 'extra_hour_status'], 'idx_payroll_user_date_status');
        });
    }

    /**
     * Revertir los índices.
     */
    public function down(): void
    {
        Schema::table('payroll_user', function (Blueprint $table) {
            $table->dropIndex('idx_payroll_user_project_id');
            $table->dropIndex('idx_payroll_user_date_status');
        });
    }
};
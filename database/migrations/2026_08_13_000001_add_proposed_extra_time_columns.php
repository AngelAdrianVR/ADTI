<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega columnas para rastrear el "acuerdo" del tiempo extra ajustado
     * a través del pipeline de aprobación.
     *
     * - payroll_user.proposed_extra_hours/minutes: valor ajustado persistido por
     *   el último nivel que aprobó (se hereda a los siguientes niveles).
     * - extra_hour_approval_decisions.proposed_extra_hours/minutes: auditoría de
     *   QUÉ valor ajustó CADA aprobador en SU decisión.
     */
    public function up(): void
    {
        // 1. Columnas en payroll_user (persistencia del acuerdo a través de niveles)
        Schema::table('payroll_user', function (Blueprint $table) {
            $table->integer('proposed_extra_hours')->nullable()
                ->after('approved_extra_minutes')
                ->comment('Horas de tiempo extra acordadas por el último nivel que aprobó (se heredan al siguiente nivel)');

            $table->integer('proposed_extra_minutes')->nullable()
                ->after('proposed_extra_hours')
                ->comment('Minutos de tiempo extra acordados por el último nivel que aprobó (se heredan al siguiente nivel)');
        });

        // 2. Columnas en extra_hour_approval_decisions (auditoría por decisión)
        Schema::table('extra_hour_approval_decisions', function (Blueprint $table) {
            $table->integer('proposed_extra_hours')->nullable()
                ->after('status')
                ->comment('Horas que este aprobador dejó propuestas/acordadas en su decisión');

            $table->integer('proposed_extra_minutes')->nullable()
                ->after('proposed_extra_hours')
                ->comment('Minutos que este aprobador dejó propuestos/acordados en su decisión');
        });
    }

    public function down(): void
    {
        Schema::table('extra_hour_approval_decisions', function (Blueprint $table) {
            $table->dropColumn(['proposed_extra_hours', 'proposed_extra_minutes']);
        });

        Schema::table('payroll_user', function (Blueprint $table) {
            $table->dropColumn(['proposed_extra_hours', 'proposed_extra_minutes']);
        });
    }
};
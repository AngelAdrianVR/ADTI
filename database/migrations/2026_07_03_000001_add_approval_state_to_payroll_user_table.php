<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega columnas desnormalizadas para tracking eficiente del flujo de aprobación.
     */
    public function up(): void
    {
        Schema::table('payroll_user', function (Blueprint $table) {
            $table->enum('extra_hour_status', ['none', 'pending', 'approved', 'rejected'])
                ->default('none')
                ->after('approved_at')
                ->comment('none=sin extra, pending=esperando nivel, approved=aprobado, rejected=rechazado');

            $table->foreignId('current_approval_level_id')
                ->nullable()
                ->after('extra_hour_status')
                ->constrained('extra_hour_approval_levels')
                ->nullOnDelete()
                ->comment('Nivel que actualmente debe decidir (NULL si none/approved/rejected)');

            $table->index(
                ['payroll_id', 'extra_hour_status', 'current_approval_level_id'],
                'payroll_user_approval_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('payroll_user', function (Blueprint $table) {
            $table->dropIndex('payroll_user_approval_lookup_idx');
            $table->dropForeign(['current_approval_level_id']);
            $table->dropColumn(['extra_hour_status', 'current_approval_level_id']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migración para crear la tabla de niveles de autorización de horas extra.
     * Cada nivel pertenece a una nómina y contiene uno o más aprobadores.
     */
    public function up(): void
    {
        // Tabla de niveles de aprobación
        Schema::create('extra_hour_approval_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();
            // Nivel jerárquico (1, 2, 3...)
            $table->unsignedTinyInteger('level');
            // Nombre descriptivo del nivel (ej. "Supervisor Directo", "Gerente de Área")
            $table->string('name')->nullable();
            $table->timestamps();

            $table->unique(['payroll_id', 'level'], 'uq_approval_level');
        });

        // Tabla pivote: aprobadores por nivel
        Schema::create('extra_hour_approval_level_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_level_id')->constrained('extra_hour_approval_levels')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['approval_level_id', 'user_id'], 'uq_approval_level_user');
        });

        // Tabla de decisiones de aprobación por cada entrada de tiempo extra
        Schema::create('extra_hour_approval_decisions', function (Blueprint $table) {
            $table->id();
            // Referencia al registro de asistencia (payroll_user) que tiene tiempo extra
            $table->foreignId('payroll_user_id')->constrained('payroll_user')->cascadeOnDelete();
            // Nivel de aprobación al que pertenece esta decisión
            $table->foreignId('approval_level_id')->constrained('extra_hour_approval_levels')->cascadeOnDelete();
            // Usuario aprobador que tomó la decisión
            $table->foreignId('approver_id')->constrained('users')->cascadeOnDelete();
            // Estado de la decisión
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            // Comentario opcional del aprobador
            $table->text('comments')->nullable();
            // Fecha en que se tomó la decisión
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            // Un aprobador solo puede decidir una vez por entrada y nivel
            $table->unique(['payroll_user_id', 'approval_level_id', 'approver_id'], 'uq_approval_decision');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_hour_approval_decisions');
        Schema::dropIfExists('extra_hour_approval_level_user');
        Schema::dropIfExists('extra_hour_approval_levels');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla de grupos de aprobación y ajusta los niveles
     * para que pertenezcan a un grupo en lugar de directamente a la nómina.
     */
    public function up(): void
    {
        // 1. Crear tabla de grupos de aprobación
        Schema::create('extra_hour_approval_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();
            $table->string('name')->nullable(); // Nombre descriptivo del grupo
            $table->timestamps();
        });

        // 2. Tabla pivote: empleados asignados a cada grupo
        Schema::create('extra_hour_approval_group_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_group_id')->constrained('extra_hour_approval_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['approval_group_id', 'user_id'], 'uq_approval_group_user');
        });

        // 3. Agregar columna group_id a los niveles de aprobación
        //    (se elimina la restricción unique anterior porque ahora el nivel es por grupo)
        Schema::table('extra_hour_approval_levels', function (Blueprint $table) {
            // Eliminar el unique constraint anterior (payroll_id, level)
            $table->dropUnique('uq_approval_level');

            // Agregar la FK al grupo (nullable para datos existentes)
            $table->foreignId('approval_group_id')
                  ->nullable()
                  ->after('payroll_id')
                  ->constrained('extra_hour_approval_groups')
                  ->cascadeOnDelete();

            // Nuevo unique: por grupo y nivel
            $table->unique(['approval_group_id', 'level'], 'uq_approval_level_group');
        });
    }

    public function down(): void
    {
        Schema::table('extra_hour_approval_levels', function (Blueprint $table) {
            $table->dropUnique('uq_approval_level_group');
            $table->dropForeign(['approval_group_id']);
            $table->dropColumn('approval_group_id');
            $table->unique(['payroll_id', 'level'], 'uq_approval_level');
        });

        Schema::dropIfExists('extra_hour_approval_group_user');
        Schema::dropIfExists('extra_hour_approval_groups');
    }
};

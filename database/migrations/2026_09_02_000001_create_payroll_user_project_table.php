<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla pivote: vinculación de MÚLTIPLES proyectos por día laborado (payroll_user).
     *
     * Cada fila representa el trabajo del empleado en un proyecto durante un día,
     * con tipo de trabajo (interno/externo), departamento y el tiempo extra
     * invertido en ESE proyecto durante ESE día (para cuadrar métricas).
     */
    public function up(): void
    {
        Schema::create('payroll_user_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_user_id')->constrained('payroll_user')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            // Departamento al que se imputa la labor (de la tabla catálogo departments)
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('work_type', 20)->default('internal')->index(); // internal | external
            $table->unsignedSmallInteger('extra_hours')->nullable();
            $table->unsignedSmallInteger('extra_minutes')->nullable();
            $table->timestamps();

            $table->unique(['payroll_user_id', 'project_id'], 'pup_user_project_unique');
        });

        // Backfill: migrar las vinculaciones existentes (payroll_user.project_id)
        // hacia la nueva tabla pivote, usando como tiempo extra el aprobado
        // (o el solicitado si aún no se aprueba). work_type se asume 'internal'.
        $deptMap = DB::table('departments')->pluck('id', 'name');

        $legacy = DB::table('payroll_user as pu')
            ->join('users as u', 'u.id', '=', 'pu.user_id')
            ->select([
                'pu.id as payroll_user_id',
                'pu.project_id',
                'pu.approved_extra_hours',
                'pu.approved_extra_minutes',
                'pu.extra_hours',
                'pu.extra_minutes',
                'u.org_props',
            ])
            ->whereNotNull('pu.project_id')
            ->get();

        foreach ($legacy as $row) {
            $orgProps = json_decode($row->org_props ?? '{}', true);
            $departmentName = $orgProps['department'] ?? null;

            DB::table('payroll_user_project')->insert([
                'payroll_user_id' => $row->payroll_user_id,
                'project_id' => $row->project_id,
                'department_id' => $departmentName ? ($deptMap[$departmentName] ?? null) : null,
                'work_type' => 'internal',
                'extra_hours' => $row->approved_extra_hours ?? $row->extra_hours,
                'extra_minutes' => $row->approved_extra_minutes ?? $row->extra_minutes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_user_project');
    }
};

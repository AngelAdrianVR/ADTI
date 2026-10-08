<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vinculación de un proyecto a un día laborado (payroll_user).
 *
 * Permite asociar MÚLTIPLES proyectos por día, indicando si el trabajo fue
 * interno o externo, el departamento imputable y el tiempo extra invertido
 * en cada proyecto durante el día.
 */
class PayrollUserProject extends Model
{
    use HasFactory;

    protected $table = 'payroll_user_project';

    protected $fillable = [
        'payroll_user_id',
        'project_id',
        'department_id',
        'work_type',
        'extra_hours',
        'extra_minutes',
    ];

    protected $casts = [
        'extra_hours' => 'integer',
        'extra_minutes' => 'integer',
    ];

    public function payrollUser(): BelongsTo
    {
        return $this->belongsTo(PayrollUser::class, 'payroll_user_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Total de horas (con decimales) representado por este vínculo:
     * extra_hours + extra_minutes / 60.
     */
    public function getHoursAttribute(): float
    {
        $hours = (float) ($this->extra_hours ?? 0);
        $minutes = (float) ($this->extra_minutes ?? 0);

        return round($hours + ($minutes / 60), 2);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'client',
        'start_date',
        'estimated_end_date',
        'budgeted_hours',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'estimated_end_date' => 'date',
            'budgeted_hours' => 'decimal:2',
        ];
    }

    // --- Relaciones ---

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    // Nueva relación: Obtener sesiones de tiempo activas (sin hora de fin)
    // Esto nos permite saber QUIÉN está trabajando y en QUÉ tarea actualmente.
    public function activeTimeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class)
            ->whereNull('end_time')
            ->where('is_paused', false);
    }

    // Obtener los usuarios que han trabajado en este proyecto (Histórico)
    public function users()
    {
        return $this->belongsToMany(User::class, 'time_entries')->distinct();
    }

    // Registros de nómina (incidencias) vinculados a este proyecto con tiempo extra aprobado.
    // Permite sincronizar las horas extra aprobadas como "tiempo invertido" en el proyecto
    // sin duplicar registros: solo se lee desde el módulo de nóminas.
    public function extraTimeRecords(): HasMany
    {
        return $this->hasMany(PayrollUser::class, 'project_id')
            ->where('extra_hour_status', 'approved')
            ->where(function ($q) {
                $q->where('approved_extra_hours', '>', 0)
                    ->orWhere('approved_extra_minutes', '>', 0);
            });
    }

    // Total de horas extra aprobadas vinculadas a este proyecto
    // (consulta SQL agregada: evita cargar modelos)
    public function getExtraHoursTotalAttribute()
    {
        $totals = $this->extraTimeRecords()
            ->selectRaw('COALESCE(SUM(approved_extra_hours), 0) as hours, COALESCE(SUM(approved_extra_minutes), 0) as minutes')
            ->first();

        return round((float) $totals->hours + ((float) $totals->minutes / 60), 2);
    }

    // --- Scopes (Filtros) ---

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeFinished($query)
    {
        return $query->where('status', 'finished');
    }

    // --- Helpers para la Vista ---

    // Calcular horas reales consumidas:
    // 1. Tiempo registrado vía sesiones/manual (TimeEntry)
    // 2. + Horas extra APROBADAS vinculadas a este proyecto desde el módulo de nóminas.
    // Esto mantiene actualizado el "tiempo invertido" del proyecto aunque el empleado
    // solo haya registrado su tiempo extra en incidencias.
    public function getConsumedHoursAttribute()
    {
        // Sumar tiempos cerrados
        $seconds = $this->timeEntries()->sum('total_duration_seconds');

        // Convertir a horas (con 2 decimales)
        $timeEntryHours = round($seconds / 3600, 2);

        // Sumar horas extra aprobadas vinculadas al proyecto
        return round($timeEntryHours + $this->extra_hours_total, 2);
    }
}
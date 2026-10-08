<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'department_id',
        'description',
        'budgeted_hours',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'budgeted_hours' => 'decimal:2',
    ];

    // --- Atributos Virtuales (Appends) ---
    protected $appends = ['is_finished', 'consumed_hours'];

    public function getIsFinishedAttribute(): bool
    {
        return !is_null($this->completed_at);
    }

    // NUEVO: Calcular horas consumidas sumando los registros de tiempo asociados
    public function getConsumedHoursAttribute()
    {
        // Nota: Esto suma la duración de los time_entries relacionados a esta tarea.
        // Se divide entre 3600 para convertir segundos a horas.
        $seconds = $this->timeEntries->sum('total_duration_seconds');
        return round($seconds / 3600, 2);
    }

    // --- Relaciones ---
    
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    // Relación necesaria para el cálculo de horas
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }
}
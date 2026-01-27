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

    // Calcular horas reales consumidas (suma de duraciones cerradas + duración actual en vivo)
    public function getConsumedHoursAttribute()
    {
        // Sumar tiempos cerrados
        $seconds = $this->timeEntries()->sum('total_duration_seconds');
        
        // Convertir a horas (con 2 decimales)
        return round($seconds / 3600, 2);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TimeEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_id',
        'start_time',
        'end_time',
        'is_paused',
        'last_pause_start',
        'total_pause_seconds',
        'total_duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'last_pause_start' => 'datetime',
            'is_paused' => 'boolean',
        ];
    }

    // --- IMPORTANTE: Hacemos visible el atributo calculado en el JSON ---
    protected $appends = ['current_duration'];

    // --- Relaciones ---

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    // --- Lógica de Negocio (Acciones) ---

    // Calcular duración actual dinámica (para visualización en tiempo real)
    public function getCurrentDurationAttribute()
    {
        // Si ya finalizó, retornar la duración guardada
        if ($this->end_time) {
            return $this->total_duration_seconds;
        }

        $now = Carbon::now();
        
        // Tiempo total bruto desde el inicio hasta ahora
        $grossDuration = $this->start_time->diffInSeconds($now);

        // Si está pausado actualmente, descontamos el tiempo de la pausa actual
        if ($this->is_paused && $this->last_pause_start) {
            $currentPauseDuration = $this->last_pause_start->diffInSeconds($now);
            // El tiempo real es: Bruto - Pausas Anteriores - Pausa Actual
            return max(0, $grossDuration - $this->total_pause_seconds - $currentPauseDuration);
        }

        // Si está activo (trabajando), descontamos solo las pausas acumuladas
        return max(0, $grossDuration - $this->total_pause_seconds);
    }
}
<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Log;

class PayrollUser extends Pivot
{
    use HasFactory;

    public $incrementing = true;

    protected $fillable = [
        'date',
        'check_in',
        'check_in_location',
        'check_out',
        'check_out_location',
        'late',
        'extra_hours',
        'extra_minutes',
        'user_id',
        'payroll_id',
        'incidence',
        'project_id',
        'additionals',
        'checked_in_platform',
        // Nuevos campos
        'approved_extra_hours',
        'approved_extra_minutes',
        'approved_by',
        'approved_at'
    ];

    protected $casts = [
        'date' => 'date',
        'additionals' => 'array',
        'approved_at' => 'datetime',
        'project_id' => 'integer',
    ];

    // relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    // Relación para saber quién aprobó el tiempo
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Relación: Proyecto vinculado a este día
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    // Relación: Decisiones de aprobación por niveles para esta entrada
    public function approvalDecisions()
    {
        return $this->hasMany(ExtraHourApprovalDecision::class, 'payroll_user_id');
    }

    /**
     * Obtiene los límites del turno según la configuración del usuario.
     * Retorna un array con [start_of_shift, end_of_shift] como objetos Carbon.
     * Para turnos nocturnos (que cruzan medianoche), end_of_shift tiene addDay().
     */
    public function getShiftBoundaries(): array
    {
        $shift = $this->user->org_props['work_shift'] ?? 'Turno 3 (09:00 - 18:00)';

        return match ($shift) {
            'Turno 1 (06:00 - 14:00)' => [
                Carbon::createFromTime(6, 0),
                Carbon::createFromTime(14, 0),
            ],
            'Turno 2 (14:00 - 22:00)' => [
                Carbon::createFromTime(14, 0),
                Carbon::createFromTime(22, 0),
            ],
            'Turno 3 (09:00 - 18:00)' => [
                Carbon::createFromTime(9, 0),
                Carbon::createFromTime(18, 0),
            ],
            // Retrocompatibilidad con turnos antiguos
            'Nocturno (19:00 - 07:00)' => [
                Carbon::createFromTime(19, 0),
                Carbon::createFromTime(7, 0)->addDay(),
            ],
            'Nocturno (20:00 - 08:00)' => [
                Carbon::createFromTime(20, 0),
                Carbon::createFromTime(8, 0)->addDay(),
            ],
            'Diurno' => [
                Carbon::createFromTime(9, 0),
                Carbon::createFromTime(18, 0),
            ],
            default => [
                Carbon::createFromTime(9, 0),
                Carbon::createFromTime(18, 0),
            ],
        };
    }

    public function calculateExtraTime()
    {
        // Verifica si check_in y check_out están definidos
        if ($this->check_in && $this->check_out) {
            try {
                $check_in = Carbon::createFromFormat('H:i', trim($this->check_in));
                $check_out = Carbon::createFromFormat('H:i', trim($this->check_out));
            } catch (\Carbon\Exceptions\InvalidFormatException $e) {
                logger()->error('Error al calcular tiempo extra. Formato de hora inválido en check_in o check_out', [
                    'check_in' => $this->check_in,
                    'check_out' => $this->check_out,
                ]);
                return;
            }

            // --- LÓGICA NOCTURNA: Si la salida es numéricamente menor a la entrada, cruzó la medianoche
            if ($check_out->lessThan($check_in)) {
                $check_out->addDay();
            }

            $total_extra_minutes = 0;

            // Obtener límites del turno usando el nuevo método unificado
            [$start_of_shift, $end_of_shift] = $this->getShiftBoundaries();

            // Si es fin de semana, todo el tiempo trabajado es extra
            if (Carbon::parse($this->date)->isWeekend()) {
                $total_extra_minutes = $check_in->diffInMinutes($check_out);
            } else {
                // 1. Calcula el tiempo extra si llega ANTES de su hora de entrada oficial
                if ($check_in->lessThan($start_of_shift)) {
                    $total_extra_minutes += $check_in->diffInMinutes($start_of_shift);
                }

                // 2. Calcula el tiempo extra trabajado DESPUÉS de su hora de salida oficial
                if ($check_out->greaterThan($end_of_shift)) {
                    $total_extra_minutes += $end_of_shift->diffInMinutes($check_out);
                }
            }

            // Convertir el total de minutos extra acumulados a horas y minutos
            $extra_hours = intdiv($total_extra_minutes, 60);
            $extra_minutes = $total_extra_minutes % 60;

            // Actualiza los campos de horas y minutos extra
            $this->update([
                'extra_hours' => $extra_hours,
                'extra_minutes' => $extra_minutes,
            ]);
        }
    }

    public function calculateLate()
    {
        $toleranceMinutes = 15;

        // Obtener límites del turno usando el método unificado
        [$baseTime, $endTime] = $this->getShiftBoundaries();
        // Para el cálculo de retardo solo nos interesa la hora de entrada (baseTime)

        // Verifica si existe una hora de entrada (check_in) y limpia el valor
        if (!empty($this->check_in)) {
            try {
                $checkInTime = Carbon::createFromFormat('H:i', trim($this->check_in));

                // CORRECCIÓN: Extracción segura de la fecha para evitar "Double time specification"
                $safeDate = Carbon::parse($this->date)->toDateString();

                // Normalizamos con la fecha segura
                $baseDateTime = Carbon::parse($safeDate . ' ' . $baseTime->format('H:i'));
                $checkInDateTime = Carbon::parse($safeDate . ' ' . $checkInTime->format('H:i'));

                // Si el turno empieza de noche (ej. 18:00+) y la llegada es de mañana (ej. < 12:00), cruzó medianoche
                if ($baseTime->hour >= 18 && $checkInTime->hour < 12) {
                    $checkInDateTime->addDay();
                }

                // Calcula el límite de tiempo permitido incluyendo la tolerancia
                $allowedDateTime = $baseDateTime->copy()->addMinutes($toleranceMinutes);

                // Calcula minutos tarde si check_in es después de la hora permitida
                if ($checkInDateTime->greaterThan($allowedDateTime)) {
                    $lateMinutes = $allowedDateTime->diffInMinutes($checkInDateTime);

                    // Actualiza el campo 'late' en el modelo
                    $this->update([
                        'late' => $lateMinutes,
                    ]);
                } else {
                    $this->update([
                        'late' => 0,
                    ]);
                }
            } catch (\Carbon\Exceptions\InvalidFormatException $e) {
                // Log del error para depuración
                logger()->error('Al calcular retardo. Formato de hora inválido en check_in', [
                    'check_in' => $this->check_in,
                ]);

                $this->update([
                    'late' => 0,
                ]);
            }
        }
    }
}
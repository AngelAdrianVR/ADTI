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
        'check_out',
        'late',
        'extra_hours',
        'extra_minutes',
        'user_id',
        'payroll_id',
        'incidence',
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

            $total_extra_minutes = 0;

            // Si es fin de semana, todo el tiempo trabajado es extra
            if (Carbon::parse($this->date)->isWeekend()) {
                $total_extra_minutes = $check_in->diffInMinutes($check_out);
            } else {
                // De lunes a viernes
                $start_of_day = Carbon::createFromTime(9, 0); // 09:00 hrs
                $end_of_day = Carbon::createFromTime(18, 0); // 18:00 hrs

                // 1. Calcula el tiempo extra si llega ANTES de las 09:00 hrs
                if ($check_in->lessThan($start_of_day)) {
                    $total_extra_minutes += $check_in->diffInMinutes($start_of_day);
                }

                // 2. Calcula el tiempo trabajado DESPUÉS de las 18:00 hrs
                if ($check_out->greaterThan($end_of_day)) {
                    $total_extra_minutes += $end_of_day->diffInMinutes($check_out);
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
        $baseTime = Carbon::createFromTime(9, 0); // 09:00 AM

        // Verifica si existe una hora de entrada (check_in) y limpia el valor
        if (!empty($this->check_in)) {
            try {
                $checkInTime = Carbon::createFromFormat('H:i', trim($this->check_in));

                // Calcula el límite de tiempo permitido incluyendo la tolerancia
                $allowedTime = $baseTime->copy()->addMinutes($toleranceMinutes);

                // Calcula minutos tarde si check_in es después de la hora permitida
                if ($checkInTime->greaterThan($allowedTime)) {
                    $lateMinutes = $allowedTime->diffInMinutes($checkInTime);

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
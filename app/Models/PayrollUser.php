<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

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
        'checked_in_platform'
    ];

    protected $casts = [
        'date' => 'date',
        'additionals' => 'array',
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

    // public function calculateExtraTime()
    // {
    //     // Verifica si check_in y check_out están definidos y si es un día de fin de semana
    //     if ($this->check_in && $this->check_out && Carbon::parse($this->date)->isWeekend()) {
    //         $check_in = Carbon::createFromFormat('H:i', $this->check_in);
    //         $check_out = Carbon::createFromFormat('H:i', $this->check_out);

    //         $total_minutes = $check_in->diffInMinutes($check_out);
    //         $extra_hours = intdiv($total_minutes, 60);
    //         $extra_minutes = $total_minutes % 60;

    //         // Actualiza los campos de horas y minutos extra
    //         $this->update([
    //             'extra_hours' => $extra_hours,
    //             'extra_minutes' => $extra_minutes,
    //         ]);
    //     }
    // }

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

            $extra_hours = 0;
            $extra_minutes = 0;

            // Si es fin de semana, todo el tiempo trabajado es extra
            if (Carbon::parse($this->date)->isWeekend()) {
                $total_minutes = $check_in->diffInMinutes($check_out);
                $extra_hours = intdiv($total_minutes, 60);
                $extra_minutes = $total_minutes % 60;
            } else {
                // De lunes a viernes, calcula el tiempo trabajado después de las 18:00 hrs
                $end_of_day = Carbon::createFromTime(18, 0); // 18:00 hrs

                if ($check_out->greaterThan($end_of_day)) {
                    $extra_time = $end_of_day->diffInMinutes($check_out);
                    $extra_hours = intdiv($extra_time, 60);
                    $extra_minutes = $extra_time % 60;
                }
            }

            // Ajusta si los minutos exceden 60
            if ($extra_minutes >= 60) {
                $extra_hours += intdiv($extra_minutes, 60);
                $extra_minutes = $extra_minutes % 60;
            }

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

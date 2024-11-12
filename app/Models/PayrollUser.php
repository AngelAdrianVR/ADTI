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

    public function calculateExtraTime()
    {
        // Verifica si check_in y check_out están definidos y si es un día de fin de semana
        if ($this->check_in && $this->check_out && Carbon::parse($this->date)->isWeekend()) {
            $check_in = Carbon::createFromFormat('H:i', $this->check_in);
            $check_out = Carbon::createFromFormat('H:i', $this->check_out);

            $total_minutes = $check_in->diffInMinutes($check_out);
            $extra_hours = intdiv($total_minutes, 60);
            $extra_minutes = $total_minutes % 60;

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
        // Define la hora base de entrada
        $baseTime = Carbon::createFromTime(9, 0); // 09:00 AM

        // Verifica si existe una hora de entrada (check_in)
        if ($this->check_in) {
            $checkInTime = Carbon::createFromFormat('H:i', $this->check_in);

            // Calcula el límite de tiempo permitidos incluyendo la tolerancia
            $allowedTime = $baseTime->copy()->addMinutes($toleranceMinutes);

            // Calcula minutos tarde si check_in es después de la hora permitida
            if ($checkInTime->greaterThan($allowedTime)) {
                $lateMinutes = $allowedTime->diffInMinutes($checkInTime);

                // Actualiza el campo 'late' en el modelo
                $this->update([
                    'late' => $lateMinutes,
                ]);
            } else {
                // Si no está tarde, establece el campo 'late' en 0
                $this->update([
                    'late' => 0,
                ]);
            }
        }
    }
}

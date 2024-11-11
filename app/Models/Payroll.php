<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'start_date',
        'biweekly',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date'
    ];

    //Rellationships
    public function users()
    {
        return $this->belongsToMany(User::class)
            ->using(PayrollUser::class)
            ->withPivot([
                'id',
                'date',
                'check_in',
                'check_out',
                'late',
                'extra_hours',
                'extra_minutes',
                'incidence',
                'additionals',
                'checked_in_platform',
            ])
            ->withTimestamps();
    }

    public static function getCurrent()
    {
        return self::orderBy('id', 'desc')->first();
    }

    // methods
    public function getProcessedAttendances($user_id)
    {
        $attendances = PayrollUser::where('user_id', $user_id)
            ->where('payroll_id', $this->id)
            ->oldest('date')
            ->get();
        $user = User::find($user_id);

        $processed = [];
        for ($i = 0; $i < 14; $i++) {
            $current_date = $this->start_date->copy()->addDays($i);

            // Verificar festivo fijo
            $holiday = Holiday::where('is_active', 1)
                ->where(function ($query) use ($current_date) {
                    $query->where(function ($query) use ($current_date) {
                        // Para días festivos fijos (no personalizados)
                        $query->where('is_custom_date', 0)
                            ->whereMonth('date', $current_date->month)
                            ->whereDay('date', $current_date->day);
                    })->orWhere(function ($query) use ($current_date) {
                        // Para días festivos personalizados
                        $query->where('is_custom_date', 1)
                            ->where('ordinal', $this->getOrdinalInMonth($current_date))
                            ->where('week_day', $current_date->dayOfWeek)
                            ->where('month', $current_date->month);
                    });
                })->first();

            // Días de descanso (sábados y domingos)
            $is_day_off = in_array($i, [4, 5, 11, 12]);

            // Verificar si ya existe un registro de asistencia para este día
            $current = $attendances->firstWhere('date', $current_date);
            if ($current) {
                $processed[] = $current;
            } else {
                // Crear un nuevo registro procesado
                $payroll_user = new PayrollUser(['date' => $current_date->toDateString()]);

                if ($holiday && !$is_day_off) {
                    // Día festivo
                    $payroll_user->incidence = $holiday->name;
                } else {
                    // Verificar si la fecha ya pasó o si es futura
                    if ($current_date->lessThan(Carbon::parse($user->org_props['entry_date'])) || $current_date->greaterThan(now())) {
                        $payroll_user->incidence = 'Sin registro aún';
                    } else {
                        // Días ya pasados
                        $payroll_user->incidence = $is_day_off ? 'Descanso' : 'Falta injustificada';
                    }
                }
                $processed[] = $payroll_user;
            }
        }
        return $processed;
    }

    /**
     * Obtener el ordinal (primero, segundo, etc.) del día en el mes.
     */
    private function getOrdinalInMonth($date)
    {
        return (int) ceil($date->day / 7);
    }
}

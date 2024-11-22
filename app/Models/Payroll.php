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
                        // Días festivos fijos (no personalizados)
                        $query->where('is_custom_date', 0)
                            ->whereMonth('date', $current_date->month)
                            ->whereDay('date', $current_date->day);
                    })->orWhere(function ($query) use ($current_date) {
                        // Días festivos personalizados
                        $query->where('is_custom_date', 1)
                            ->where('ordinal', $this->getOrdinalTextInSpanish($current_date))
                            ->where('week_day', $this->getWeekdayInSpanish($current_date))
                            ->where('month', $this->getMonthInSpanish($current_date));
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
                    $payroll_user->incidence = "Día festivo ($holiday->name)";
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
     * Obtener el ordinal (Primero, Segundo, etc.) en español del día en el mes.
     */
    private function getOrdinalTextInSpanish($date)
    {
        $ordinals = ['Primero', 'Segundo', 'Tercero', 'Cuarto', 'Quinto'];
        $index = (int) ceil($date->day / 7) - 1;
        return $ordinals[$index] ?? null;
    }

    /**
     * Obtener el nombre del día de la semana en español.
     */
    private function getWeekdayInSpanish($date)
    {
        $weekDays = [
            0 => 'Domingo',
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado'
        ];
        return $weekDays[$date->dayOfWeek] ?? null;
    }

    /**
     * Obtener el nombre del mes en español.
     */
    private function getMonthInSpanish($date)
    {
        $months = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre'
        ];
        return $months[$date->month] ?? null;
    }
}

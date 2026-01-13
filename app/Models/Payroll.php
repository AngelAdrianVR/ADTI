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
    // OPTIMIZACIÓN: Acepta colecciones pre-cargadas para evitar N+1 queries
    public function getProcessedAttendances($user_id, $preLoadedAttendances = null, $holidays = null)
    {
        // Si no se pasan datos pre-cargados, los buscamos (retrocompatibilidad)
        $attendances = $preLoadedAttendances ?? PayrollUser::where('user_id', $user_id)
            ->where('payroll_id', $this->id)
            ->get();
            
        // Convertimos a colección key-value por fecha para búsqueda rápida O(1)
        $attendancesMap = $attendances->keyBy(function ($item) {
            return $item->date->toDateString();
        });

        $user = User::find($user_id);

        $processed = [];
        for ($i = 0; $i < 14; $i++) {
            $current_date = $this->start_date->copy()->addDays($i);
            $dateString = $current_date->toDateString();

            // Verificar festivo usando la colección optimizada
            $is_holiday = $holidays 
                ? $holidays->contains(fn($h) => $h->date->isSameDay($current_date))
                : Holiday::whereDate('date', $current_date)->exists();

            $day_of_week = $current_date->dayOfWeek;

            // Buscar asistencia en memoria
            $payroll_user = $attendancesMap->get($dateString);

            if ($payroll_user) {
                // Si existe registro, usarlo
                $processed[] = $payroll_user;
            } else {
                // Crear objeto "dummy" para días sin registro
                $payroll_user = new PayrollUser();
                $payroll_user->date = $current_date;
                $payroll_user->user_id = $user->id;
                $payroll_user->payroll_id = $this->id;
                
                // Lógica de incidencias
                if ($is_holiday) {
                    $payroll_user->incidence = "Día festivo";
                } else {
                    if ($day_of_week == 0) { // Domingo
                        $payroll_user->incidence = "Domingo";
                    } else {
                        // Verificar si es día de descanso (Sábado para algunos, Domingo para todos por defecto)
                        // Aquí asumimos lógica estándar, adaptar según reglas de negocio específicas
                        $is_day_off = false; 
                        // Ejemplo: Si el usuario tiene horario personalizado, verificar aquí.
                        // Por ahora mantenemos la lógica original de "Falta" si no es domingo/festivo
                        
                        // NOTA: Para no romper lógica existente, marcamos como falta si ya pasó la fecha
                        // y no es futuro.
                        if ($current_date->lt(now()->startOfDay())) {
                             $payroll_user->incidence = 'Falta injustificada';
                        } else {
                             $payroll_user->incidence = 'Día normal'; // O null
                        }
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
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
            $current_date = $this->start_date->addDays($i);
            $holiday = Holiday::whereMonth('date', $current_date->month)
                ->whereDay('date', $current_date->day)
                ->where('is_active', 1)->first();
            $current = $attendances->firstWhere('date', $current_date);
            // dias de descanso siempre son los sabados y domingos
            $is_day_off = $i === 4 || $i === 5 || $i === 11 || $i === 12;
            if ($current) { //existe un registro de asistencia este dia
                $processed[] = $current;
            } else { // no hay registro, se crea uno procesado
                $payroll_user = new PayrollUser(['date' => $current_date->toDateString()]);
                // dia festivo
                if ($holiday && !$is_day_off) {
                    $payroll_user->incidence = $holiday->name;
                } else {
                    // dia todavia no pasa
                    if ($current_date->lessThan(Carbon::parse($user->org_props['entry_date'])) || $current_date->greaterThan(now())) {
                        $payroll_user->incidence = 'Sin registro aún';
                    } else { //dias que ya pasaron
                        if ($is_day_off) { //dia de escanso
                            $payroll_user->incidence = 'Descanso';
                        } else { //falta injustificada
                            $payroll_user->incidence = 'Falta injustificada';
                        }
                    }
                }
                $processed[] = $payroll_user;
            }
        }
        return $processed;
    }
}

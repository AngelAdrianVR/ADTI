<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasMedia
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use HasRoles;
    use InteractsWithMedia;

    protected $fillable = [
        'code',
        'name',
        'email',
        'password',
        'phone',
        'birthdate',
        'civil_state',
        'address',
        'rfc',
        'curp',
        'ssn',
        'org_props',
        'is_active',
        'inactivate_date',
        'inactivate_reason',
        'profile_photo_path',
        'home_office',
        'paused',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birthdate' => 'date',
            'inactivate_date' => 'date',
            'password' => 'hashed',
            'org_props' => 'array',
        ];
    }

    //relationships
    public function payrolls()
    {
        return $this->belongsToMany(Payroll::class)
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

    // methods ------------------------------------------------------------------------------------
    //metodo que recupera la siguiente insidencia
    public function getNextAttendance()
    {
        $next = '';
        $today_attendance = PayrollUser::where('user_id', $this->id)->whereDate('date', today())->first();
        if (is_null($today_attendance)) {
            $next = 'Registrar entrada';
        } elseif (is_null($today_attendance->check_out)) {
            $next = 'Registrar salida';
        } else {
            $next = 'Día terminado';
        }

        return $next;
    }

    public function updateVacations()
    {
        $entryDate = Carbon::parse($this->org_props['entry_date']);
        $yearsWorked = (int) $entryDate->diffInYears(now());

        // Días de vacaciones según la antigüedad
        $vacationDaysPerYear = match (true) {
            $yearsWorked === 0 => 12,
            $yearsWorked === 1 => 14,
            $yearsWorked === 2 => 16,
            $yearsWorked === 3 => 18,
            $yearsWorked === 4 => 20,
            $yearsWorked >= 5 && $yearsWorked <= 9 => 22,
            $yearsWorked >= 10 && $yearsWorked <= 14 => 24,
            $yearsWorked >= 15 && $yearsWorked <= 19 => 26,
            $yearsWorked >= 20 && $yearsWorked <= 24 => 28,
            $yearsWorked >= 25 && $yearsWorked <= 29 => 30,
            default => 12,
        };

        // Calcula los días proporcionales para una semana (1/52 del total anual)
        $weeklyVacationDays = round($vacationDaysPerYear / 52, 2);

        // Suma los días proporcionales a las vacaciones actuales
        $org_props = $this->org_props;
        $org_props['vacations'] = ($org_props['vacations'] ?? 0) + $weeklyVacationDays;

        // Actualiza la fecha de la última actualización
        $org_props['updated_date_vacations'] = now()->toDateString();
        $this->org_props = $org_props;
        $this->save();
    }

    public function setAttendance()
    {
        $next = '';
        $now_time = now()->isoFormat('HH:mm');
        $today_attendance = PayrollUser::firstOrCreate(['date' => today()->toDateString(), 'user_id' => $this->id], [
            'payroll_id' => Payroll::firstWhere('is_active', true)->id,
            'checked_in_platform' => true,
            'late' => 0,
        ]);

        if (is_null($today_attendance->check_in)) {
            $today_attendance->update([
                'check_in' => $now_time,
            ]);
            $today_attendance->calculateLate();
            $next = 'Registrar salida';
        } elseif (is_null($today_attendance->check_out)) {
            $today_attendance->update([
                'check_out' => $now_time,
            ]);
            $today_attendance->calculateExtraTime();
            $next = 'Día terminado';
        }

        $this->update(['paused' => null]);

        return $next;
    }

    public function setPause()
    {
        if ($this->paused) {
            $this->update(['paused' => null]);
            return false;
        } else {
            $time = now()->isoFormat('h:mm a');
            $this->update(['paused' => $time]);
            return $time;
        }
    }

    public function timeEntries()
    {
        return $this->hasMany(TimeEntry::class);
    }

    // Retorna la entrada de tiempo activa (si existe)
    public function activeTimeEntry()
    {
        return $this->hasOne(TimeEntry::class)
            ->whereNull('end_time')
            ->latest();
    }
}

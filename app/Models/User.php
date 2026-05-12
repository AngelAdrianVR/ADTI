<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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
        'employees_in_charge', // Nuevo campo
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
            'home_office' => 'boolean',
            'employees_in_charge' => 'array', // Cast automático a array
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
                'check_in_location',
                'check_out',
                'check_out_location',
                'late',
                'extra_hours',
                'extra_minutes',
                'incidence',
                'additionals',
                'checked_in_platform',
                // Nuevos campos
                'approved_extra_hours',
                'approved_extra_minutes',
                'approved_by',
                'approved_at'
            ])
            ->withTimestamps();
    }

    // --- Relación de Solicitudes de Vacaciones ---
    public function vacationRequests()
    {
        return $this->hasMany(VacationRequest::class);
    }

    // methods ------------------------------------------------------------------------------------
    //metodo que recupera la siguiente insidencia
    public function getNextAttendance()
    {
        $now = now();
        $today = $now->toDateString();

        // 1. Buscar si hay un turno "abierto" reciente (menos de 18 horas desde el check-in)
        // Esto soluciona los turnos nocturnos que cruzan la medianoche
        $open_attendance = PayrollUser::where('user_id', $this->id)
            ->whereNotNull('check_in')
            ->whereNull('check_out')
            ->orderBy('date', 'desc')
            ->first();

        if ($open_attendance) {
            // CORRECCIÓN: Extraer la fecha de forma segura
            $safeDate = Carbon::parse($open_attendance->date)->toDateString();
            $checkInDateTime = Carbon::parse($safeDate . ' ' . trim($open_attendance->check_in));
            
            // Si el check-in fue hace menos de 18 horas, sigue siendo válido para darle salida
            if ($checkInDateTime->diffInHours($now) < 18) {
                return 'Registrar salida';
            }
        }

        // 2. Si no hay turno abierto válido, revisar si ya cerró el turno de hoy
        $last_closed = PayrollUser::where('user_id', $this->id)
            ->where('date', $today)
            ->orderBy('date', 'desc')
            ->first();

        if ($last_closed && !is_null($last_closed->check_out)) {
            return 'Día terminado';
        }

        return 'Registrar entrada';
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

        // --- NUEVO: Registrar el devengo en el historial ---
        UserVacationAdjustment::create([
            'user_id' => $this->id,
            'days' => $weeklyVacationDays,
            'notes' => 'Devengo proporcional semanal (Automático)',
            'date' => now()->toDateString(),
        ]);
    }

    public function setAttendance($location = null)
    {
        $now = now();
        $now_time = $now->isoFormat('HH:mm');
        $today_date = $now->toDateString();

        // 1. Buscar turno abierto reciente (soporte universal para cruzar medianoche)
        $open_attendance = PayrollUser::where('user_id', $this->id)
            ->whereNotNull('check_in')
            ->whereNull('check_out')
            ->orderBy('date', 'desc')
            ->first();

        if ($open_attendance) {
            // CORRECCIÓN: Extraer la fecha de forma segura
            $safeDate = Carbon::parse($open_attendance->date)->toDateString();
            $checkInDateTime = Carbon::parse($safeDate . ' ' . trim($open_attendance->check_in));
            
            // Si pasaron menos de 18 horas, es una salida válida de su turno
            if ($checkInDateTime->diffInHours($now) < 18) {
                // Prevenir doble clic rápido accidental (menos de 3 minutos)
                if ($checkInDateTime->diffInMinutes($now) <= 3) {
                    return 'Registrar salida';
                }

                $open_attendance->update([
                    'check_out' => $now_time,
                    'check_out_location' => $location,
                ]);
                $open_attendance->calculateExtraTime();
                $this->update(['paused' => null]);
                
                return 'Día terminado';
            }
            // Si pasaron más de 18 horas, asumimos que olvidó checar y procedemos a dar nueva entrada
        }

        // 2. Si no es salida de un turno previo, registramos nueva entrada para el día actual
        $today_attendance = PayrollUser::firstOrCreate([
            'date' => $today_date, 
            'user_id' => $this->id
        ], [
            'payroll_id' => Payroll::firstWhere('is_active', true)->id,
            'checked_in_platform' => true,
            'late' => 0,
        ]);

        if (is_null($today_attendance->check_in)) {
            $today_attendance->update([
                'check_in' => $now_time,
                'check_in_location' => $location,
            ]);
            $today_attendance->calculateLate();
            $this->update(['paused' => null]);
            
            return 'Registrar salida';
        }

        return 'Día terminado';
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
<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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
                'pausas',
                'check_out',
                'late',
                'extras_enabled',
                'extra_hours',
                'extra_minutes',
                'additionals',
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
            $next = 'Dia terminado';
        }

        return $next;
    }
}

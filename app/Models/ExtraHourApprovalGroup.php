<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExtraHourApprovalGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_id',
        'name',
    ];

    // Relaciones
    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    // Empleados asignados a este grupo
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'extra_hour_approval_group_user', 'approval_group_id', 'user_id')
            ->withTimestamps();
    }

    // Niveles de autorización dentro de este grupo
    public function levels(): HasMany
    {
        return $this->hasMany(ExtraHourApprovalLevel::class, 'approval_group_id')->orderBy('level');
    }
}

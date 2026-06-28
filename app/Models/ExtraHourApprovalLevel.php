<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExtraHourApprovalLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_id',
        'approval_group_id',
        'level',
        'name',
    ];

    protected $casts = [
        'level' => 'integer',
    ];

    // Relaciones
    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    // Grupo de aprobación al que pertenece este nivel
    public function group(): BelongsTo
    {
        return $this->belongsTo(ExtraHourApprovalGroup::class, 'approval_group_id');
    }

    // Usuarios aprobadores de este nivel
    public function approvers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'extra_hour_approval_level_user', 'approval_level_id', 'user_id')
            ->withTimestamps();
    }

    // Decisiones tomadas en este nivel
    public function decisions(): HasMany
    {
        return $this->hasMany(ExtraHourApprovalDecision::class, 'approval_level_id');
    }
}

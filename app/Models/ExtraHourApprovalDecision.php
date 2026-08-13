<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtraHourApprovalDecision extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_user_id',
        'approval_level_id',
        'approver_id',
        'status',
        'proposed_extra_hours',
        'proposed_extra_minutes',
        'comments',
        'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    // Relaciones
    public function payrollUser(): BelongsTo
    {
        return $this->belongsTo(PayrollUser::class);
    }

    public function approvalLevel(): BelongsTo
    {
        return $this->belongsTo(ExtraHourApprovalLevel::class, 'approval_level_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}

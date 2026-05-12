<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VacationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'start_date',
        'end_date',
        'days_requested',
        'status',
        'resolved_by',
        'resolved_at',
        'employee_notes',
        'reviewer_notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'resolved_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
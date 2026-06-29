<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtraHourCost extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_id',
        'user_id',
        'day_of_week',
        'range_type',
        'cost_per_hour',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'day_of_week' => 'integer',
        'cost_per_hour' => 'decimal:2',
    ];

    // Relaciones
    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

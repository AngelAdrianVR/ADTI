<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'date',
        'ordinal',
        'week_day',
        'month',
        'is_active',
        'is_custom_date',
    ];

    protected $casts = [
        'date' => 'date'
    ];
}

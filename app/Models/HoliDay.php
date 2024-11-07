<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HoliDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'date',
        'is_active',
    ];

    protected $casts = [
        'date' => 'date'
    ];
}

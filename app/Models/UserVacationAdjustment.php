<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserVacationAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'days',
        'notes',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
        'days' => 'float', // Cast a float para manejar decimales en el frontend
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_id',
        'user_id',
        'comments',
    ];

    // relaciones
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BioTimeTransactions extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'quantity',
    ];

    protected $cats = [
        'date' => 'date'
    ];
}

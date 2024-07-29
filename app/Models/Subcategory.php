<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subcategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'key',
        'level',
        'features',
        'category_id',
    ];

    protected $casts = [
        'features' => 'array'
    ];

    //relationships --------------------------------
    public function category() :BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function products() :HasMany
    {
        return $this->hasMany(Product::class);
    }
}

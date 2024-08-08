<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Subcategory extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'name',
        'key',
        'level',
        'features',
        'category_id',
        'prev_subcategory_id', //guarda el id de la subcategoria previa. (si es nulo el anterior ya es la categoría)
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

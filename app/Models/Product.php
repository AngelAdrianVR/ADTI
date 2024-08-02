<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Product extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'name',
        'description',
        'part_number',
        'location',
        'features',
        'bread_crumbles', //guarda todos los nombres de las subcategorias que sigue
        'subcategory_id', //última subcategoría. traza el camino completo
    ];

    protected $casts = [
        'features' => 'array',
        'bread_crumbles' => 'array'
    ];

    //relationships ---------------------
    public function subcategory() :BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }
}

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
        'consecutivo', //numero consecutivo de máximo 3 digitos para numero de parte interno por subcategoría.
        'description',
        'part_number', //numero de parte del interno
        'part_number_supplier', //numero de parte del fabricante
        'location',
        'line_cost',
        'features',
        'features_keys', //arreglo de claves de caracteristicas en orden necesarias para formar # de parte interno
        'bread_crumbles', //guarda todos los nombres de las subcategorias que sigue
        'currency', //Moneda
        'subcategory_id', //última subcategoría. traza el camino completo
    ];

    protected $casts = [
        'features' => 'array',
        'features_keys' => 'array',
        'bread_crumbles' => 'array'
    ];

    //relationships ---------------------
    public function subcategory() :BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }
}

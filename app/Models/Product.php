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
        'subcategory_id',
    ];

    protected $casts = [
        'features' => 'array'
    ];

    //relationships ---------------------
    public function subcategory() :BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }
}

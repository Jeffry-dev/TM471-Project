<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItem extends Model
{
    protected $fillable = [
        'name',
        'description',
        'ingredients',
        'allergens',
        'dietary_tags',
        'price',
        'category',
        'category_id',
        'image_url',
        'is_available',
    ];

    protected $casts = [
        'price' => 'float',
        'is_available' => 'boolean',
        'ingredients' => 'array',
        'allergens' => 'array',
        'dietary_tags' => 'array',
    ];

    public function categoryRef(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}

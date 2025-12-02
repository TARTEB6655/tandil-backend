<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    // Specify the fields that can be mass-assigned
    protected $fillable = [
        'category_id',
        'name',
        'description',
        'price',
        'stock',
        'image',
    ];

    /**
     * Define relationship with Category model
     * Each product belongs to one category.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Accessor for image URL
     * If image field stores filename, return full URL.
     */
    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return url('storage/products/' . $this->image);
        }
        return null;
    }
}

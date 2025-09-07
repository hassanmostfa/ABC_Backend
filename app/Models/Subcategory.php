<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Subcategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name_en',
        'name_ar',
        'image_path',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the category that owns the subcategory
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the products for the subcategory
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}

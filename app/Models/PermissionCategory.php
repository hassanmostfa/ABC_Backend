<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PermissionCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the permission items that belong to this category.
     */
    public function permissionItems()
    {
        return $this->hasMany(PermissionItem::class)->orderBy('sort_order');
    }

    /**
     * Get active permission items.
     */
    public function activePermissionItems()
    {
        return $this->permissionItems()->where('is_active', true);
    }
}

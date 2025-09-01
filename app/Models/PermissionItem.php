<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PermissionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'permission_category_id',
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
     * Get the permission category that this item belongs to.
     */
    public function permissionCategory()
    {
        return $this->belongsTo(PermissionCategory::class);
    }

    /**
     * Get the role permissions for this item.
     */
    public function rolePermissions()
    {
        return $this->hasMany(RolePermission::class);
    }
}

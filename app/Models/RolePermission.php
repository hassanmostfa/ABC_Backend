<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'role_id',
        'permission_item_id',
        'can_view',
        'can_add',
        'can_edit',
        'can_delete',
    ];

    protected $casts = [
        'can_view' => 'boolean',
        'can_add' => 'boolean',
        'can_edit' => 'boolean',
        'can_delete' => 'boolean',
    ];

    /**
     * Get the role that this permission belongs to.
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the permission item that this permission belongs to.
     */
    public function permissionItem()
    {
        return $this->belongsTo(PermissionItem::class);
    }

    /**
     * Check if the role has a specific action for this permission.
     */
    public function can($action)
    {
        return $this->{"can_$action"} ?? false;
    }
}

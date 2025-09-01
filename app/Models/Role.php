<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the admins that belong to this role.
     */
    public function admins()
    {
        return $this->hasMany(Admin::class);
    }

    /**
     * Get the permissions for this role.
     */
    public function permissions()
    {
        return $this->hasMany(RolePermission::class);
    }

    /**
     * Check if the role has a specific permission.
     */
    public function hasPermission($permissionItem, $action = null)
    {
        $permission = $this->permissions()
            ->whereHas('permissionItem', function ($query) use ($permissionItem) {
                if (is_string($permissionItem)) {
                    $query->where('slug', $permissionItem);
                } else {
                    $query->where('id', $permissionItem);
                }
            })
            ->first();

        if (!$permission) {
            return false;
        }

        if ($action) {
            return $permission->{"can_$action"} ?? false;
        }

        return $permission->can_view || $permission->can_add || $permission->can_edit || $permission->can_delete;
    }

    /**
     * Assign permissions to the role.
     */
    public function assignPermissions($permissions)
    {
        foreach ($permissions as $permissionItemId => $actions) {
            $this->permissions()->updateOrCreate(
                ['permission_item_id' => $permissionItemId],
                [
                    'can_view' => $actions['view'] ?? false,
                    'can_add' => $actions['add'] ?? false,
                    'can_edit' => $actions['edit'] ?? false,
                    'can_delete' => $actions['delete'] ?? false,
                ]
            );
        }
    }
}

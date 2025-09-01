<?php

namespace App\Traits;

trait HasPermissions
{
    /**
     * Check if the admin has a specific permission.
     */
    public function hasPermission($permissionItem, $action = null): bool
    {
        if (!$this->role) {
            return false;
        }

        return $this->role->hasPermission($permissionItem, $action);
    }

    /**
     * Check if the admin can view a specific item.
     */
    public function canView($permissionItem): bool
    {
        return $this->hasPermission($permissionItem, 'view');
    }

    /**
     * Check if the admin can add a specific item.
     */
    public function canAdd($permissionItem): bool
    {
        return $this->hasPermission($permissionItem, 'add');
    }

    /**
     * Check if the admin can edit a specific item.
     */
    public function canEdit($permissionItem): bool
    {
        return $this->hasPermission($permissionItem, 'edit');
    }

    /**
     * Check if the admin can delete a specific item.
     */
    public function canDelete($permissionItem): bool
    {
        return $this->hasPermission($permissionItem, 'delete');
    }

    /**
     * Check if the admin has any permission for a specific item.
     */
    public function hasAnyPermission($permissionItem): bool
    {
        return $this->hasPermission($permissionItem);
    }

    /**
     * Check if the admin has all permissions for a specific item.
     */
    public function hasAllPermissions($permissionItem): bool
    {
        if (!$this->role) {
            return false;
        }

        $permission = $this->role->permissions()
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

        return $permission->can_view && $permission->can_add && $permission->can_edit && $permission->can_delete;
    }
}

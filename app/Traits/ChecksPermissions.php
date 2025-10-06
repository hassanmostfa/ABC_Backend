<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use App\Models\Admin;

trait ChecksPermissions
{
    /**
     * Check if the authenticated admin has a specific permission.
     */
    protected function checkPermission($request, string $permission, string $action = null): bool
    {
        $user = $request->user();
        
        if (!$user || !($user instanceof Admin)) {
            return false;
        }

        if ($action) {
            return $user->hasPermission($permission, $action);
        }

        return $user->hasAnyPermission($permission);
    }

    /**
     * Check permission and return error response if failed.
     */
    protected function checkPermissionOrFail($request, string $permission, string $action = null): ?JsonResponse
    {
        if (!$this->checkPermission($request, $permission, $action)) {
            $actionText = $action ? "$action permission for $permission" : "permission for $permission";
            return response()->json([
                'success' => false,
                'message' => "You do not have $actionText"
            ], 403);
        }

        return null;
    }

    /**
     * Check if admin can view a specific item.
     */
    protected function canViewOrFail($request, string $permission): ?JsonResponse
    {
        return $this->checkPermissionOrFail($request, $permission, 'view');
    }

    /**
     * Check if admin can add a specific item.
     */
    protected function canAddOrFail($request, string $permission): ?JsonResponse
    {
        return $this->checkPermissionOrFail($request, $permission, 'add');
    }

    /**
     * Check if admin can edit a specific item.
     */
    protected function canEditOrFail($request, string $permission): ?JsonResponse
    {
        return $this->checkPermissionOrFail($request, $permission, 'edit');
    }

    /**
     * Check if admin can delete a specific item.
     */
    protected function canDeleteOrFail($request, string $permission): ?JsonResponse
    {
        return $this->checkPermissionOrFail($request, $permission, 'delete');
    }
}

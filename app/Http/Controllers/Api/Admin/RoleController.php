<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\PermissionCategory;
use App\Models\PermissionItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    /**
     * Display a listing of the roles.
     */
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions.permissionItem.permissionCategory')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $roles,
            'message' => 'Roles retrieved successfully'
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created role in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'description' => 'nullable|string',
            'permissions' => 'required|array',
        ]);

        $role = Role::create([
            'name' => $request->name,
            'description' => $request->description,
            'is_active' => true,
        ]);

        // Get all permission items
        $permissionItems = PermissionItem::where('is_active', true)->get();
        
        // Assign permissions based on the request
        foreach ($permissionItems as $item) {
            $permissionData = $request->permissions[$item->slug] ?? [];
            
            $role->permissions()->create([
                'permission_item_id' => $item->id,
                'can_view' => $permissionData['view'] ?? 0,
                'can_add' => $permissionData['add'] ?? 0,
                'can_edit' => $permissionData['edit'] ?? 0,
                'can_delete' => $permissionData['delete'] ?? 0,
            ]);
        }

        // Get all permissions for this role as 1s and 0s
        $rolePermissions = $role->permissions()
            ->with('permissionItem.permissionCategory')
            ->get()
            ->mapWithKeys(function ($permission) {
                $itemSlug = $permission->permissionItem->slug;
                return [
                    $itemSlug => [
                        'view' => (int) $permission->can_view,
                        'add' => (int) $permission->can_add,
                        'edit' => (int) $permission->can_edit,
                        'delete' => (int) $permission->can_delete,
                    ]
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'permissions' => $rolePermissions
            ],
            'message' => 'Role created successfully'
        ], 201);
    }

    /**
     * Display the specified role.
     */
    public function show(Request $request, $id): JsonResponse
    {
        // Get all permissions for this role as 1s and 0s
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found'
            ], 404);
        }

        $rolePermissions = $role->permissions()
            ->with('permissionItem.permissionCategory')
            ->get()
            ->mapWithKeys(function ($permission) {
                $itemSlug = $permission->permissionItem->slug;
                return [
                    $itemSlug => [
                        'view' => (int) $permission->can_view,
                        'add' => (int) $permission->can_add,
                        'edit' => (int) $permission->can_edit,
                        'delete' => (int) $permission->can_delete,
                    ]
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'role_description' => $role->description,
                'is_active' => (bool) $role->is_active,
                'permissions' => $rolePermissions
            ],
            'message' => 'Role retrieved successfully'
        ]);
    }
    /**
     * Update the specified role in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'permissions' => 'required|array',
        ]);

        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found'
            ], 404);
        }

        $role->update([
            'name' => $request->name,
            'description' => $request->description,
            'is_active' => $request->is_active ?? $role->is_active,
        ]);

        // Clear existing permissions and assign new ones
        $role->permissions()->delete();

        // Get all permission items
        $permissionItems = PermissionItem::where('is_active', true)->get();
        
        // Assign permissions based on the request
        foreach ($permissionItems as $item) {
            $permissionData = $request->permissions[$item->slug] ?? [];
            
            $role->permissions()->create([
                'permission_item_id' => $item->id,
                'can_view' => $permissionData['view'] ?? 0,
                'can_add' => $permissionData['add'] ?? 0,
                'can_edit' => $permissionData['edit'] ?? 0,
                'can_delete' => $permissionData['delete'] ?? 0,
            ]);
        }

        // Get all permissions for this role as 1s and 0s
        $rolePermissions = $role->permissions()
            ->with('permissionItem.permissionCategory')
            ->get()
            ->mapWithKeys(function ($permission) {
                $itemSlug = $permission->permissionItem->slug;
                return [
                    $itemSlug => [
                        'view' => (int) $permission->can_view,
                        'add' => (int) $permission->can_add,
                        'edit' => (int) $permission->can_edit,
                        'delete' => (int) $permission->can_delete,
                    ]
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'role_description' => $role->description,
                'is_active' => (bool) $role->is_active,
                'permissions' => $rolePermissions
            ],
            'message' => 'Role updated successfully'
        ]);
    }

    /**
     * Remove the specified role from storage.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        // Check if role has any admins assigned
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found'
            ], 404);
        }

        if ($role->admins()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete role. It has assigned admins.'
            ], 422);
        }

        $role->permissions()->delete();
        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully'
        ]);
    }

    /**
     * Get permissions structure for role creation/editing.
     */
    public function getPermissionsStructure(): JsonResponse
    {
        $categories = PermissionCategory::with(['permissionItems' => function ($query) {
            $query->where('is_active', true)->orderBy('sort_order');
        }])
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
            'message' => 'Permissions structure retrieved successfully'
        ]);
    }

    /**
     * Get role with permissions for editing.
     */
    public function getRoleForEdit(Request $request, $id): JsonResponse
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found'
            ], 404);
        }

        $role->load('permissions.permissionItem.permissionCategory');
        
        $categories = PermissionCategory::with(['permissionItems' => function ($query) {
            $query->where('is_active', true)->orderBy('sort_order');
        }])
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'role' => $role,
                'permissions_structure' => $categories
            ],
            'message' => 'Role data retrieved successfully'
        ]);
    }
}

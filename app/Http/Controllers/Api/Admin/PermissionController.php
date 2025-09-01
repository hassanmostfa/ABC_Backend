<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PermissionCategory;
use App\Models\PermissionItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    /**
     * Get all permission categories with their items.
     */
    public function index(): JsonResponse
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
            'message' => 'Permissions retrieved successfully'
        ]);
    }

    /**
     * Get permission categories only.
     */
    public function getCategories(): JsonResponse
    {
        $categories = PermissionCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
            'message' => 'Permission categories retrieved successfully'
        ]);
    }

    /**
     * Get permission items for a specific category.
     */
    public function getItemsByCategory(PermissionCategory $category): JsonResponse
    {
        $items = $category->permissionItems()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'category' => $category,
                'items' => $items
            ],
            'message' => 'Permission items retrieved successfully'
        ]);
    }

    /**
     * Get all permission items.
     */
    public function getAllItems(): JsonResponse
    {
        $items = PermissionItem::with('permissionCategory')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items,
            'message' => 'All permission items retrieved successfully'
        ]);
    }

    /**
     * Store a new permission category.
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:permission_categories,slug',
            'description' => 'nullable|string',
            'sort_order' => 'integer|min:0',
        ]);

        $category = PermissionCategory::create([
            'name' => $request->name,
            'slug' => $request->slug,
            'description' => $request->description,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'data' => $category,
            'message' => 'Permission category created successfully'
        ], 201);
    }

    /**
     * Store a new permission item.
     */
    public function storeItem(Request $request): JsonResponse
    {
        $request->validate([
            'permission_category_id' => 'required|exists:permission_categories,id',
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:permission_items,slug',
            'description' => 'nullable|string',
            'sort_order' => 'integer|min:0',
        ]);

        $item = PermissionItem::create([
            'permission_category_id' => $request->permission_category_id,
            'name' => $request->name,
            'slug' => $request->slug,
            'description' => $request->description,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => true,
        ]);

        $item->load('permissionCategory');

        return response()->json([
            'success' => true,
            'data' => $item,
            'message' => 'Permission item created successfully'
        ], 201);
    }

    /**
     * Update a permission category.
     */
    public function updateCategory(Request $request, PermissionCategory $category): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:permission_categories,slug,' . $category->id,
            'description' => 'nullable|string',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $category->update([
            'name' => $request->name,
            'slug' => $request->slug,
            'description' => $request->description,
            'sort_order' => $request->sort_order ?? $category->sort_order,
            'is_active' => $request->is_active ?? $category->is_active,
        ]);

        return response()->json([
            'success' => true,
            'data' => $category,
            'message' => 'Permission category updated successfully'
        ]);
    }

    /**
     * Update a permission item.
     */
    public function updateItem(Request $request, PermissionItem $item): JsonResponse
    {
        $request->validate([
            'permission_category_id' => 'required|exists:permission_categories,id',
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:permission_items,slug,' . $item->id,
            'description' => 'nullable|string',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $item->update([
            'permission_category_id' => $request->permission_category_id,
            'name' => $request->name,
            'slug' => $request->slug,
            'description' => $request->description,
            'sort_order' => $request->sort_order ?? $item->sort_order,
            'is_active' => $request->is_active ?? $item->is_active,
        ]);

        $item->load('permissionCategory');

        return response()->json([
            'success' => true,
            'data' => $item,
            'message' => 'Permission item updated successfully'
        ]);
    }

    /**
     * Delete a permission category.
     */
    public function destroyCategory(PermissionCategory $category): JsonResponse
    {
        // Check if category has any items
        if ($category->permissionItems()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category. It has permission items.'
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permission category deleted successfully'
        ]);
    }

    /**
     * Delete a permission item.
     */
    public function destroyItem(PermissionItem $item): JsonResponse
    {
        // Check if item is used in any role permissions
        if ($item->rolePermissions()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete permission item. It is assigned to roles.'
            ], 422);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permission item deleted successfully'
        ]);
    }
}

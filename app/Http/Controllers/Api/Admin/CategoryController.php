<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Category;
use App\Repositories\CategoryRepositoryInterface;
use App\Http\Resources\Admin\CategoryResource;
use App\Traits\ManagesFileUploads;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CategoryController extends BaseApiController
{
    use ManagesFileUploads;
    
    protected $categoryRepository;

    public function __construct(CategoryRepositoryInterface $categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * Display a listing of the categories with pagination, search and filters.
     */
    public function index(Request $request): JsonResponse
    {
        // Validate filter parameters
        $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Prepare filters
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
        ];

        // Remove empty filters
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        $perPage = $request->input('per_page', 15);
        $categories = $this->categoryRepository->getAllPaginated($filters, $perPage);

        // Transform data using CategoryResource
        $transformedCategories = CategoryResource::collection($categories->items());

        // Create a custom response with pagination and filters
        $response = [
            'success' => true,
            'message' => 'Categories retrieved successfully',
            'data' => $transformedCategories,
            'pagination' => [
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
                'from' => $categories->firstItem(),
                'to' => $categories->lastItem(),
            ]
        ];

        if (!empty($filters)) {
            $response['filters'] = $filters;
        }

        return response()->json($response);
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name_en' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'is_active' => 'boolean',
        ]);

        $data = $request->only(['name_en', 'name_ar', 'is_active']);

        // Handle image upload
        if ($request->hasFile('image')) {
            $imagePath = $this->uploadFile($request->file('image'), Category::$STORAGE_DIR, 'public');
            $data['image_path'] = $imagePath;
        }

        $category = $this->categoryRepository->create($data);

        return $this->createdResponse($category, 'Category created successfully');
    }

    /**
     * Display the specified category.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $category = $this->categoryRepository->findById($id);

        if (!$category) {
            return $this->notFoundResponse('Category not found');
        }

        // Transform data using CategoryResource
        $transformedCategory = new CategoryResource($category);

        return $this->resourceResponse($transformedCategory, 'Category retrieved successfully');
    }

    /**
     * Update the specified category in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'name_en' => 'sometimes|required|string|max:255',
            'name_ar' => 'sometimes|required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'is_active' => 'sometimes|boolean',
        ]);

        $category = $this->categoryRepository->findById($id);

        if (!$category) {
            return $this->notFoundResponse('Category not found');
        }

        $data = $request->only(['name_en', 'name_ar', 'is_active']);

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($category->image_path) {
                $this->deleteFile($category->image_path, 'public');
            }
            
            // Upload new image
            $imagePath = $this->uploadFile($request->file('image'), Category::$STORAGE_DIR, 'public');
            $data['image_path'] = $imagePath;
        }

        $updatedCategory = $this->categoryRepository->update($id, $data);

        return $this->updatedResponse($updatedCategory, 'Category updated successfully');
    }

    /**
     * Remove the specified category from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $category = $this->categoryRepository->findById($id);

        if (!$category) {
            return $this->notFoundResponse('Category not found');
        }

        // Delete associated image file if exists
        if ($category->image_path) {
            $this->deleteFile($category->image_path, 'public');
        }

        $deleted = $this->categoryRepository->delete($id);

        if (!$deleted) {
            return $this->notFoundResponse('Category not found');
        }

        return $this->deletedResponse('Category deleted successfully');
    }

    /**
     * Get all active categories.
     */
    public function active(Request $request): JsonResponse
    {
        $categories = $this->categoryRepository->getActive();

        // Transform data using CategoryResource
        $transformedCategories = CategoryResource::collection($categories);

        return $this->successResponse($transformedCategories, 'Active categories retrieved successfully');
    }
}

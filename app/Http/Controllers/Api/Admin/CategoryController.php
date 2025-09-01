<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Repositories\CategoryRepositoryInterface;
use App\Http\Resources\CategoryResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CategoryController extends BaseApiController
{
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
            'image_path' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        $category = $this->categoryRepository->create($request->all());

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
            'image_path' => 'nullable|string|max:500',
            'is_active' => 'sometimes|boolean',
        ]);

        $category = $this->categoryRepository->update($id, $request->all());

        if (!$category) {
            return $this->notFoundResponse('Category not found');
        }

        return $this->updatedResponse($category, 'Category updated successfully');
    }

    /**
     * Remove the specified category from storage.
     */
    public function destroy(int $id): JsonResponse
    {
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

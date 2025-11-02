<?php

namespace App\Http\Controllers\Api\Web;

use App\Http\Controllers\Api\BaseApiController;
use App\Repositories\CategoryRepositoryInterface;
use App\Repositories\SubcategoryRepositoryInterface;
use App\Http\Resources\Web\WebCategoryResource;
use App\Http\Resources\Web\WebSubcategoryResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CategoryController extends BaseApiController
{
    protected $categoryRepository;
    protected $subcategoryRepository;

    public function __construct(
        CategoryRepositoryInterface $categoryRepository,
        SubcategoryRepositoryInterface $subcategoryRepository
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->subcategoryRepository = $subcategoryRepository;
    }

    /**
     * Get all active categories (public API)
     */
    public function getAllCategories(Request $request): JsonResponse
    {
        // Validate filter parameters
        $request->validate([
            'active_only' => 'nullable|boolean',
        ]);

        $activeOnly = $request->input('active_only', true);
        
        // Get categories
        if ($activeOnly) {
            $categories = $this->categoryRepository->getActive();
        } else {
            $categories = $this->categoryRepository->getAll();
        }

        // Load subcategories for each category
        $categories->load(['subcategories' => function ($query) use ($activeOnly) {
            if ($activeOnly) {
                $query->where('is_active', true);
            }
        }]);

        // Transform data using CategoryResource
        $transformedCategories = WebCategoryResource::collection($categories);

        return response()->json([
            'success' => true,
            'message' => 'Categories retrieved successfully',
            'data' => $transformedCategories,
            'count' => $categories->count(),
        ]);
    }

    /**
     * Get all active subcategories (public API)
     */
    public function getAllSubcategories(Request $request): JsonResponse
    {
        // Validate filter parameters
        $request->validate([
            'active_only' => 'nullable|boolean',
            'category_id' => 'nullable|integer|exists:categories,id',
        ]);

        $activeOnly = $request->input('active_only', true);
        $categoryId = $request->input('category_id');

        // Get subcategories
        if ($categoryId) {
            // Get subcategories by category
            $subcategories = $this->subcategoryRepository->getByCategory($categoryId);
        } else {
            // Get all subcategories
            if ($activeOnly) {
                $subcategories = $this->subcategoryRepository->getActive();
            } else {
                $subcategories = $this->subcategoryRepository->getAll();
            }
        }

        // Filter by active status if needed
        if ($activeOnly && !$categoryId) {
            $subcategories = $subcategories->filter(function ($subcategory) {
                return $subcategory->is_active;
            });
        }

        // Load category for each subcategory
        $subcategories->load('category');

        // Transform data using SubcategoryResource
        $transformedSubcategories = WebSubcategoryResource::collection($subcategories);

        return response()->json([
            'success' => true,
            'message' => 'Subcategories retrieved successfully',
            'data' => $transformedSubcategories,
            'count' => $subcategories->count(),
        ]);
    }

    /**
     * Get subcategories by category ID (public API)
     */
    public function getSubcategoriesByCategory(Request $request, int $categoryId): JsonResponse
    {
        // Validate that category exists
        $category = $this->categoryRepository->findById($categoryId);
        if (!$category) {
            return $this->notFoundResponse('Category not found');
        }

        // Only return active category for public API
        if (!$category->is_active) {
            return $this->notFoundResponse('Category not found');
        }

        // Validate filter parameters
        $request->validate([
            'active_only' => 'nullable|boolean',
        ]);

        $activeOnly = $request->input('active_only', true);

        // Get subcategories by category
        $subcategories = $this->subcategoryRepository->getByCategory($categoryId);

        // Filter by active status if needed
        if ($activeOnly) {
            $subcategories = $subcategories->filter(function ($subcategory) {
                return $subcategory->is_active;
            });
        }

        // Load category for each subcategory
        $subcategories->load('category');

        // Transform data using SubcategoryResource
        $transformedSubcategories = WebSubcategoryResource::collection($subcategories);

        return response()->json([
            'success' => true,
            'message' => 'Subcategories by category retrieved successfully',
            'data' => $transformedSubcategories,
            'count' => $subcategories->count(),
        ]);
    }
}

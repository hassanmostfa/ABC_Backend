<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Repositories\CategoryRepositoryInterface;
use App\Repositories\SubcategoryRepositoryInterface;
use App\Http\Resources\Admin\CategoryResource;
use App\Http\Resources\Admin\SubcategoryResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UtilsController extends BaseApiController
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
     * Get all categories without pagination
     */
    public function getCategories(Request $request): JsonResponse
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
        $transformedCategories = CategoryResource::collection($categories);

        return $this->customResponse(
            $transformedCategories,
            'Categories retrieved successfully',
            200,
        );
    }

    /**
     * Get subcategories based on category ID without pagination
     */
    public function getSubcategories(Request $request, int $categoryId): JsonResponse
    {
        // Validate that category exists
        $category = $this->categoryRepository->findById($categoryId);
        if (!$category) {
            return $this->notFoundResponse('Category not found');
        }

        // Validate filter parameters
        $request->validate([
            'active_only' => 'nullable|boolean',
        ]);

        $activeOnly = $request->input('active_only', true);

        // Get subcategories by category
        $subcategories = $this->subcategoryRepository->getByCategoryId($categoryId);

        // Filter by active status if needed
        if ($activeOnly) {
            $subcategories = $subcategories->filter(function ($subcategory) {
                return $subcategory->is_active;
            });
        }

        // Load category for each subcategory
        $subcategories->load('category');

        // Transform data using SubcategoryResource
        $transformedSubcategories = SubcategoryResource::collection($subcategories);

        return $this->customResponse(
            $transformedSubcategories,
            'Subcategories retrieved successfully',
            200,
        );
    }
}

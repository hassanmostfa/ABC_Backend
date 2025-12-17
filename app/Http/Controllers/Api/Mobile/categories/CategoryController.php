<?php

namespace App\Http\Controllers\Api\Mobile\categories;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Mobile\CategoryResource;
use App\Http\Resources\Mobile\SubcategoryResource;
use App\Repositories\CategoryRepositoryInterface;
use App\Repositories\SubcategoryRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends BaseApiController
{
    protected CategoryRepositoryInterface $categoryRepository;
    protected SubcategoryRepositoryInterface $subcategoryRepository;

    public function __construct(
        CategoryRepositoryInterface $categoryRepository,
        SubcategoryRepositoryInterface $subcategoryRepository
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->subcategoryRepository = $subcategoryRepository;
    }

    /**
     * Get all active categories
     */
    public function getAllCategories(Request $request): JsonResponse
    {
        try {
            // Get only active categories for mobile API
            $categories = $this->categoryRepository->getActive();

            // Load subcategories for each category (only active)
            $categories->load(['subcategories' => function ($query) {
                $query->where('is_active', true);
            }]);

            // Transform data using CategoryResource
            $transformedCategories = CategoryResource::collection($categories);

            return $this->successResponse(
                $transformedCategories,
                'Categories retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred while retrieving categories: ' . $e->getMessage());
        }
    }

    /**
     * Get all active subcategories
     */
    public function getAllSubcategories(Request $request): JsonResponse
    {
        try {
            // Get only active subcategories for mobile API
            $subcategories = $this->subcategoryRepository->getActive();

            // Load category for each subcategory
            $subcategories->load('category');

            // Transform data using SubcategoryResource
            $transformedSubcategories = SubcategoryResource::collection($subcategories);

            return $this->successResponse(
                $transformedSubcategories,
                'Subcategories retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred while retrieving subcategories: ' . $e->getMessage());
        }
    }

    /**
     * Get subcategories by category ID
     */
    public function getSubcategoriesByCategory(int $categoryId): JsonResponse
    {
        try {
            // Validate that category exists and is active
            $category = $this->categoryRepository->findById($categoryId);
            
            if (!$category) {
                return $this->notFoundResponse('Category not found');
            }

            // Only return if category is active
            if (!$category->is_active) {
                return $this->notFoundResponse('Category not found');
            }

            // Get active subcategories by category
            $subcategories = $this->subcategoryRepository->getByCategoryId($categoryId);
            
            // Filter only active subcategories
            $subcategories = $subcategories->filter(function ($subcategory) {
                return $subcategory->is_active;
            });

            // Load category for each subcategory
            $subcategories->load('category');

            // Transform data using SubcategoryResource
            $transformedSubcategories = SubcategoryResource::collection($subcategories);

            return $this->successResponse(
                $transformedSubcategories,
                'Subcategories retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred while retrieving subcategories: ' . $e->getMessage());
        }
    }
}


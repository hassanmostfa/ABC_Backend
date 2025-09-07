<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Repositories\SubcategoryRepositoryInterface;
use App\Http\Resources\SubcategoryResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SubcategoryController extends BaseApiController
{
    protected $subcategoryRepository;

    public function __construct(SubcategoryRepositoryInterface $subcategoryRepository)
    {
        $this->subcategoryRepository = $subcategoryRepository;
    }

    /**
     * Display a listing of the subcategories with pagination, search and filters.
     */
    public function index(Request $request): JsonResponse
    {
        // Validate filter parameters
        $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive',
            'category_id' => 'nullable|integer|exists:categories,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Prepare filters
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'category_id' => $request->input('category_id'),
        ];

        // Remove empty filters
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        $perPage = $request->input('per_page', 15);
        $subcategories = $this->subcategoryRepository->getAllPaginated($filters, $perPage);

        // Transform data using SubcategoryResource
        $transformedSubcategories = SubcategoryResource::collection($subcategories->items());

        // Create a custom response with pagination and filters
        $response = [
            'success' => true,
            'message' => 'Subcategories retrieved successfully',
            'data' => $transformedSubcategories,
            'pagination' => [
                'current_page' => $subcategories->currentPage(),
                'last_page' => $subcategories->lastPage(),
                'per_page' => $subcategories->perPage(),
                'total' => $subcategories->total(),
                'from' => $subcategories->firstItem(),
                'to' => $subcategories->lastItem(),
            ]
        ];

        if (!empty($filters)) {
            $response['filters'] = $filters;
        }

        return response()->json($response);
    }

    /**
     * Store a newly created subcategory in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => 'required|integer|exists:categories,id',
            'name_en' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'image_path' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        $subcategory = $this->subcategoryRepository->create($request->all());

        return $this->createdResponse($subcategory, 'Subcategory created successfully');
    }

    /**
     * Display the specified subcategory.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $subcategory = $this->subcategoryRepository->findById($id);

        if (!$subcategory) {
            return $this->notFoundResponse('Subcategory not found');
        }

        // Transform data using SubcategoryResource
        $transformedSubcategory = new SubcategoryResource($subcategory);

        return $this->resourceResponse($transformedSubcategory, 'Subcategory retrieved successfully');
    }

    /**
     * Update the specified subcategory in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'category_id' => 'sometimes|required|integer|exists:categories,id',
            'name_en' => 'sometimes|required|string|max:255',
            'name_ar' => 'sometimes|required|string|max:255',
            'image_path' => 'nullable|string|max:500',
            'is_active' => 'sometimes|boolean',
        ]);

        $subcategory = $this->subcategoryRepository->update($id, $request->all());

        if (!$subcategory) {
            return $this->notFoundResponse('Subcategory not found');
        }

        return $this->updatedResponse($subcategory, 'Subcategory updated successfully');
    }

    /**
     * Remove the specified subcategory from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->subcategoryRepository->delete($id);

        if (!$deleted) {
            return $this->notFoundResponse('Subcategory not found');
        }

        return $this->deletedResponse('Subcategory deleted successfully');
    }

    /**
     * Get subcategories by category ID.
     */
    public function getByCategory(Request $request, int $categoryId): JsonResponse
    {
        $subcategories = $this->subcategoryRepository->getByCategoryId($categoryId);

        // Transform data using SubcategoryResource
        $transformedSubcategories = SubcategoryResource::collection($subcategories);

        return $this->successResponse($transformedSubcategories, 'Subcategories retrieved successfully');
    }
}

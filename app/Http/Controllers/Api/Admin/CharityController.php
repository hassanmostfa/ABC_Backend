<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Repositories\CharityRepositoryInterface;
use App\Http\Resources\Admin\CharityResource;
use App\Http\Requests\Admin\CharityRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CharityController extends BaseApiController
{
    protected $charityRepository;

    public function __construct(CharityRepositoryInterface $charityRepository)
    {
        $this->charityRepository = $charityRepository;
    }

    /**
     * Display a listing of the charities with pagination and search filters.
     * 
     * Query Parameters:
     * - per_page: Number of items per page (1-100, default: 15)
     * - search: Search term to filter by name (English/Arabic), phone, or address
     */
    public function index(Request $request): JsonResponse
    {
        // Validate filter parameters
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
        ]);

        $perPage = $request->input('per_page', 15);
        $filters = $request->only(['search']);
        
        $charities = $this->charityRepository->getAllPaginated($filters, $perPage);

        // Transform data using CharityResource
        $transformedCharities = CharityResource::collection($charities->items());

        // Create a custom response with pagination
        $response = [
            'success' => true,
            'message' => 'Charities retrieved successfully',
            'data' => $transformedCharities,
            'pagination' => [
                'current_page' => $charities->currentPage(),
                'last_page' => $charities->lastPage(),
                'per_page' => $charities->perPage(),
                'total' => $charities->total(),
                'from' => $charities->firstItem(),
                'to' => $charities->lastItem(),
            ],
            'search_applied' => !empty($filters['search']) ? $filters['search'] : null,
        ];

        return response()->json($response);
    }

    /**
     * Store a newly created charity in storage.
     */
    public function store(CharityRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $charity = $this->charityRepository->create($validatedData);
            
            return $this->createdResponse($charity, 'Charity created successfully');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create charity',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified charity.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $charity = $this->charityRepository->findById($id);

        if (!$charity) {
            return $this->notFoundResponse('Charity not found');
        }

        // Transform data using CharityResource
        $transformedCharity = new CharityResource($charity);

        return $this->resourceResponse($transformedCharity, 'Charity retrieved successfully');
    }

    /**
     * Update the specified charity in storage.
     */
    public function update(CharityRequest $request, int $id): JsonResponse
    {
        $validatedData = $request->validated();
        $charity = $this->charityRepository->update($id, $validatedData);

        if (!$charity) {
            return $this->notFoundResponse('Charity not found');
        }

        return $this->updatedResponse($charity, 'Charity updated successfully');
    }

    /**
     * Remove the specified charity from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->charityRepository->delete($id);

        if (!$deleted) {
            return $this->notFoundResponse('Charity not found');
        }

        return $this->deletedResponse('Charity deleted successfully');
    }
}

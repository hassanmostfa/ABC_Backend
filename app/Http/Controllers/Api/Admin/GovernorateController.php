<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Repositories\GovernorateRepositoryInterface;
use App\Http\Resources\Admin\GovernorateResource;
use App\Http\Requests\Admin\GovernorateRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GovernorateController extends BaseApiController
{
    protected $governorateRepository;

    public function __construct(GovernorateRepositoryInterface $governorateRepository)
    {
        $this->governorateRepository = $governorateRepository;
    }

    /**
     * Display a listing of the governorates with pagination and filters.
     */
    public function index(Request $request): JsonResponse
    {
        // Validate filter parameters
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
            'country_id' => 'nullable|integer|exists:countries,id',
            'get_all' => 'nullable|boolean',
        ]);

        $getAll = $request->boolean('get_all', false);
        
        if ($getAll) {
            // Return all governorates without pagination
            $filters = [
                'search' => $request->input('search'),
                'country_id' => $request->input('country_id'),
            ];
            
            // Remove null values from filters
            $filters = array_filter($filters, function($value) {
                return $value !== null && $value !== '';
            });
            
            $governorates = $this->governorateRepository->getAll($filters);
            $transformedGovernorates = GovernorateResource::collection($governorates);
            
            return response()->json([
                'success' => true,
                'message' => 'Governorates retrieved successfully',
                'data' => $transformedGovernorates,
                'total' => $governorates->count(),
            ]);
        }

        $perPage = $request->input('per_page', 15);
        $filters = [
            'search' => $request->input('search'),
            'country_id' => $request->input('country_id'),
        ];
        
        // Remove null values from filters
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });
        
        $governorates = $this->governorateRepository->getAllPaginated($filters, $perPage);

        // Transform data using GovernorateResource
        $transformedGovernorates = GovernorateResource::collection($governorates->items());

        // Create a custom response with pagination
        $response = [
            'success' => true,
            'message' => 'Governorates retrieved successfully',
            'data' => $transformedGovernorates,
            'pagination' => [
                'current_page' => $governorates->currentPage(),
                'last_page' => $governorates->lastPage(),
                'per_page' => $governorates->perPage(),
                'total' => $governorates->total(),
                'from' => $governorates->firstItem(),
                'to' => $governorates->lastItem(),
            ]
        ];

        return response()->json($response);
    }

    /**
     * Store a newly created governorate in storage.
     */
    public function store(GovernorateRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $governorate = $this->governorateRepository->create($validatedData);
            
            // Log activity
            logAdminActivity('created', 'Governorate', $governorate->id);
            
            return $this->createdResponse($governorate, 'Governorate created successfully');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create governorate',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified governorate.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $governorate = $this->governorateRepository->findById($id);

        if (!$governorate) {
            return $this->notFoundResponse('Governorate not found');
        }

        // Transform data using GovernorateResource
        $transformedGovernorate = new GovernorateResource($governorate);

        return $this->resourceResponse($transformedGovernorate, 'Governorate retrieved successfully');
    }

    /**
     * Update the specified governorate in storage.
     */
    public function update(GovernorateRequest $request, int $id): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $governorate = $this->governorateRepository->update($id, $validatedData);

            if (!$governorate) {
                return $this->notFoundResponse('Governorate not found');
            }

            // Log activity
            logAdminActivity('updated', 'Governorate', $id);

            return $this->updatedResponse($governorate, 'Governorate updated successfully');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update governorate',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified governorate from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->governorateRepository->delete($id);

        if (!$deleted) {
            return $this->notFoundResponse('Governorate not found');
        }

        // Log activity
        logAdminActivity('deleted', 'Governorate', $id);

        return $this->deletedResponse('Governorate deleted successfully');
    }

    /**
     * Get governorates by country ID.
     */
    public function getByCountry(Request $request, int $countryId): JsonResponse
    {
        $governorates = $this->governorateRepository->getByCountry($countryId);
        $transformedGovernorates = GovernorateResource::collection($governorates);

        return $this->resourceResponse($transformedGovernorates, 'Governorates retrieved successfully');
    }
}

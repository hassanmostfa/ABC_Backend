<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Repositories\AreaRepositoryInterface;
use App\Http\Resources\Admin\AreaResource;
use App\Http\Requests\Admin\AreaRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AreaController extends BaseApiController
{
    protected $areaRepository;

    public function __construct(AreaRepositoryInterface $areaRepository)
    {
        $this->areaRepository = $areaRepository;
    }

    /**
     * Display a listing of the areas with pagination and filters.
     */
    public function index(Request $request): JsonResponse
    {
        // Validate filter parameters
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
            'governorate_id' => 'nullable|integer|exists:governorates,id',
            'country_id' => 'nullable|integer|exists:countries,id',
        ]);

        $perPage = $request->input('per_page', 15);
        $filters = [
            'search' => $request->input('search'),
            'governorate_id' => $request->input('governorate_id'),
            'country_id' => $request->input('country_id'),
        ];
        
        // Remove null values from filters
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });
        
        $areas = $this->areaRepository->getAllPaginated($filters, $perPage);

        // Transform data using AreaResource
        $transformedAreas = AreaResource::collection($areas->items());

        // Create a custom response with pagination
        $response = [
            'success' => true,
            'message' => 'Areas retrieved successfully',
            'data' => $transformedAreas,
            'pagination' => [
                'current_page' => $areas->currentPage(),
                'last_page' => $areas->lastPage(),
                'per_page' => $areas->perPage(),
                'total' => $areas->total(),
                'from' => $areas->firstItem(),
                'to' => $areas->lastItem(),
            ]
        ];

        return response()->json($response);
    }

    /**
     * Store a newly created area in storage.
     */
    public function store(AreaRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $area = $this->areaRepository->create($validatedData);
            
            // Log activity
            logAdminActivity('created', 'Area', $area->id);
            
            return $this->createdResponse($area, 'Area created successfully');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create area',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified area.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $area = $this->areaRepository->findById($id);

        if (!$area) {
            return $this->notFoundResponse('Area not found');
        }

        // Transform data using AreaResource
        $transformedArea = new AreaResource($area);

        return $this->resourceResponse($transformedArea, 'Area retrieved successfully');
    }

    /**
     * Update the specified area in storage.
     */
    public function update(AreaRequest $request, int $id): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $area = $this->areaRepository->update($id, $validatedData);

            if (!$area) {
                return $this->notFoundResponse('Area not found');
            }

            // Log activity
            logAdminActivity('updated', 'Area', $id);

            return $this->updatedResponse($area, 'Area updated successfully');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update area',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified area from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->areaRepository->delete($id);

        if (!$deleted) {
            return $this->notFoundResponse('Area not found');
        }

        // Log activity
        logAdminActivity('deleted', 'Area', $id);

        return $this->deletedResponse('Area deleted successfully');
    }

    /**
     * Get areas by governorate ID.
     */
    public function getByGovernorate(Request $request, int $governorateId): JsonResponse
    {
        $areas = $this->areaRepository->getByGovernorate($governorateId);
        $transformedAreas = AreaResource::collection($areas);

        return $this->resourceResponse($transformedAreas, 'Areas retrieved successfully');
    }

    /**
     * Get areas by country ID.
     */
    public function getByCountry(Request $request, int $countryId): JsonResponse
    {
        $areas = $this->areaRepository->getByCountry($countryId);
        $transformedAreas = AreaResource::collection($areas);

        return $this->resourceResponse($transformedAreas, 'Areas retrieved successfully');
    }
}

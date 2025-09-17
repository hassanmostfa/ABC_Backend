<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Repositories\CountryRepositoryInterface;
use App\Http\Resources\CountryResource;
use App\Http\Requests\CountryRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CountryController extends BaseApiController
{
    protected $countryRepository;

    public function __construct(CountryRepositoryInterface $countryRepository)
    {
        $this->countryRepository = $countryRepository;
    }

    /**
     * Display a listing of the countries with pagination and filters.
     */
    public function index(Request $request): JsonResponse
    {
        // Validate filter parameters
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
        ]);

        $perPage = $request->input('per_page', 15);
        $filters = [
            'search' => $request->input('search'),
        ];
        
        // Remove null values from filters
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });
        
        $countries = $this->countryRepository->getAllPaginated($filters, $perPage);

        // Transform data using CountryResource
        $transformedCountries = CountryResource::collection($countries->items());

        // Create a custom response with pagination
        $response = [
            'success' => true,
            'message' => 'Countries retrieved successfully',
            'data' => $transformedCountries,
            'pagination' => [
                'current_page' => $countries->currentPage(),
                'last_page' => $countries->lastPage(),
                'per_page' => $countries->perPage(),
                'total' => $countries->total(),
                'from' => $countries->firstItem(),
                'to' => $countries->lastItem(),
            ]
        ];

        return response()->json($response);
    }

    /**
     * Store a newly created country in storage.
     */
    public function store(CountryRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $country = $this->countryRepository->create($validatedData);
            
            return $this->createdResponse($country, 'Country created successfully');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create country',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified country.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $country = $this->countryRepository->findById($id);

        if (!$country) {
            return $this->notFoundResponse('Country not found');
        }

        // Transform data using CountryResource
        $transformedCountry = new CountryResource($country);

        return $this->resourceResponse($transformedCountry, 'Country retrieved successfully');
    }

    /**
     * Update the specified country in storage.
     */
    public function update(CountryRequest $request, int $id): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $country = $this->countryRepository->update($id, $validatedData);

            if (!$country) {
                return $this->notFoundResponse('Country not found');
            }

            return $this->updatedResponse($country, 'Country updated successfully');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update country',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified country from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->countryRepository->delete($id);

        if (!$deleted) {
            return $this->notFoundResponse('Country not found');
        }

        return $this->deletedResponse('Country deleted successfully');
    }

}

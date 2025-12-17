<?php

namespace App\Http\Controllers\Api\Mobile\offers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Mobile\OfferListResource;
use App\Http\Resources\Mobile\OfferResource;
use App\Repositories\OfferRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfferController extends BaseApiController
{
    protected OfferRepositoryInterface $offerRepository;

    public function __construct(OfferRepositoryInterface $offerRepository)
    {
        $this->offerRepository = $offerRepository;
    }

    /**
     * Display a listing of active offers with pagination and filters.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Validate filter parameters
            $request->validate([
                'per_page' => 'nullable|integer|min:1|max:100',
                'type' => 'nullable|in:normal,charity',
                'category_id' => 'nullable|integer|exists:categories,id',
                'search' => 'nullable|string|max:1000',
            ]);

            $perPage = $request->input('per_page', 15);
            $filters = [
                'active_only' => true, // Only get active offers for mobile API
                'type' => $request->input('type'),
                'category_id' => $request->input('category_id'),
                'search' => $request->input('search'),
            ];
            
            // Remove null and empty values from filters (but keep active_only)
            $filtered = [];
            foreach ($filters as $key => $value) {
                if ($key === 'active_only' || ($value !== null && $value !== '')) {
                    $filtered[$key] = $value;
                }
            }
            $filters = $filtered;
            
            // Get offers using repository
            $offers = $this->offerRepository->getAllPaginated($filters, $perPage);

            // Transform data using OfferListResource
            $transformedOffers = OfferListResource::collection($offers->items());

            // Create a custom response with pagination and filters
            $response = [
                'success' => true,
                'message' => 'Offers retrieved successfully',
                'data' => $transformedOffers,
                'pagination' => [
                    'current_page' => $offers->currentPage(),
                    'last_page' => $offers->lastPage(),
                    'per_page' => $offers->perPage(),
                    'total' => $offers->total(),
                    'from' => $offers->firstItem(),
                    'to' => $offers->lastItem(),
                ]
            ];

            if (!empty($filters)) {
                $response['filters'] = $filters;
            }

            return response()->json($response);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred while retrieving offers: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified offer.
     */
    public function show(int $id): JsonResponse
    {
        try {
            // Find offer using repository
            $offer = $this->offerRepository->findById($id);

            if (!$offer) {
                return $this->notFoundResponse('Offer not found');
            }

            return $this->successResponse(
                new OfferResource($offer),
                'Offer retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred while retrieving the offer: ' . $e->getMessage());
        }
    }
}


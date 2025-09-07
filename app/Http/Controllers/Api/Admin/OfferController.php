<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Repositories\OfferRepositoryInterface;
use App\Http\Resources\OfferResource;
use App\Http\Requests\OfferRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OfferController extends BaseApiController
{
    protected $offerRepository;

    public function __construct(OfferRepositoryInterface $offerRepository)
    {
        $this->offerRepository = $offerRepository;
    }

    /**
     * Display a listing of the offers with pagination and filters.
     */
    public function index(Request $request): JsonResponse
    {
        // Validate filter parameters
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = $request->input('per_page', 15);
        $offers = $this->offerRepository->getAllPaginated([], $perPage);

        // Transform data using OfferResource
        $transformedOffers = OfferResource::collection($offers->items());

        // Create a custom response with pagination
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

        return response()->json($response);
    }

    /**
     * Store a newly created offer in storage.
     */
    public function store(OfferRequest $request): JsonResponse
    {
        try {
            $offer = $this->offerRepository->create($request->validated());
            
            // Load relationships for response
            $offer->load(['targetProduct', 'giftProduct']);
            
            return $this->createdResponse($offer, 'Offer created successfully');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create offer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified offer.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $offer = $this->offerRepository->findById($id);

        if (!$offer) {
            return $this->notFoundResponse('Offer not found');
        }

        // Transform data using OfferResource
        $transformedOffer = new OfferResource($offer);

        return $this->resourceResponse($transformedOffer, 'Offer retrieved successfully');
    }

    /**
     * Update the specified offer in storage.
     */
    public function update(OfferRequest $request, int $id): JsonResponse
    {
        $offer = $this->offerRepository->update($id, $request->validated());

        if (!$offer) {
            return $this->notFoundResponse('Offer not found');
        }

        return $this->updatedResponse($offer, 'Offer updated successfully');
    }

    /**
     * Remove the specified offer from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->offerRepository->delete($id);

        if (!$deleted) {
            return $this->notFoundResponse('Offer not found');
        }

        return $this->deletedResponse('Offer deleted successfully');
    }
}

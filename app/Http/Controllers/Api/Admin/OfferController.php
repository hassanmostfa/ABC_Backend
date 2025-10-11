<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Repositories\OfferRepositoryInterface;
use App\Http\Resources\Admin\OfferResource;
use App\Http\Requests\Admin\OfferRequest;
use App\Traits\ManagesFileUploads;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Offer;

class OfferController extends BaseApiController
{
    use ManagesFileUploads;
    
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
            'type' => 'nullable|string|max:255',
        ]);

        $perPage = $request->input('per_page', 15);
        $filters = [
            'type' => $request->input('type'),
        ];
        
        // Remove null values from filters
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });
        
        $offers = $this->offerRepository->getAllPaginated($filters, $perPage);

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
            $validatedData = $request->validated();
            $conditions = $validatedData['conditions'] ?? [];
            $rewards = $validatedData['rewards'] ?? [];
            
            // Handle image upload
            if ($request->hasFile('image')) {
                $imagePath = $this->uploadFile($request->file('image'), Offer::$STORAGE_DIR, 'public');
                $validatedData['image'] = $imagePath;
            }
            
            // Remove conditions and rewards from offer data
            unset($validatedData['conditions'], $validatedData['rewards']);
            
            $offer = $this->offerRepository->create($validatedData);
            
            // Create conditions
            foreach ($conditions as $conditionData) {
                $conditionData['offer_id'] = $offer->id;
                $offer->conditions()->create($conditionData);
            }
            
            // Create rewards
            foreach ($rewards as $rewardData) {
                $rewardData['offer_id'] = $offer->id;
                $offer->rewards()->create($rewardData);
            }
            
            // Reload the offer with all relationships for response
            $offer = $this->offerRepository->findById($offer->id);
            
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
        $validatedData = $request->validated();
        $conditions = $validatedData['conditions'] ?? [];
        $rewards = $validatedData['rewards'] ?? [];
        
        // Get the existing offer to check for old image
        $existingOffer = $this->offerRepository->findById($id);
        
        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if it exists
            if ($existingOffer && $existingOffer->image && $existingOffer->deleteImage()) {
                $existingOffer->deleteImage();
            }
            
            $imagePath = $this->uploadFile($request->file('image'), Offer::$STORAGE_DIR, 'public');
            $validatedData['image'] = $imagePath;
        }
        
        // Remove conditions and rewards from offer data
        unset($validatedData['conditions'], $validatedData['rewards']);
        
        $offer = $this->offerRepository->update($id, $validatedData);

        if (!$offer) {
            return $this->notFoundResponse('Offer not found');
        }

        // Update conditions - delete existing and create new ones
        $offer->conditions()->delete();
        foreach ($conditions as $conditionData) {
            $conditionData['offer_id'] = $offer->id;
            $offer->conditions()->create($conditionData);
        }
        
        // Update rewards - delete existing and create new ones
        $offer->rewards()->delete();
        foreach ($rewards as $rewardData) {
            $rewardData['offer_id'] = $offer->id;
            $offer->rewards()->create($rewardData);
        }

        // Reload the offer with all relationships for response
        $offer = $this->offerRepository->findById($offer->id);

        return $this->updatedResponse($offer, 'Offer updated successfully');
    }

    /**
     * Remove the specified offer from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        // Get the offer to check for image before deletion
        $offer = $this->offerRepository->findById($id);
        
        if (!$offer) {
            return $this->notFoundResponse('Offer not found');
        }
        
        // Delete the image file if it exists
        if ($offer->image && $offer->deleteImage()) {
            $offer->deleteImage();
        }
        
        $deleted = $this->offerRepository->delete($id);

        if (!$deleted) {
            return $this->notFoundResponse('Offer not found');
        }

        return $this->deletedResponse('Offer deleted successfully');
    }
}

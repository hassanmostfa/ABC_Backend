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

    /**
     * Get offers related to a product variant (where variant appears in conditions or rewards).
     */
    public function getByProductVariant(Request $request, int $productVariantId): JsonResponse
    {
        try {
            $request->validate([
                'active_only' => 'nullable|boolean',
                'debug' => 'nullable|boolean',
            ]);

            $activeOnly = $request->boolean('active_only', true);
            $debug = $request->boolean('debug', false);

            $offers = $this->offerRepository->getByProductVariantId($productVariantId, $activeOnly);

            if ($debug) {
                $variant = \App\Models\ProductVariant::with('product')->find($productVariantId);
                $conditionsCount = \App\Models\OfferCondition::where('product_variant_id', $productVariantId)
                    ->orWhere('product_id', $variant?->product_id)->count();
                $rewardsCount = \App\Models\OfferReward::where('product_variant_id', $productVariantId)
                    ->orWhere('product_id', $variant?->product_id)->count();

                return response()->json([
                    'success' => true,
                    'message' => 'Debug info',
                    'data' => $offers->isEmpty() ? [] : OfferListResource::collection($offers),
                    'debug' => [
                        'product_variant_id' => $productVariantId,
                        'variant_exists' => (bool) $variant,
                        'variant_product_id' => $variant?->product_id,
                        'active_only' => $activeOnly,
                        'conditions_matching_count' => $conditionsCount,
                        'rewards_matching_count' => $rewardsCount,
                        'offers_found' => $offers->count(),
                    ],
                ]);
            }

            $transformedOffers = OfferListResource::collection($offers);

            return $this->successResponse(
                $transformedOffers,
                'Offers retrieved successfully'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred while retrieving offers: ' . $e->getMessage());
        }
    }
}


<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\WalletChargeOfferRequest;
use App\Http\Resources\Admin\WalletChargeOfferResource;
use App\Repositories\WalletChargeOffers\WalletChargeOfferRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletChargeOfferController extends BaseApiController
{
    public function __construct(
        protected WalletChargeOfferRepositoryInterface $walletChargeOfferRepository
    ) {}

    /**
     * Display a listing of wallet charge offers.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'is_active' => 'nullable|in:true,false,1,0',
            'sort_by' => 'nullable|in:sort_order,charge_amount,get_amount,created_at,updated_at,id',
            'sort_order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $filters = [
            'search' => $request->input('search'),
            'is_active' => $request->input('is_active'),
            'sort_by' => $request->input('sort_by', 'sort_order'),
            'sort_order' => $request->input('sort_order', 'asc'),
        ];

        $perPage = (int) $request->input('per_page', 15);
        $offers = $this->walletChargeOfferRepository->getAllPaginated($filters, $perPage);

        $response = [
            'success' => true,
            'message' => 'Wallet charge offers retrieved successfully',
            'data' => WalletChargeOfferResource::collection($offers->items()),
            'pagination' => [
                'current_page' => $offers->currentPage(),
                'last_page' => $offers->lastPage(),
                'per_page' => $offers->perPage(),
                'total' => $offers->total(),
                'from' => $offers->firstItem(),
                'to' => $offers->lastItem(),
            ],
        ];

        $appliedFilters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        if (!empty($appliedFilters)) {
            $response['filters'] = $appliedFilters;
        }

        return response()->json($response);
    }

    /**
     * Store a newly created wallet charge offer.
     */
    public function store(WalletChargeOfferRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $offer = $this->walletChargeOfferRepository->create($data);

        logAdminActivity('created', 'WalletChargeOffer', $offer->id);

        return $this->createdResponse(new WalletChargeOfferResource($offer), 'Wallet charge offer created successfully');
    }

    /**
     * Display the specified wallet charge offer.
     */
    public function show(int $id): JsonResponse
    {
        $offer = $this->walletChargeOfferRepository->findById($id);

        if (!$offer) {
            return $this->notFoundResponse('Wallet charge offer not found');
        }

        return $this->resourceResponse(new WalletChargeOfferResource($offer), 'Wallet charge offer retrieved successfully');
    }

    /**
     * Update the specified wallet charge offer.
     */
    public function update(WalletChargeOfferRequest $request, int $id): JsonResponse
    {
        $offer = $this->walletChargeOfferRepository->update($id, $request->validated());

        if (!$offer) {
            return $this->notFoundResponse('Wallet charge offer not found');
        }

        logAdminActivity('updated', 'WalletChargeOffer', $offer->id);

        return $this->updatedResponse(new WalletChargeOfferResource($offer), 'Wallet charge offer updated successfully');
    }

    /**
     * Toggle wallet charge offer active status.
     */
    public function toggleActive(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'is_active' => 'nullable|boolean',
        ]);

        $offer = $this->walletChargeOfferRepository->findById($id);
        if (!$offer) {
            return $this->notFoundResponse('Wallet charge offer not found');
        }

        $newStatus = $request->has('is_active')
            ? (bool) $request->input('is_active')
            : !$offer->is_active;

        $updated = $this->walletChargeOfferRepository->update($id, [
            'is_active' => $newStatus,
        ]);

        if (!$updated) {
            return $this->errorResponse('Failed to update wallet charge offer status', 500);
        }

        logAdminActivity($newStatus ? 'activated' : 'deactivated', 'WalletChargeOffer', $id);

        return $this->updatedResponse(
            new WalletChargeOfferResource($updated),
            $newStatus ? 'Wallet charge offer activated successfully' : 'Wallet charge offer deactivated successfully'
        );
    }

    /**
     * Remove the specified wallet charge offer.
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->walletChargeOfferRepository->delete($id);

        if (!$deleted) {
            return $this->notFoundResponse('Wallet charge offer not found');
        }

        logAdminActivity('deleted', 'WalletChargeOffer', $id);

        return $this->deletedResponse('Wallet charge offer deleted successfully');
    }
}

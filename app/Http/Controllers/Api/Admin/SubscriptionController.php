<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Subscription;
use App\Http\Requests\Admin\SubscriptionRequest;
use App\Http\Resources\Admin\SubscriptionResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends BaseApiController
{
    /**
     * Display a listing of subscriptions with pagination and filters.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 15);
            
            $query = Subscription::with([
                'offer.conditions.product',
                'offer.conditions.productVariant',
                'offer.rewards.product',
                'offer.rewards.productVariant',
                'offer.charity'
            ]);

            // Filter by offer_id
            if ($request->has('offer_id') && $request->offer_id) {
                $query->where('offer_id', $request->offer_id);
            }

            // Filter by period
            if ($request->has('period') && $request->period) {
                $query->where('period', $request->period);
            }

            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Search by offer title
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->whereHas('offer', function ($q) use ($search) {
                    $q->where('title_en', 'LIKE', "%{$search}%")
                      ->orWhere('title_ar', 'LIKE', "%{$search}%");
                });
            }

            $query->orderBy('created_at', 'desc');
            
            $subscriptions = $query->paginate($perPage);

            $transformedSubscriptions = SubscriptionResource::collection($subscriptions->items());

            $response = [
                'success' => true,
                'message' => 'Subscriptions retrieved successfully',
                'data' => $transformedSubscriptions,
                'pagination' => [
                    'current_page' => $subscriptions->currentPage(),
                    'last_page' => $subscriptions->lastPage(),
                    'per_page' => $subscriptions->perPage(),
                    'total' => $subscriptions->total(),
                    'from' => $subscriptions->firstItem(),
                    'to' => $subscriptions->lastItem(),
                ]
            ];

            return response()->json($response);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred while retrieving subscriptions: ' . $e->getMessage());
        }
    }

    /**
     * Store a newly created subscription in storage.
     */
    public function store(SubscriptionRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            
            $subscription = Subscription::create($validatedData);
            $subscription->load([
                'offer.conditions.product',
                'offer.conditions.productVariant',
                'offer.rewards.product',
                'offer.rewards.productVariant',
                'offer.charity'
            ]);

            logAdminActivity('created', 'Subscription', $subscription->id);

            return $this->createdResponse(
                new SubscriptionResource($subscription),
                'Subscription created successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to create subscription: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified subscription.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $subscription = Subscription::with([
                'offer.conditions.product',
                'offer.conditions.productVariant',
                'offer.rewards.product',
                'offer.rewards.productVariant',
                'offer.charity'
            ])->find($id);

            if (!$subscription) {
                return $this->notFoundResponse('Subscription not found');
            }

            return $this->successResponse(
                new SubscriptionResource($subscription),
                'Subscription retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred while retrieving the subscription: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified subscription in storage.
     */
    public function update(SubscriptionRequest $request, int $id): JsonResponse
    {
        try {
            $subscription = Subscription::find($id);

            if (!$subscription) {
                return $this->notFoundResponse('Subscription not found');
            }

            $validatedData = $request->validated();
            $subscription->update($validatedData);
            $subscription->load([
                'offer.conditions.product',
                'offer.conditions.productVariant',
                'offer.rewards.product',
                'offer.rewards.productVariant',
                'offer.charity'
            ]);

            logAdminActivity('updated', 'Subscription', $subscription->id);

            return $this->updatedResponse(
                new SubscriptionResource($subscription),
                'Subscription updated successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to update subscription: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified subscription from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $subscription = Subscription::find($id);

            if (!$subscription) {
                return $this->notFoundResponse('Subscription not found');
            }

            $subscription->delete();

            logAdminActivity('deleted', 'Subscription', $id);

            return $this->deletedResponse('Subscription deleted successfully');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to delete subscription: ' . $e->getMessage());
        }
    }

    /**
     * Toggle the is_active status of the specified subscription.
     */
    public function toggleActive(int $id): JsonResponse
    {
        try {
            $subscription = Subscription::with([
                'offer.conditions.product',
                'offer.conditions.productVariant',
                'offer.rewards.product',
                'offer.rewards.productVariant',
                'offer.charity'
            ])->find($id);

            if (!$subscription) {
                return $this->notFoundResponse('Subscription not found');
            }

            // Toggle the is_active status
            $subscription->is_active = !$subscription->is_active;
            $subscription->save();

            logAdminActivity('toggled_active', 'Subscription', $subscription->id, [
                'is_active' => $subscription->is_active
            ]);

            return $this->successResponse(
                new SubscriptionResource($subscription),
                'Subscription status updated successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to toggle subscription status: ' . $e->getMessage());
        }
    }

    /**
     * Get available subscription periods.
     */
    public function periods(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Available subscription periods retrieved successfully',
            'data' => [
                ['value' => '3', 'label_en' => '3 Months', 'label_ar' => '3 أشهر'],
                ['value' => '6', 'label_en' => '6 Months', 'label_ar' => '6 أشهر'],
                ['value' => '12', 'label_en' => '12 Months', 'label_ar' => '12 شهر'],
            ]
        ]);
    }
}

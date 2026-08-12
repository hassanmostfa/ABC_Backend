<?php

namespace App\Http\Controllers\Api\Mobile\subscriptions;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Subscription;
use App\Models\CustomerSubscription;
use App\Http\Requests\Mobile\SubscriptionPurchaseRequest;
use App\Http\Resources\Mobile\SubscriptionResource;
use App\Http\Resources\Mobile\CustomerSubscriptionResource;
use App\Services\SubscriptionPurchaseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends BaseApiController
{
    protected SubscriptionPurchaseService $purchaseService;

    public function __construct(SubscriptionPurchaseService $purchaseService)
    {
        $this->purchaseService = $purchaseService;
    }

    /**
     * Get all active subscriptions (available for purchase)
     * For both Mobile & Web
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $subscriptions = Subscription::with('offer')
                ->active()
                ->orderBy('period')
                ->get();

            return $this->successResponse(
                SubscriptionResource::collection($subscriptions),
                'Subscriptions retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Get subscription details by ID
     * For both Mobile & Web
     */
    public function show(int $id): JsonResponse
    {
        try {
            $subscription = Subscription::with([
                'offer.conditions.product',
                'offer.conditions.productVariant',
                'offer.rewards.product',
                'offer.rewards.productVariant'
            ])->active()->findOrFail($id);

            return $this->successResponse(
                new SubscriptionResource($subscription),
                'Subscription retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->notFoundResponse('Subscription not found');
        }
    }

    /**
     * Purchase a subscription
     * For both Mobile & Web
     */
    public function purchase(SubscriptionPurchaseRequest $request): JsonResponse
    {
        try {
            $customer = $request->user();

            // Check if customer account is completed
            if (!$customer->is_account_completed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please complete your account information first',
                ], 422);
            }

            $customerSubscription = $this->purchaseService->purchase($customer, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Subscription purchased successfully',
                'data' => new CustomerSubscriptionResource($customerSubscription),
            ], 201);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to purchase subscription: ' . $e->getMessage());
        }
    }

    /**
     * Get customer's subscriptions
     * For both Mobile & Web
     */
    public function mySubscriptions(Request $request): JsonResponse
    {
        try {
            $customer = $request->user();

            $subscriptions = CustomerSubscription::with([
                'subscription.offer',
                'orders'
            ])
                ->where('customer_id', $customer->id)
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            return response()->json([
                'success' => true,
                'message' => 'Subscriptions retrieved successfully',
                'data' => CustomerSubscriptionResource::collection($subscriptions->items()),
                'pagination' => [
                    'current_page' => $subscriptions->currentPage(),
                    'last_page' => $subscriptions->lastPage(),
                    'per_page' => $subscriptions->perPage(),
                    'total' => $subscriptions->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Get specific customer subscription details
     * For both Mobile & Web
     */
    public function mySubscriptionDetails(Request $request, int $id): JsonResponse
    {
        try {
            $customer = $request->user();

            $subscription = CustomerSubscription::with([
                'subscription.offer',
                'orders.items.product',
                'orders.items.productVariant'
            ])
                ->where('customer_id', $customer->id)
                ->findOrFail($id);

            return $this->successResponse(
                new CustomerSubscriptionResource($subscription),
                'Subscription details retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->notFoundResponse('Subscription not found');
        }
    }
}

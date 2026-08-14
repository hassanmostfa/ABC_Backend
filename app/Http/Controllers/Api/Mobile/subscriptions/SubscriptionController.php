<?php

namespace App\Http\Controllers\Api\Mobile\subscriptions;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Customer;
use App\Models\Subscription;
use App\Models\CustomerSubscription;
use App\Http\Requests\Mobile\SubscriptionPurchaseRequest;
use App\Http\Resources\Mobile\SubscriptionResource;
use App\Http\Resources\Mobile\CustomerSubscriptionResource;
use App\Http\Resources\Mobile\SubscriptionCheckoutResource;
use App\Services\SubscriptionPurchaseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

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
            $subscriptions = Subscription::with([
                'offer.conditions.product',
                'offer.conditions.productVariant',
                'offer.rewards.product',
                'offer.rewards.productVariant',
                'offer.charity',
            ])
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
                'offer.rewards.productVariant',
                'offer.charity',
            ])->active()->findOrFail($id);

            return $this->successResponse(
                (new SubscriptionResource($subscription))->withFullOffer(),
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
            $customer = $this->resolveAuthenticatedCustomer();

            if (!$customer) {
                return $this->unauthorizedResponse(
                    'No authenticated customer found. This endpoint requires a customer token, not an admin token.'
                );
            }

            $result = $this->purchaseService->purchase($customer, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Complete payment to create the subscription.',
                'data' => new SubscriptionCheckoutResource($result['checkout']),
                'payment_link' => $result['payment_link'],
                'is_checkout' => true,
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
            $customer = $this->resolveAuthenticatedCustomer();

            if (!$customer) {
                return $this->unauthorizedResponse(
                    'No authenticated customer found. This endpoint requires a customer token, not an admin token.'
                );
            }

            $subscriptions = CustomerSubscription::with([
                'subscription.offer',
                'orders',
                'invoice',
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
            $customer = $this->resolveAuthenticatedCustomer();

            if (!$customer) {
                return $this->unauthorizedResponse(
                    'No authenticated customer found. This endpoint requires a customer token, not an admin token.'
                );
            }

            $subscription = CustomerSubscription::with([
                'subscription.offer',
                'orders.items.product',
                'orders.items.productVariant',
                'invoice',
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

    private function resolveAuthenticatedCustomer(): ?Customer
    {
        $user = Auth::guard('sanctum')->user();

        return $user instanceof Customer ? $user : null;
    }
}

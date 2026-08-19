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
use App\Http\Resources\Mobile\SubscriptionOrderResource;
use App\Models\SubscriptionOrder;
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
        $request->validate([
            'category_id' => 'nullable|integer|min:1',
            'category' => 'nullable|integer|min:1',
            'subcategory_id' => 'nullable|integer|min:1',
            'subcategory' => 'nullable|integer|min:1',
        ]);

        try {
            $categoryId = $request->input('category_id') ?? $request->input('category');
            $subcategoryId = $request->input('subcategory_id') ?? $request->input('subcategory');

            $query = Subscription::with([
                'offer.conditions.product',
                'offer.conditions.productVariant',
                'offer.rewards.product',
                'offer.rewards.productVariant',
                'offer.charity',
            ])->active();

            // Filter by category_id (through offer's conditions or rewards products)
            if ($categoryId !== null && is_numeric($categoryId)) {
                $categoryId = (int) $categoryId;
                $query->whereHas('offer', function ($offerQuery) use ($categoryId) {
                    $offerQuery->where(function ($q) use ($categoryId) {
                        $q->whereHas('conditions.product', function ($productQuery) use ($categoryId) {
                            $productQuery->where('category_id', $categoryId);
                        })
                        ->orWhereHas('rewards.product', function ($productQuery) use ($categoryId) {
                            $productQuery->where('category_id', $categoryId);
                        });
                    });
                });
            }

            // Filter by subcategory_id (through offer's conditions or rewards products)
            if ($subcategoryId !== null && is_numeric($subcategoryId)) {
                $subcategoryId = (int) $subcategoryId;
                $query->whereHas('offer', function ($offerQuery) use ($subcategoryId) {
                    $offerQuery->where(function ($q) use ($subcategoryId) {
                        $q->whereHas('conditions.product', function ($productQuery) use ($subcategoryId) {
                            $productQuery->where('subcategory_id', $subcategoryId);
                        })
                        ->orWhereHas('rewards.product', function ($productQuery) use ($subcategoryId) {
                            $productQuery->where('subcategory_id', $subcategoryId);
                        });
                    });
                });
            }

            $subscriptions = $query->orderBy('period')->get();

            $response = [
                'success' => true,
                'message' => 'Subscriptions retrieved successfully',
                'data' => SubscriptionResource::collection($subscriptions),
            ];

            // Add filters to response if any were applied
            $appliedFilters = [];
            if ($categoryId !== null) {
                $appliedFilters['category_id'] = $categoryId;
            }
            if ($subcategoryId !== null) {
                $appliedFilters['subcategory_id'] = $subcategoryId;
            }
            if (!empty($appliedFilters)) {
                $response['filters'] = $appliedFilters;
            }

            return response()->json($response);
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

            if (empty($result['is_checkout'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'Subscription purchased successfully.',
                    'data' => (new CustomerSubscriptionResource($result['customer_subscription']))->withOrders(),
                    'payment_link' => null,
                    'is_checkout' => false,
                ], 201);
            }

            return response()->json([
                'success' => true,
                'message' => 'Complete payment to create the subscription.',
                'data' => new SubscriptionCheckoutResource($result['checkout']),
                'payment_link' => $result['payment_link'],
                'is_checkout' => true,
            ], 201);
        } catch (\Exception $e) {
            $code = (int) $e->getCode();
            if ($code >= 400 && $code < 500) {
                return $this->errorResponse($e->getMessage(), $code);
            }

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
                'subscription.offer.conditions.product',
                'subscription.offer.conditions.productVariant',
                'subscription.offer.rewards.product',
                'subscription.offer.rewards.productVariant',
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
     * Get specific customer subscription details with paginated orders.
     * For both Mobile & Web
     */
    public function mySubscriptionDetails(Request $request, int $id): JsonResponse
    {
        $request->validate(
            [
                'status' => 'nullable|in:pending,processing,shipped,delivered,cancelled,completed',
                'order_status' => 'nullable|in:pending,processing,shipped,delivered,cancelled,completed',
                'per_page' => 'nullable|integer|min:1|max:100',
            ],
            [
                'status.in' => 'The status filter applies to orders, not the subscription. Allowed: pending, processing, shipped, delivered, cancelled.',
                'order_status.in' => 'The order status must be one of: pending, processing, shipped, delivered, cancelled.',
            ]
        );

        try {
            $customer = $this->resolveAuthenticatedCustomer();

            if (!$customer) {
                return $this->unauthorizedResponse(
                    'No authenticated customer found. This endpoint requires a customer token, not an admin token.'
                );
            }

            $orderStatus = $request->query('order_status') ?: $request->query('status');
            if (is_string($orderStatus)) {
                $orderStatus = strtolower(trim($orderStatus));
            }
            if ($orderStatus === '') {
                $orderStatus = null;
            }
            if ($orderStatus === 'completed') {
                $orderStatus = 'delivered';
            }
            $perPage = (int) $request->input('per_page', 15);

            $subscription = CustomerSubscription::query()
                ->withCount([
                    'orders as completed_orders_count' => fn ($q) => $q->where('status', 'delivered'),
                    'orders as pending_orders_count' => fn ($q) => $q->where('status', 'pending'),
                    'orders as processing_orders_count' => fn ($q) => $q->where('status', 'processing'),
                    'orders as cancelled_orders_count' => fn ($q) => $q->where('status', 'cancelled'),
                ])
                ->where('customer_id', $customer->id)
                ->findOrFail($id);

            $ordersQuery = SubscriptionOrder::query()
                ->with(['items.product', 'items.productVariant'])
                ->where('customer_subscription_id', $subscription->id)
                ->orderBy('order_sequence');

            if ($orderStatus) {
                $ordersQuery->where('subscription_orders.status', $orderStatus);
            }

            $orders = $ordersQuery->paginate($perPage);

            $response = [
                'success' => true,
                'message' => 'Subscription orders retrieved successfully',
                'data' => SubscriptionOrderResource::collection($orders->items()),
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'from' => $orders->firstItem(),
                    'to' => $orders->lastItem(),
                ],
                'statistics' => [
                    'total_orders' => (int) $subscription->total_orders,
                    'pending_orders' => (int) $subscription->pending_orders_count,
                    'processing_orders' => (int) $subscription->processing_orders_count,
                    'delivered_orders' => (int) $subscription->completed_orders_count,
                    'cancelled_orders' => (int) $subscription->cancelled_orders_count,
                ],
            ];

            if ($orderStatus) {
                $response['filters'] = ['order_status' => $orderStatus];
            }

            return response()->json($response);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
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

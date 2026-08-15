<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Subscription;
use App\Models\CustomerSubscription;
use App\Http\Requests\Admin\SubscriptionRequest;
use App\Http\Resources\Admin\SubscriptionResource;
use App\Http\Resources\Admin\CustomerSubscriptionResource;
use App\Http\Resources\Admin\RefundRequestResource;
use App\Services\RefundRequestService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends BaseApiController
{
    public function __construct(
        protected RefundRequestService $refundRequestService
    ) {}

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

    /**
     * List all purchased customer subscriptions with filters.
     */
    public function customerSubscriptionsIndex(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|in:pending_payment,active,paused,cancelled,completed,pending_cancellation',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'subscription_id' => 'nullable|integer|exists:subscriptions,id',
            'source' => 'nullable|in:app,web',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        try {
            $query = CustomerSubscription::with([
                'customer',
                'subscription.offer.conditions.product',
                'subscription.offer.conditions.productVariant',
                'subscription.offer.rewards.product',
                'subscription.offer.rewards.productVariant',
                'invoice',
            ])
                ->withCount([
                    'orders as completed_orders_count' => fn ($q) => $q->where('status', 'delivered'),
                    'orders as pending_orders_count' => fn ($q) => $q->where('status', 'pending'),
                    'orders as cancelled_orders_count' => fn ($q) => $q->where('status', 'cancelled'),
                ])
                ->orderBy('created_at', 'desc');

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->filled('customer_id')) {
                $query->where('customer_id', $request->input('customer_id'));
            }

            if ($request->filled('subscription_id')) {
                $query->where('subscription_id', $request->input('subscription_id'));
            }

            if ($request->filled('source')) {
                $query->where('source', $request->input('source'));
            }

            if ($request->filled('date_from')) {
                $query->whereDate('start_date', '>=', $request->input('date_from'));
            }

            if ($request->filled('date_to')) {
                $query->whereDate('start_date', '<=', $request->input('date_to'));
            }

            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->whereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('phone', 'LIKE', "%{$search}%")
                            ->orWhere('email', 'LIKE', "%{$search}%")
                            ->orWhere('customer_code', 'LIKE', "%{$search}%");
                    })->orWhereHas('subscription.offer', function ($offerQuery) use ($search) {
                        $offerQuery->where('title_en', 'LIKE', "%{$search}%")
                            ->orWhere('title_ar', 'LIKE', "%{$search}%");
                    });
                });
            }

            $perPage = $request->input('per_page', 15);
            $subscriptions = $query->paginate($perPage);

            $response = [
                'success' => true,
                'message' => 'Customer subscriptions retrieved successfully',
                'data' => CustomerSubscriptionResource::collection($subscriptions->items()),
                'pagination' => [
                    'current_page' => $subscriptions->currentPage(),
                    'last_page' => $subscriptions->lastPage(),
                    'per_page' => $subscriptions->perPage(),
                    'total' => $subscriptions->total(),
                    'from' => $subscriptions->firstItem(),
                    'to' => $subscriptions->lastItem(),
                ],
            ];

            $filters = array_filter([
                'search' => $request->input('search'),
                'status' => $request->input('status'),
                'customer_id' => $request->input('customer_id'),
                'subscription_id' => $request->input('subscription_id'),
                'source' => $request->input('source'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
            ], fn ($value) => $value !== null && $value !== '');

            if (!empty($filters)) {
                $response['filters'] = $filters;
            }

            return response()->json($response);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred while retrieving customer subscriptions: ' . $e->getMessage());
        }
    }

    /**
     * Show a purchased customer subscription with orders and items.
     */
    public function customerSubscriptionShow(int $id): JsonResponse
    {
        try {
            $subscription = CustomerSubscription::with([
                'customer',
                'subscription.offer.conditions.product',
                'subscription.offer.conditions.productVariant',
                'subscription.offer.rewards.product',
                'subscription.offer.rewards.productVariant',
                'invoice',
                'orders' => fn ($q) => $q->orderBy('order_sequence'),
                'orders.items.product',
                'orders.items.productVariant',
                'refundRequests' => fn ($q) => $q->orderBy('created_at', 'desc'),
            ])
                ->withCount([
                    'orders as completed_orders_count' => fn ($q) => $q->where('status', 'delivered'),
                    'orders as pending_orders_count' => fn ($q) => $q->where('status', 'pending'),
                    'orders as cancelled_orders_count' => fn ($q) => $q->where('status', 'cancelled'),
                ])
                ->find($id);

            if (!$subscription) {
                return $this->notFoundResponse('Customer subscription not found');
            }

            return $this->successResponse(
                new CustomerSubscriptionResource($subscription),
                'Customer subscription retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred while retrieving the customer subscription: ' . $e->getMessage());
        }
    }

    /**
     * Cancel a customer's purchased subscription (admin only).
     * Creates a refund request for remaining pending orders cost.
     * Wallet is credited only after refund approval.
     */
    public function cancelCustomerSubscription(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        try {
            $result = $this->refundRequestService->requestForCustomerSubscription(
                $id,
                $request->input('reason')
            );

            if (!$result['success']) {
                return $this->errorResponse($result['message'], 400);
            }

            $customerSubscription = $result['customer_subscription']->load([
                'customer',
                'subscription.offer.conditions.product',
                'subscription.offer.conditions.productVariant',
                'subscription.offer.rewards.product',
                'subscription.offer.rewards.productVariant',
                'invoice',
                'orders' => fn ($q) => $q->orderBy('order_sequence'),
                'orders.items.product',
                'orders.items.productVariant',
                'refundRequests' => fn ($q) => $q->orderBy('created_at', 'desc'),
            ])->loadCount([
                'orders as completed_orders_count' => fn ($q) => $q->where('status', 'delivered'),
                'orders as pending_orders_count' => fn ($q) => $q->where('status', 'pending'),
                'orders as cancelled_orders_count' => fn ($q) => $q->where('status', 'cancelled'),
            ]);

            logAdminActivity('requested cancellation refund', 'CustomerSubscription', $id);

            return $this->successResponse([
                'customer_subscription' => new CustomerSubscriptionResource($customerSubscription),
                'refund_request' => $result['refund_request']
                    ? new RefundRequestResource($result['refund_request'])
                    : null,
            ], $result['message']);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to cancel customer subscription: ' . $e->getMessage());
        }
    }
}

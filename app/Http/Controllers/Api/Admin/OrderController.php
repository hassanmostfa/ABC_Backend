<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\PendingOnlineInvoiceException;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Concerns\HandlesOrderCheckouts;
use App\Http\Requests\Admin\BulkUpdateOrderStatusRequest;
use App\Http\Requests\Admin\StoreOrderRequest;
use App\Http\Requests\Admin\UpdateOrderRequest;
use App\Http\Resources\Admin\OrderResource;
use App\Http\Resources\Admin\RefundRequestResource;
use App\Http\Resources\CheckoutAsOrderResource;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderCheckout;
use App\Repositories\OrderRepositoryInterface;
use App\Services\OrderCancellationService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class OrderController extends BaseApiController
{
    use HandlesOrderCheckouts;

    protected $orderRepository;
    protected $orderService;
    protected $orderCancellationService;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        OrderService $orderService,
        OrderCancellationService $orderCancellationService
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderService = $orderService;
        $this->orderCancellationService = $orderCancellationService;
    }

    /**
     * Display a listing of the orders with pagination, search and filters.
     */
    public function index(Request $request): JsonResponse
    {
        // Validate filter parameters
        $request->validate([
            'search' => 'nullable|string|max:1000',
            'status' => 'nullable|in:pending,processing,completed,cancelled',
            'payment_method' => 'nullable|in:cash,wallet',
            'delivery_type' => 'nullable|in:pickup,delivery',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Prepare filters
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'payment_method' => $request->input('payment_method'),
            'delivery_type' => $request->input('delivery_type'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];

        // Remove empty filters
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        $perPage = $request->input('per_page', 15);
        $orders = $this->orderRepository->getAllPaginated($filters, $perPage);

        // Transform orders using resource
        $transformedOrders = OrderResource::collection($orders->items());

        // Create a custom response with pagination and filters
        $response = [
            'success' => true,
            'message' => 'Orders retrieved successfully',
            'data' => $transformedOrders,
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'from' => $orders->firstItem(),
                'to' => $orders->lastItem(),
            ]
        ];

        if (!empty($filters)) {
            $response['filters'] = $filters;
        }

        return response()->json($response);
    }

    /**
     * Store a newly created order in storage.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        try {
            $orderData = $request->validated();
            $orderData['source'] = $orderData['source'] ?? 'call_center';

            $admin = $request->user();
            if ($admin instanceof Admin) {
                $orderData['acting_admin_id'] = $admin->id;
            }

            $result = $this->orderService->createOrder($orderData);

            if (!empty($result['is_checkout'])) {
                $checkout = $result['checkout'];
                $checkout->load('customer');
                logAdminActivity('created', 'OrderCheckout', $checkout->id);

                return $this->createdResponse(new CheckoutAsOrderResource($checkout), 'Order created successfully');
            }

            logAdminActivity('created', 'Order', $result['order']->id);

            if (isset($result['payment_link'])) {
                Log::info('Payment link found in result for order ' . $result['order']->id . ': ' . $result['payment_link']);
                $result['order']->payment_link = $result['payment_link'];
            } else {
                Log::info('Payment link NOT found in result for order ' . $result['order']->id . '. Result keys: ' . implode(', ', array_keys($result)));
            }

            return $this->createdResponse(new OrderResource($result['order']), 'Order created successfully');
        } catch (PendingOnlineInvoiceException $e) {
            return $this->pendingOnlineInvoiceResponse($e, $request, $e->getMessage());
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) && $e->getCode() > 0 ? (int) $e->getCode() : 500;
            return $this->errorResponse($e->getMessage(), $code);
        }
    }

    /**
     * Display the specified order.
     */
    public function show(int $id): JsonResponse
    {
        $entity = $this->checkoutResolver()->resolveCheckoutOrOrder($id);

        if (!$entity) {
            return $this->notFoundResponse('Order not found');
        }

        if ($entity instanceof OrderCheckout) {
            $entity->load(['customer']);

            return $this->resourceResponse(new CheckoutAsOrderResource($entity), 'Order retrieved successfully');
        }

        $entity->load(['customer', 'charity', 'offers', 'items.product', 'items.variant', 'invoice.payments', 'customerAddress', 'createdBy']);

        return $this->resourceResponse(new OrderResource($entity), 'Order retrieved successfully');
    }

    /**
     * Update the specified order in storage.
     */
    public function update(UpdateOrderRequest $request, int $id): JsonResponse
    {
        $order = $this->orderRepository->findById($id);

        if (!$order) {
            return $this->notFoundResponse('Order not found');
        }

        $newStatus = $request->input('status');
        if ($newStatus === 'cancelled' && $order->status !== 'cancelled') {
            try {
                $result = $this->orderCancellationService->cancelOrder($id, $request->input('reason'));

                if (!$result['success']) {
                    return $this->errorResponse($result['message'], 400);
                }

                logAdminActivity('cancelled', 'Order', $id);

                $response = [
                    'order' => new OrderResource($this->orderRepository->findById($id)),
                ];

                if (isset($result['refund_request']) && $result['refund_request']) {
                    $response['refund_request'] = new RefundRequestResource($result['refund_request']);
                }

                return $this->updatedResponse($response, $result['message']);
            } catch (\Exception $e) {
                $code = is_numeric($e->getCode()) && $e->getCode() > 0 ? (int) $e->getCode() : 500;
                return $this->errorResponse($e->getMessage(), $code);
            }
        }

        try {
            $result = $this->orderService->updateOrder($id, $request->validated());
            
            // Log activity
            logAdminActivity('updated', 'Order', $id);
            
            return $this->updatedResponse(new OrderResource($result['order']), 'Order updated successfully');
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) && $e->getCode() > 0 ? (int) $e->getCode() : 500;
            return $this->errorResponse($e->getMessage(), $code);
        }
    }

    /**
     * Cancel the specified order.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $checkout = $this->checkoutResolver()->findCheckout($id);
        if ($checkout && !$checkout->order_id) {
            try {
                $result = $this->checkoutResolver()->cancel($id, $request->input('reason'));
                if (!$result['success']) {
                    return $this->errorResponse($result['message'], 400);
                }

                logAdminActivity('cancelled', 'OrderCheckout', $id);

                return $this->successResponse([
                    'success' => true,
                    'message' => $result['message'],
                    'order' => new CheckoutAsOrderResource($result['checkout']),
                ], $result['message']);
            } catch (\Exception $e) {
                return $this->errorResponse($e->getMessage(), 500);
            }
        }

        $order = $this->orderRepository->findById($id);

        if (!$order) {
            return $this->notFoundResponse('Order not found');
        }

        try {
            $result = $this->orderCancellationService->cancelOrder($id, $request->input('reason'));

            if (!$result['success']) {
                return $this->errorResponse($result['message'], 400);
            }

            logAdminActivity('cancelled', 'Order', $id);

            $response = [
                'success' => true,
                'message' => $result['message'],
                'order' => new OrderResource($this->orderRepository->findById($id)),
            ];
            if (isset($result['refund_request'])) {
                $response['refund_request'] = new RefundRequestResource($result['refund_request']);
            }
            return $this->successResponse($response, $result['message']);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified order from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->orderRepository->delete($id);

        if (!$deleted) {
            return $this->notFoundResponse('Order not found');
        }

        // Log activity
        logAdminActivity('deleted', 'Order', $id);

        return $this->deletedResponse('Order deleted successfully');
    }

    /**
     * Regenerate payment link for an order (online_link payment method only).
     */
    public function regeneratePaymentLink(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'src' => 'nullable|string|in:knet,cc',
        ]);

        $result = $this->orderService->regeneratePaymentLink($id, $validated['src'] ?? null);

        if (!$result['success']) {
            $code = in_array($result['message'], ['Order not found.'], true) ? 404 : 400;
            return $this->errorResponse($result['message'], $code);
        }

        logAdminActivity('regenerated payment link', 'Order', $id);

        return $this->successResponse([
            'payment_link' => $result['payment_link'],
        ], $result['message']);
    }

    /**
     * Poll Ottu and apply payment when webhook cannot reach this server (e.g. local dev).
     */
    public function syncPayment(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'nullable|string|max:128',
        ]);

        $result = $this->orderService->syncOttuPaymentStatus($id, $validated['session_id'] ?? null);

        if (!$result['success']) {
            $code = in_array($result['message'], ['Order not found.'], true) ? 404 : 400;

            return $this->errorResponse($result['message'], $code, $result);
        }

        logAdminActivity('synced ottu payment', 'Order', $id);

        $order = $result['order'] ?? null;
        if (!$order instanceof Order) {
            $resolved = $this->checkoutResolver()->resolveCheckoutOrOrder($id);
            if ($resolved instanceof Order) {
                $order = $resolved;
            }
        }

        if ($order) {
            $order->load(['invoice', 'items.product', 'items.variant', 'customer', 'invoice.payments']);
        }

        return $this->successResponse([
            'order' => $order ? new OrderResource($order) : null,
            'invoice_status' => $result['invoice_status'] ?? null,
            'payment_status' => $result['payment_status'] ?? null,
        ], $result['message']);
    }

    /**
     * Switch cash-on-delivery order to online payment and return a new payment link.
     */
    public function switchToPaymentLink(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'src' => 'required|string|in:knet,cc',
        ]);

        $result = $this->orderService->switchCashOrderToOnlinePayment($id, $validated['src']);

        if (!$result['success']) {
            $code = in_array($result['message'], ['Order not found.'], true) ? 404 : 400;
            return $this->errorResponse($result['message'], $code);
        }

        $order = $result['order'];
        if ($order && isset($result['payment_link'])) {
            $order->payment_link = $result['payment_link'];
        }

        logAdminActivity('switched order to online payment link', 'Order', $id);

        return $this->successResponse([
            'order' => new OrderResource($order),
            'payment_link' => $result['payment_link'],
        ], $result['message']);
    }

    /**
     * Bulk update order statuses.
     */
    public function bulkUpdateStatus(BulkUpdateOrderStatusRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $orderIds = $validated['order_ids'];
        $status = $validated['status'];
        $reason = $validated['reason'] ?? null;

        $existingOrderIds = Order::query()
            ->whereIn('id', $orderIds)
            ->pluck('id')
            ->all();

        $notFoundOrderIds = array_values(array_diff($orderIds, $existingOrderIds));

        $skippedOrderIds = Order::query()
            ->whereIn('id', $existingOrderIds)
            ->whereIn('status', ['completed', 'cancelled'])
            ->pluck('id')
            ->all();

        $updatableOrderIds = array_values(array_diff($existingOrderIds, $skippedOrderIds));

        $updatedCount = 0;
        $updatedOrderIds = [];
        $failedOrderIds = [];
        $refundRequestIds = [];

        if ($status === 'cancelled') {
            foreach ($updatableOrderIds as $orderId) {
                try {
                    $result = $this->orderCancellationService->cancelOrder($orderId, $reason);

                    if (!$result['success']) {
                        $failedOrderIds[] = $orderId;
                        continue;
                    }

                    $updatedCount++;
                    $updatedOrderIds[] = $orderId;

                    if (isset($result['refund_request']) && $result['refund_request']) {
                        $refundRequestIds[] = $result['refund_request']->id;
                    }
                } catch (\Exception $e) {
                    $failedOrderIds[] = $orderId;
                }
            }
        } elseif (!empty($updatableOrderIds)) {
            $updatedCount = Order::query()
                ->whereIn('id', $updatableOrderIds)
                ->update(['status' => $status]);
            $updatedOrderIds = $updatableOrderIds;
        }

        logAdminActivity('bulk updated status', 'Order', null, [
            'requested_order_ids' => $orderIds,
            'status' => $status,
            'reason' => $reason,
            'updated_count' => $updatedCount,
            'updated_order_ids' => $updatedOrderIds,
            'skipped_order_ids' => $skippedOrderIds,
            'not_found_order_ids' => $notFoundOrderIds,
            'failed_order_ids' => $failedOrderIds,
            'refund_request_ids' => $refundRequestIds,
        ]);

        return $this->successResponse([
            'status' => $status,
            'requested_count' => count($orderIds),
            'updated_count' => $updatedCount,
            'updated_order_ids' => $updatedOrderIds,
            'skipped_count' => count($skippedOrderIds),
            'skipped_order_ids' => $skippedOrderIds,
            'not_found_count' => count($notFoundOrderIds),
            'not_found_order_ids' => $notFoundOrderIds,
            'failed_count' => count($failedOrderIds),
            'failed_order_ids' => $failedOrderIds,
            'refund_request_count' => count($refundRequestIds),
            'refund_request_ids' => $refundRequestIds,
        ], 'Orders status updated successfully');
    }

}


<?php

namespace App\Http\Controllers\Api\Mobile\orders;

use App\Exceptions\PendingOnlineInvoiceException;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Concerns\HandlesOrderCheckouts;
use App\Http\Requests\Mobile\StoreOrderRequest;
use App\Http\Resources\Admin\OrderResource;
use App\Http\Resources\CheckoutAsOrderResource;
use App\Http\Resources\Admin\RefundRequestResource;
use App\Models\Order;
use App\Models\Setting;
use App\Repositories\OrderRepositoryInterface;
use App\Services\OrderCancellationService;
use App\Services\OrderService;
use App\Jobs\SendOrderCreatedNotificationsJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;


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
     * Create a new order (mobile API)
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        try {
            $orderingEnabled = (bool) (Setting::getValue('app_ordering_enabled', '1') === '1' || Setting::getValue('app_ordering_enabled', '1') === 1);
            if (!$orderingEnabled) {
                $locale = $this->getLocaleFromRequest($request);
                $message = (string) Setting::getTranslatedValue('app_ordering_disabled_message', $locale, '');
                if ($message === '') {
                    $message = $locale === 'ar'
                        ? 'الطلب من التطبيق غير متاح حالياً. يرجى زيارة فروعنا لتقديم طلبك.'
                        : 'Ordering from the app is currently unavailable. Please visit our stores to place your order.';
                }
                return $this->errorResponse($message, 503);
            }

            // Get authenticated customer
            $customer = Auth::guard('sanctum')->user();

            if (!$customer) {
                return $this->unauthorizedResponse('No authenticated customer found');
            }

            // Merge customer_id from authenticated user and set source for order number prefix (APP)
            $orderData = $request->validated();
            $orderData['customer_id'] = $customer->id;
            $orderData['source'] = 'app';

            // Create order using the same service as admin
            $result = $this->orderService->createOrder($orderData);

            if (!empty($result['is_checkout'])) {
                $checkout = $result['checkout'];
                $checkout->load('customer');

                return $this->createdResponse(new CheckoutAsOrderResource($checkout), 'Order created successfully');
            }

            if (isset($result['payment_link'])) {
                $result['order']->payment_link = $result['payment_link'];
            }
            
            // Order-created notifications (customer + admins). Skip for online_link: customer is notified after payment succeeds.
            if ($result['order']->payment_method !== 'online_link') {
                SendOrderCreatedNotificationsJob::dispatch($result['order']->id)->afterResponse();
            }

            return $this->createdResponse(new OrderResource($result['order']), 'Order created successfully');
        } catch (PendingOnlineInvoiceException $e) {
            $locale = $this->getLocaleFromRequest($request);
            $message = $locale === 'ar'
                ? 'لا يمكنك إنشاء طلب جديد بالدفع الإلكتروني قبل سداد الفاتورة المعلقة أو إلغاء الطلب الحالي.'
                : $e->getMessage();

            return $this->pendingOnlineInvoiceResponse($e, $request, $message);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) && $e->getCode() > 0 ? (int) $e->getCode() : 500;
            return $this->errorResponse($e->getMessage(), $code);
        }
    }

    /**
     * Display the specified order (mobile API)
     */
    public function show(int $id): JsonResponse
    {
        // Get authenticated customer
        $customer = Auth::guard('sanctum')->user();

        if (!$customer) {
            return $this->unauthorizedResponse('No authenticated customer found');
        }

        $entity = $this->authorizeCheckoutOrOrder($id, $customer->id);

        if (!$entity) {
            $checkout = $this->checkoutResolver()->findCheckout($id);
            $order = $this->checkoutResolver()->findOrder($id);
            if (!$checkout && !$order) {
                return $this->notFoundResponse('Order not found');
            }

            return $this->unauthorizedResponse('You do not have permission to view this order');
        }

        return $this->resourceResponse($this->orderResponseFromEntity($entity), 'Order retrieved successfully');
    }

    /**
     * Get all customer orders with statistics (mobile API)
     */
    public function index(Request $request): JsonResponse
    {
        // Validate filter parameters
        $request->validate([
            'status' => 'nullable|in:pending,processing,completed,cancelled',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Get authenticated customer
        $customer = Auth::guard('sanctum')->user();

        if (!$customer) {
            return $this->unauthorizedResponse('No authenticated customer found');
        }

        // Prepare filters - always filter by customer_id
        $filters = [
            'customer_id' => $customer->id,
            'status' => $request->input('status'),
        ];

        // Remove empty filters
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        $perPage = $request->input('per_page', 15);
        
        // Get paginated orders
        $orders = $this->orderRepository->getAllPaginated($filters, $perPage);

        // Get all orders for statistics (not paginated)
        $allOrders = $this->orderRepository->getByCustomer($customer->id);

        // Calculate statistics from all orders
        $stats = [
            'total_orders' => $allOrders->count(),
            'pending_orders' => $allOrders->where('status', 'pending')->count(),
            'processing_orders' => $allOrders->where('status', 'processing')->count(),
            'completed_orders' => $allOrders->where('status', 'completed')->count(),
            'cancelled_orders' => $allOrders->where('status', 'cancelled')->count(),
        ];

        // Transform orders using resource
        $transformedOrders = OrderResource::collection($orders->items());

        // Create response with pagination, orders and statistics
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
            ],
            'statistics' => $stats,
        ];

        if (!empty($filters) && isset($filters['status'])) {
            $response['filters'] = ['status' => $filters['status']];
        }

        return response()->json($response);
    }

    /**
     * Cancel order (mobile API) - customer can cancel their own order
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $customer = Auth::guard('sanctum')->user();

        if (!$customer) {
            return $this->unauthorizedResponse('No authenticated customer found');
        }

        $entity = $this->authorizeCheckoutOrOrder($id, $customer->id);

        if (!$entity) {
            $checkout = $this->checkoutResolver()->findCheckout($id);
            $order = $this->checkoutResolver()->findOrder($id);
            if (!$checkout && !$order) {
                return $this->notFoundResponse('Order not found');
            }

            return $this->unauthorizedResponse('You do not have permission to cancel this order');
        }

        try {
            if ($entity instanceof \App\Models\OrderCheckout) {
                $result = $this->checkoutResolver()->cancel($id, $request->input('reason'));
                if (!$result['success']) {
                    return $this->errorResponse($result['message'], 400);
                }

                return $this->successResponse([
                    'success' => true,
                    'message' => $result['message'],
                    'order' => new CheckoutAsOrderResource($result['checkout']),
                ], $result['message']);
            }

            $result = $this->orderCancellationService->cancelOrder($id, $request->input('reason'));

            if (!$result['success']) {
                return $this->errorResponse($result['message'], 400);
            }

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
     * Regenerate payment link for customer's own order (online_link only).
     */
    public function regeneratePaymentLink(Request $request, int $id): JsonResponse
    {
        $customer = Auth::guard('sanctum')->user();

        if (!$customer) {
            return $this->unauthorizedResponse('No authenticated customer found');
        }

        $validated = $request->validate([
            'src' => 'nullable|string|in:knet,cc',
        ]);

        $entity = $this->authorizeCheckoutOrOrder($id, $customer->id);

        if (!$entity) {
            $checkout = $this->checkoutResolver()->findCheckout($id);
            $order = $this->checkoutResolver()->findOrder($id);
            if (!$checkout && !$order) {
                return $this->notFoundResponse('Order not found');
            }

            return $this->unauthorizedResponse('You do not have permission to regenerate link for this order');
        }

        $result = $this->orderService->regeneratePaymentLink($id, $validated['src'] ?? null);

        if (!$result['success']) {
            $code = $result['message'] === 'Order not found.' ? 404 : 400;
            return $this->errorResponse($result['message'], $code);
        }

        return $this->successResponse([
            'payment_link' => $result['payment_link'],
        ], $result['message']);
    }

    /**
     * After Ottu checkout success, sync payment status from Ottu API (required when webhook URL is not public).
     */
    public function syncPayment(Request $request, int $id): JsonResponse
    {
        $customer = Auth::guard('sanctum')->user();

        if (!$customer) {
            return $this->unauthorizedResponse('No authenticated customer found');
        }

        $validated = $request->validate([
            'session_id' => 'nullable|string|max:128',
        ]);

        $entity = $this->authorizeCheckoutOrOrder($id, $customer->id);

        if (!$entity) {
            $checkout = $this->checkoutResolver()->findCheckout($id);
            $order = $this->checkoutResolver()->findOrder($id);
            if (!$checkout && !$order) {
                return $this->notFoundResponse('Order not found');
            }

            return $this->unauthorizedResponse('You do not have permission to sync payment for this order');
        }

        $result = $this->orderService->syncOttuPaymentStatus($id, $validated['session_id'] ?? null);

        if (!$result['success']) {
            $code = str_contains($result['message'], 'not found') ? 404 : 400;

            return $this->errorResponse($result['message'], $code, $result);
        }

        $order = $result['order'] ?? null;
        if (!$order instanceof Order) {
            $resolved = $this->checkoutResolver()->resolveCheckoutOrOrder($id);
            if ($resolved instanceof Order) {
                $order = $resolved;
            }
        }

        if (!$order) {
            return $this->errorResponse('Order not found after payment sync.', 404);
        }

        $order->load(['invoice', 'items.product', 'items.variant', 'customer']);

        return $this->successResponse([
            'order' => new OrderResource($order),
            'invoice_status' => $result['invoice_status'] ?? null,
            'payment_status' => $result['payment_status'] ?? null,
        ], $result['message']);
    }

    private function getLocaleFromRequest(Request $request): string
    {
        $raw = strtolower((string) ($request->header('LANG') ?? $request->header('Accept-Language') ?? $request->input('locale', 'ar')));
        $primary = trim(explode(',', $raw)[0]);
        $primary = trim(explode(';', $primary)[0]);
        if (str_starts_with($primary, 'ar')) {
            return 'ar';
        }
        if (str_starts_with($primary, 'en')) {
            return 'en';
        }
        return in_array($primary, ['ar', 'en']) ? $primary : 'ar';
    }
}

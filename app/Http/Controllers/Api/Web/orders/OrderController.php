<?php

namespace App\Http\Controllers\Api\Web\orders;

use App\Exceptions\PendingOnlineInvoiceException;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Concerns\HandlesOrderCheckouts;
use App\Http\Requests\Web\StoreOrderRequest;
use App\Http\Resources\Admin\OrderResource;
use App\Http\Resources\CheckoutAsOrderResource;
use App\Http\Resources\Admin\RefundRequestResource;
use App\Models\Setting;
use App\Repositories\Orders\OrderRepositoryInterface;
use App\Services\OrderCancellationService;
use App\Services\OrderService;
use App\Jobs\SendOrderCreatedNotificationsJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class OrderController extends BaseApiController
{
    use HandlesOrderCheckouts;

    public function __construct(
        protected OrderRepositoryInterface $orderRepository,
        protected OrderService $orderService,
        protected OrderCancellationService $orderCancellationService
    ) {}

    /**
     * Create order from website (order number prefix WEB- e.g. WEB-2026-000001; online payment redirects use website URLs).
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
                        ? 'الطلب عبر الموقع غير متاح حالياً. يرجى زيارة فروعنا لتقديم طلبك.'
                        : 'Online ordering is currently unavailable. Please visit our stores to place your order.';
                }
                return $this->errorResponse($message, 503);
            }

            $customer = Auth::guard('sanctum')->user();
            if (!$customer) {
                return $this->unauthorizedResponse('No authenticated customer found');
            }

            $orderData = $request->validated();
            $orderData['customer_id'] = $customer->id;
            $orderData['source'] = 'web';

            $result = $this->orderService->createOrder($orderData);

            if (!empty($result['is_checkout'])) {
                $checkout = $result['checkout'];
                $checkout->load('customer');

                return $this->createdResponse(new CheckoutAsOrderResource($checkout), 'Order created successfully');
            }

            if (isset($result['payment_link'])) {
                $result['order']->payment_link = $result['payment_link'];
            }

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

    public function show(int $id): JsonResponse
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
            return $this->unauthorizedResponse('You do not have permission to view this order');
        }

        return $this->resourceResponse($this->orderResponseFromEntity($entity), 'Order retrieved successfully');
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:1000',
            'status' => 'nullable|in:pending,processing,completed,cancelled,refund',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $customer = Auth::guard('sanctum')->user();
        if (!$customer) {
            return $this->unauthorizedResponse('No authenticated customer found');
        }

        $filters = array_filter([
            'customer_id' => $customer->id,
            'search' => $request->input('search'),
            'status' => $request->input('status'),
        ], fn ($v) => $v !== null && $v !== '');

        $perPage = $request->input('per_page', 15);
        $orders = $this->orderRepository->getAllPaginated($filters, $perPage);
        $allOrders = $this->orderRepository->getByCustomer($customer->id);

        $stats = [
            'total_orders' => $allOrders->count(),
            'pending_orders' => $allOrders->where('status', 'pending')->count(),
            'processing_orders' => $allOrders->where('status', 'processing')->count(),
            'completed_orders' => $allOrders->where('status', 'completed')->count(),
            'cancelled_orders' => $allOrders->where('status', 'cancelled')->count(),
        ];

        $response = [
            'success' => true,
            'message' => 'Orders retrieved successfully',
            'data' => OrderResource::collection($orders->items()),
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

        if (!empty($filters) && (isset($filters['status']) || isset($filters['search']))) {
            $response['filters'] = array_filter([
                'status' => $filters['status'] ?? null,
                'search' => $filters['search'] ?? null,
            ], fn ($value) => $value !== null && $value !== '');
        }

        return response()->json($response);
    }

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
        if (!$order instanceof \App\Models\Order) {
            $resolved = $this->checkoutResolver()->resolveCheckoutOrOrder($id);
            if ($resolved instanceof \App\Models\Order) {
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
        $raw = strtolower((string) ($request->header('LANG') ?? $request->header('Accept-Language') ?? $request->input('locale', 'en')));
        $primary = trim(explode(',', $raw)[0]);
        $primary = trim(explode(';', $primary)[0]);
        if (str_starts_with($primary, 'ar')) {
            return 'ar';
        }
        if (str_starts_with($primary, 'en')) {
            return 'en';
        }
        return in_array($primary, ['ar', 'en']) ? $primary : 'en';
    }
}

<?php

namespace App\Http\Controllers\Api\Web\orders;

use App\Exceptions\PendingOnlineInvoiceException;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Mobile\StoreOrderRequest;
use App\Http\Resources\Admin\OrderResource;
use App\Http\Resources\Admin\RefundRequestResource;
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

            return response()->json([
                'success' => false,
                'message' => $message,
                'pending_invoices' => OrderResource::collection($e->pendingOrders)->toArray($request),
            ], 409);
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

        $order = $this->orderRepository->findById($id);
        if (!$order) {
            return $this->notFoundResponse('Order not found');
        }
        if ($order->customer_id !== $customer->id) {
            return $this->unauthorizedResponse('You do not have permission to view this order');
        }

        $order->load(['customer', 'charity', 'offers', 'items.product', 'items.variant', 'invoice', 'customerAddress']);

        return $this->resourceResponse(new OrderResource($order), 'Order retrieved successfully');
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|in:pending,processing,completed,cancelled',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $customer = Auth::guard('sanctum')->user();
        if (!$customer) {
            return $this->unauthorizedResponse('No authenticated customer found');
        }

        $filters = array_filter([
            'customer_id' => $customer->id,
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

        if (!empty($filters) && isset($filters['status'])) {
            $response['filters'] = ['status' => $filters['status']];
        }

        return response()->json($response);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $customer = Auth::guard('sanctum')->user();
        if (!$customer) {
            return $this->unauthorizedResponse('No authenticated customer found');
        }

        $order = $this->orderRepository->findById($id);
        if (!$order) {
            return $this->notFoundResponse('Order not found');
        }
        if ($order->customer_id !== $customer->id) {
            return $this->unauthorizedResponse('You do not have permission to cancel this order');
        }

        try {
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

        $order = $this->orderRepository->findById($id);
        if (!$order) {
            return $this->notFoundResponse('Order not found');
        }
        if ((int) $order->customer_id !== (int) $customer->id) {
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

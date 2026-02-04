<?php

namespace App\Http\Controllers\Api\Mobile\orders;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Mobile\StoreOrderRequest;
use App\Http\Resources\Admin\OrderResource;
use App\Http\Resources\Admin\RefundRequestResource;
use App\Repositories\OrderRepositoryInterface;
use App\Services\OrderCancellationService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class OrderController extends BaseApiController
{
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
            // Get authenticated customer
            $customer = Auth::guard('sanctum')->user();

            if (!$customer) {
                return $this->unauthorizedResponse('No authenticated customer found');
            }

            // Merge customer_id from authenticated user
            $orderData = $request->validated();
            $orderData['customer_id'] = $customer->id;

            // Create order using the same service as admin
            $result = $this->orderService->createOrder($orderData);
            
            // Set payment_link as a temporary attribute on the order if available
            if (isset($result['payment_link'])) {
                $result['order']->payment_link = $result['payment_link'];
            }
            
            return $this->createdResponse(new OrderResource($result['order']), 'Order created successfully');
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

        $order = $this->orderRepository->findById($id);

        if (!$order) {
            return $this->notFoundResponse('Order not found');
        }

        // Ensure the order belongs to the authenticated customer
        if ($order->customer_id !== $customer->id) {
            return $this->unauthorizedResponse('You do not have permission to view this order');
        }

        // Load all relationships
        $order->load(['customer', 'charity', 'offers', 'items.product', 'items.variant', 'invoice', 'customerAddress']);

        return $this->resourceResponse(new OrderResource($order), 'Order retrieved successfully');
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
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\StoreOrderRequest;
use App\Http\Requests\Admin\UpdateOrderRequest;
use App\Http\Resources\Admin\OrderResource;
use App\Repositories\OrderRepositoryInterface;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrderController extends BaseApiController
{
    protected $orderRepository;
    protected $orderService;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        OrderService $orderService
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderService = $orderService;
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
            $result = $this->orderService->createOrder($request->validated());
            return $this->createdResponse(new OrderResource($result['order']), 'Order created successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Display the specified order.
     */
    public function show(int $id): JsonResponse
    {
        $order = $this->orderRepository->findById($id);

        if (!$order) {
            return $this->notFoundResponse('Order not found');
        }

        // Load all relationships
        $order->load(['customer', 'charity', 'offer', 'items.product', 'items.variant', 'invoice', 'delivery']);

        return $this->resourceResponse(new OrderResource($order), 'Order retrieved successfully');
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

        try {
            $result = $this->orderService->updateOrder($id, $request->validated());
            return $this->updatedResponse(new OrderResource($result['order']), 'Order updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
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

        return $this->deletedResponse('Order deleted successfully');
    }

}


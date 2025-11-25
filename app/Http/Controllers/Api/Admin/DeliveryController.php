<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\StoreDeliveryRequest;
use App\Http\Requests\Admin\UpdateDeliveryRequest;
use App\Http\Resources\Admin\DeliveryResource;
use App\Repositories\DeliveryRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DeliveryController extends BaseApiController
{
    protected $deliveryRepository;

    public function __construct(DeliveryRepositoryInterface $deliveryRepository)
    {
        $this->deliveryRepository = $deliveryRepository;
    }

    /**
     * Display a listing of deliveries.
     */
    public function index(Request $request): JsonResponse
    {
        // Validate filter parameters
        $request->validate([
            'search' => 'nullable|string|max:1000',
            'delivery_status' => 'nullable|in:pending,assigned,in_transit,delivered,failed,cancelled',
            'payment_method' => 'nullable|in:cash,card,online,bank_transfer,wallet',
            'order_id' => 'nullable|integer|exists:orders,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Prepare filters
        $filters = [
            'search' => $request->input('search'),
            'delivery_status' => $request->input('delivery_status'),
            'payment_method' => $request->input('payment_method'),
            'order_id' => $request->input('order_id'),
        ];

        // Remove empty filters
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        $perPage = $request->input('per_page', 15);
        $deliveries = $this->deliveryRepository->getAllPaginated($filters, $perPage);

        // Transform deliveries using resource
        $transformedDeliveries = DeliveryResource::collection($deliveries->items());

        // Create a custom response with pagination and filters
        $response = [
            'success' => true,
            'message' => 'Deliveries retrieved successfully',
            'data' => $transformedDeliveries,
            'pagination' => [
                'current_page' => $deliveries->currentPage(),
                'last_page' => $deliveries->lastPage(),
                'per_page' => $deliveries->perPage(),
                'total' => $deliveries->total(),
                'from' => $deliveries->firstItem(),
                'to' => $deliveries->lastItem(),
            ]
        ];

        if (!empty($filters)) {
            $response['filters'] = $filters;
        }

        return response()->json($response);
    }

    /**
     * Store a newly created delivery in storage.
     */
    public function store(StoreDeliveryRequest $request): JsonResponse
    {
        try {
            $delivery = $this->deliveryRepository->create($request->validated());

            // Reload with relationships
            $delivery = $this->deliveryRepository->findById($delivery->id);
            $delivery->load([
                'order.customer',
                'order.charity',
                'order.items.product',
                'order.items.variant',
                'order.invoice',
            ]);

            return $this->createdResponse(new DeliveryResource($delivery), 'Delivery created successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create delivery: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified delivery.
     */
    public function show(int $id): JsonResponse
    {
        $delivery = $this->deliveryRepository->findById($id);

        if (!$delivery) {
            return $this->notFoundResponse('Delivery not found');
        }

        // Load all relationships
        $delivery->load([
            'order.customer',
            'order.charity',
            'order.offer',
            'order.items.product',
            'order.items.variant',
            'order.invoice',
            'order.delivery',
        ]);

        return $this->resourceResponse(new DeliveryResource($delivery), 'Delivery retrieved successfully');
    }

    /**
     * Update the specified delivery in storage.
     */
    public function update(UpdateDeliveryRequest $request, int $id): JsonResponse
    {
        $delivery = $this->deliveryRepository->findById($id);

        if (!$delivery) {
            return $this->notFoundResponse('Delivery not found');
        }

        try {
            $updateData = $request->validated();

            // If delivery_status is being updated to 'delivered', set received_datetime if not provided
            if (isset($updateData['delivery_status']) && $updateData['delivery_status'] === 'delivered' && $delivery->delivery_status !== 'delivered') {
                if (!isset($updateData['received_datetime'])) {
                    $updateData['received_datetime'] = now();
                }
            }

            $delivery = $this->deliveryRepository->update($id, $updateData);

            if (!$delivery) {
                return $this->errorResponse('Failed to update delivery', 500);
            }

            // Reload with relationships
            $delivery = $this->deliveryRepository->findById($id);
            $delivery->load([
                'order.customer',
                'order.charity',
                'order.offer',
                'order.items.product',
                'order.items.variant',
                'order.invoice',
                'order.delivery',
            ]);

            return $this->updatedResponse(new DeliveryResource($delivery), 'Delivery updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update delivery: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified delivery from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->deliveryRepository->delete($id);

        if (!$deleted) {
            return $this->notFoundResponse('Delivery not found');
        }

        return $this->deletedResponse('Delivery deleted successfully');
    }
}


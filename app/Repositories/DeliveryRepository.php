<?php

namespace App\Repositories;

use App\Models\Delivery;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class DeliveryRepository implements DeliveryRepositoryInterface
{
    protected $model;

    public function __construct(Delivery $delivery)
    {
        $this->model = $delivery;
    }

    /**
     * Get all deliveries with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->with(['order', 'order.customer', 'order.charity']);

        // Search functionality
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('delivery_address', 'LIKE', "%{$search}%")
                  ->orWhere('block', 'LIKE', "%{$search}%")
                  ->orWhere('street', 'LIKE', "%{$search}%")
                  ->orWhere('house_number', 'LIKE', "%{$search}%")
                  ->orWhereHas('order', function ($orderQuery) use ($search) {
                      $orderQuery->where('order_number', 'LIKE', "%{$search}%")
                                  ->orWhereHas('customer', function ($customerQuery) use ($search) {
                                      $customerQuery->where('name', 'LIKE', "%{$search}%")
                                                   ->orWhere('phone', 'LIKE', "%{$search}%");
                                  });
                  });
            });
        }

        // Filter by order_id
        if (isset($filters['order_id']) && is_numeric($filters['order_id'])) {
            $query->where('order_id', $filters['order_id']);
        }

        // Filter by delivery_status
        if (isset($filters['delivery_status']) && !empty(trim($filters['delivery_status']))) {
            $query->where('delivery_status', $filters['delivery_status']);
        }

        // Filter by payment_method
        if (isset($filters['payment_method']) && !empty(trim($filters['payment_method']))) {
            $query->where('payment_method', $filters['payment_method']);
        }

        // Filter by delivery_datetime range
        if (isset($filters['delivery_date_from']) && !empty($filters['delivery_date_from'])) {
            $query->whereDate('delivery_datetime', '>=', $filters['delivery_date_from']);
        }

        if (isset($filters['delivery_date_to']) && !empty($filters['delivery_date_to'])) {
            $query->whereDate('delivery_datetime', '<=', $filters['delivery_date_to']);
        }

        // Filter by received_datetime range
        if (isset($filters['received_date_from']) && !empty($filters['received_date_from'])) {
            $query->whereDate('received_datetime', '>=', $filters['received_date_from']);
        }

        if (isset($filters['received_date_to']) && !empty($filters['received_date_to'])) {
            $query->whereDate('received_datetime', '<=', $filters['received_date_to']);
        }

        // Sort functionality
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        
        // Validate sort fields
        $allowedSortFields = ['delivery_status', 'payment_method', 'delivery_datetime', 'received_datetime', 'created_at', 'updated_at'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'created_at';
        }
        
        $allowedSortOrders = ['asc', 'desc'];
        if (!in_array($sortOrder, $allowedSortOrders)) {
            $sortOrder = 'desc';
        }

        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get all deliveries
     */
    public function getAll(): Collection
    {
        return $this->model->with(['order', 'order.customer', 'order.charity'])->get();
    }

    /**
     * Get delivery by ID
     */
    public function findById(int $id): ?Delivery
    {
        return $this->model->with(['order', 'order.customer', 'order.charity'])->find($id);
    }

    /**
     * Create a new delivery
     */
    public function create(array $data): Delivery
    {
        return $this->model->create($data);
    }

    /**
     * Update delivery
     */
    public function update(int $id, array $data): ?Delivery
    {
        $delivery = $this->findById($id);
        
        if ($delivery) {
            $delivery->update($data);
            return $delivery->fresh(['order', 'order.customer', 'order.charity']);
        }

        return null;
    }

    /**
     * Delete delivery
     */
    public function delete(int $id): bool
    {
        $delivery = $this->findById($id);
        
        if ($delivery) {
            return $delivery->delete();
        }

        return false;
    }

    /**
     * Get delivery by order ID
     */
    public function getByOrder(int $orderId): ?Delivery
    {
        return $this->model->with(['order', 'order.customer', 'order.charity'])
            ->where('order_id', $orderId)
            ->first();
    }

    /**
     * Get deliveries by status
     */
    public function getByStatus(string $status): Collection
    {
        return $this->model->with(['order', 'order.customer', 'order.charity'])
            ->where('delivery_status', $status)
            ->orderBy('delivery_datetime', 'asc')
            ->get();
    }

    /**
     * Get deliveries by payment method
     */
    public function getByPaymentMethod(string $paymentMethod): Collection
    {
        return $this->model->with(['order', 'order.customer', 'order.charity'])
            ->where('payment_method', $paymentMethod)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get pending deliveries
     */
    public function getPending(): Collection
    {
        return $this->model->with(['order', 'order.customer', 'order.charity'])
            ->where('delivery_status', 'pending')
            ->orderBy('delivery_datetime', 'asc')
            ->get();
    }

    /**
     * Get in-transit deliveries
     */
    public function getInTransit(): Collection
    {
        return $this->model->with(['order', 'order.customer', 'order.charity'])
            ->where('delivery_status', 'in_transit')
            ->orderBy('delivery_datetime', 'asc')
            ->get();
    }

    /**
     * Get delivered deliveries
     */
    public function getDelivered(): Collection
    {
        return $this->model->with(['order', 'order.customer', 'order.charity'])
            ->where('delivery_status', 'delivered')
            ->orderBy('received_datetime', 'desc')
            ->get();
    }
}


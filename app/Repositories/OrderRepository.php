<?php

namespace App\Repositories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderRepository implements OrderRepositoryInterface
{
    protected $model;

    public function __construct(Order $order)
    {
        $this->model = $order;
    }

    /**
     * Get all orders with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->with(['customer', 'charity', 'offers']);

        // Search functionality
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'LIKE', "%{$search}%")
                  ->orWhereHas('customer', function ($customerQuery) use ($search) {
                      $customerQuery->where('name', 'LIKE', "%{$search}%")
                                   ->orWhere('phone', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('charity', function ($charityQuery) use ($search) {
                      $charityQuery->where('name_en', 'LIKE', "%{$search}%")
                                  ->orWhere('name_ar', 'LIKE', "%{$search}%");
                  });
            });
        }

        // Filter by customer_id
        if (isset($filters['customer_id']) && is_numeric($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        // Filter by charity_id
        if (isset($filters['charity_id']) && is_numeric($filters['charity_id'])) {
            $query->where('charity_id', $filters['charity_id']);
        }

        // Filter by status
        if (isset($filters['status']) && !empty(trim($filters['status']))) {
            $query->where('status', $filters['status']);
        }

        // Filter by delivery_type
        if (isset($filters['delivery_type']) && !empty(trim($filters['delivery_type']))) {
            $query->where('delivery_type', $filters['delivery_type']);
        }

        // Filter by payment_method
        if (isset($filters['payment_method']) && !empty(trim($filters['payment_method']))) {
            $query->where('payment_method', $filters['payment_method']);
        }

        // Filter by offer_id (using many-to-many relationship)
        if (isset($filters['offer_id']) && is_numeric($filters['offer_id'])) {
            $query->whereHas('offers', function ($q) use ($filters) {
                $q->where('offers.id', $filters['offer_id']);
            });
        }

        // Filter by total_amount range
        if (isset($filters['min_amount']) && is_numeric($filters['min_amount'])) {
            $query->where('total_amount', '>=', $filters['min_amount']);
        }

        if (isset($filters['max_amount']) && is_numeric($filters['max_amount'])) {
            $query->where('total_amount', '<=', $filters['max_amount']);
        }

        // Filter by date range
        if (isset($filters['date_from']) && !empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to']) && !empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Sort functionality
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        
        // Validate sort fields
        $allowedSortFields = ['order_number', 'status', 'total_amount', 'delivery_type', 'created_at', 'updated_at'];
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
     * Get all orders
     */
    public function getAll(): Collection
    {
        return $this->model->with(['customer', 'charity', 'offers'])->get();
    }

    /**
     * Get order by ID
     */
    public function findById(int $id): ?Order
    {
        return $this->model->with(['customer', 'charity', 'offers'])->find($id);
    }

    /**
     * Get order by order number
     */
    public function findByOrderNumber(string $orderNumber): ?Order
    {
        return $this->model->with(['customer', 'charity', 'offers'])
            ->where('order_number', $orderNumber)
            ->first();
    }

    /**
     * Create a new order
     */
    public function create(array $data): Order
    {
        return $this->model->create($data);
    }

    /**
     * Update order
     */
    public function update(int $id, array $data): ?Order
    {
        $order = $this->findById($id);
        
        if ($order) {
            $order->update($data);
            return $order->fresh(['customer', 'charity', 'offers']);
        }

        return null;
    }

    /**
     * Delete order
     */
    public function delete(int $id): bool
    {
        $order = $this->findById($id);
        
        if ($order) {
            return $order->delete();
        }

        return false;
    }

    /**
     * Get orders by customer ID
     */
    public function getByCustomer(int $customerId): Collection
    {
        return $this->model->with(['customer', 'charity', 'offers'])
            ->where('customer_id', $customerId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get orders by charity ID
     */
    public function getByCharity(int $charityId): Collection
    {
        return $this->model->with(['customer', 'charity', 'offers'])
            ->where('charity_id', $charityId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get orders by status
     */
    public function getByStatus(string $status): Collection
    {
        return $this->model->with(['customer', 'charity', 'offers'])
            ->where('status', $status)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}


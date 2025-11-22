<?php

namespace App\Repositories;

use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderItemRepository implements OrderItemRepositoryInterface
{
    protected $model;

    public function __construct(OrderItem $orderItem)
    {
        $this->model = $orderItem;
    }

    /**
     * Get all order items with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->with(['order', 'product', 'variant']);

        // Search functionality
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%")
                  ->orWhereHas('order', function ($orderQuery) use ($search) {
                      $orderQuery->where('order_number', 'LIKE', "%{$search}%");
                  });
            });
        }

        // Filter by order_id
        if (isset($filters['order_id']) && is_numeric($filters['order_id'])) {
            $query->where('order_id', $filters['order_id']);
        }

        // Filter by product_id
        if (isset($filters['product_id']) && is_numeric($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        // Filter by variant_id
        if (isset($filters['variant_id']) && is_numeric($filters['variant_id'])) {
            $query->where('variant_id', $filters['variant_id']);
        }

        // Filter by is_offer
        if (isset($filters['is_offer']) && $filters['is_offer'] !== '') {
            $query->where('is_offer', filter_var($filters['is_offer'], FILTER_VALIDATE_BOOLEAN));
        }

        // Filter by total_price range
        if (isset($filters['min_price']) && is_numeric($filters['min_price'])) {
            $query->where('total_price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price']) && is_numeric($filters['max_price'])) {
            $query->where('total_price', '<=', $filters['max_price']);
        }

        // Filter by quantity range
        if (isset($filters['min_quantity']) && is_numeric($filters['min_quantity'])) {
            $query->where('quantity', '>=', $filters['min_quantity']);
        }

        if (isset($filters['max_quantity']) && is_numeric($filters['max_quantity'])) {
            $query->where('quantity', '<=', $filters['max_quantity']);
        }

        // Sort functionality
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        
        // Validate sort fields
        $allowedSortFields = ['name', 'sku', 'quantity', 'unit_price', 'total_price', 'is_offer', 'created_at', 'updated_at'];
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
     * Get all order items
     */
    public function getAll(): Collection
    {
        return $this->model->with(['order', 'product', 'variant'])->get();
    }

    /**
     * Get order item by ID
     */
    public function findById(int $id): ?OrderItem
    {
        return $this->model->with(['order', 'product', 'variant'])->find($id);
    }

    /**
     * Create a new order item
     */
    public function create(array $data): OrderItem
    {
        return $this->model->create($data);
    }

    /**
     * Update order item
     */
    public function update(int $id, array $data): ?OrderItem
    {
        $orderItem = $this->findById($id);
        
        if ($orderItem) {
            $orderItem->update($data);
            return $orderItem->fresh(['order', 'product', 'variant']);
        }

        return null;
    }

    /**
     * Delete order item
     */
    public function delete(int $id): bool
    {
        $orderItem = $this->findById($id);
        
        if ($orderItem) {
            return $orderItem->delete();
        }

        return false;
    }

    /**
     * Get order items by order ID
     */
    public function getByOrder(int $orderId): Collection
    {
        return $this->model->with(['order', 'product', 'variant'])
            ->where('order_id', $orderId)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get order items by product ID
     */
    public function getByProduct(int $productId): Collection
    {
        return $this->model->with(['order', 'product', 'variant'])
            ->where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get order items that are offers
     */
    public function getOfferItems(): Collection
    {
        return $this->model->with(['order', 'product', 'variant'])
            ->where('is_offer', true)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}


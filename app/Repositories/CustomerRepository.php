<?php

namespace App\Repositories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomerRepository implements CustomerRepositoryInterface
{
    protected $model;

    public function __construct(Customer $customer)
    {
        $this->model = $customer;
    }

    /**
     * Get all customers with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        // Search functionality
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // Filter by status
        if (isset($filters['status']) && $filters['status'] !== '') {
            if ($filters['status'] === 'active') {
                $query->where('is_active', true);
            } elseif ($filters['status'] === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Filter by points range
        if (isset($filters['min_points']) && is_numeric($filters['min_points'])) {
            $query->where('points', '>=', $filters['min_points']);
        }

        if (isset($filters['max_points']) && is_numeric($filters['max_points'])) {
            $query->where('points', '<=', $filters['max_points']);
        }

        // Sort functionality
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        
        // Validate sort fields
        $allowedSortFields = ['name', 'phone', 'email', 'points', 'is_active', 'created_at', 'updated_at'];
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
     * Get all customers
     */
    public function getAll(): Collection
    {
        return $this->model->all();
    }

    /**
     * Get customer by ID
     */
    public function findById(int $id): ?Customer
    {
        return $this->model->find($id);
    }

    /**
     * Create a new customer
     */
    public function create(array $data): Customer
    {
        return $this->model->create($data);
    }

    /**
     * Update customer
     */
    public function update(int $id, array $data): ?Customer
    {
        $customer = $this->findById($id);
        
        if ($customer) {
            $customer->update($data);
            return $customer->fresh();
        }

        return null;
    }

    /**
     * Delete customer
     */
    public function delete(int $id): bool
    {
        $customer = $this->findById($id);
        
        if ($customer) {
            return $customer->delete();
        }

        return false;
    }

    /**
     * Get active customers only
     */
    public function getActive(): Collection
    {
        return $this->model->active()->get();
    }

    /**
     * Get inactive customers only
     */
    public function getInactive(): Collection
    {
        return $this->model->inactive()->get();
    }
}

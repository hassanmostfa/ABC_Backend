<?php

namespace App\Repositories;

use App\Models\CustomerAddress;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomerAddressRepository implements CustomerAddressRepositoryInterface
{
    protected $model;

    public function __construct(CustomerAddress $customerAddress)
    {
        $this->model = $customerAddress;
    }

    /**
     * Get all customer addresses with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->with(['customer', 'country', 'governorate', 'area']);

        // Search functionality
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('street', 'LIKE', "%{$search}%")
                  ->orWhere('house', 'LIKE', "%{$search}%")
                  ->orWhere('block', 'LIKE', "%{$search}%")
                  ->orWhere('floor', 'LIKE', "%{$search}%")
                  ->orWhereHas('customer', function ($customerQuery) use ($search) {
                      $customerQuery->where('name', 'LIKE', "%{$search}%")
                                   ->orWhere('phone', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('country', function ($countryQuery) use ($search) {
                      $countryQuery->where('name_en', 'LIKE', "%{$search}%")
                                  ->orWhere('name_ar', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('governorate', function ($governorateQuery) use ($search) {
                      $governorateQuery->where('name_en', 'LIKE', "%{$search}%")
                                      ->orWhere('name_ar', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('area', function ($areaQuery) use ($search) {
                      $areaQuery->where('name_en', 'LIKE', "%{$search}%")
                               ->orWhere('name_ar', 'LIKE', "%{$search}%");
                  });
            });
        }

        // Filter by customer_id
        if (isset($filters['customer_id']) && is_numeric($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        // Filter by country_id
        if (isset($filters['country_id']) && is_numeric($filters['country_id'])) {
            $query->where('country_id', $filters['country_id']);
        }

        // Filter by governorate_id
        if (isset($filters['governorate_id']) && is_numeric($filters['governorate_id'])) {
            $query->where('governorate_id', $filters['governorate_id']);
        }

        // Filter by area_id
        if (isset($filters['area_id']) && is_numeric($filters['area_id'])) {
            $query->where('area_id', $filters['area_id']);
        }

        // Sort functionality
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        
        // Validate sort fields
        $allowedSortFields = ['street', 'house', 'block', 'floor', 'created_at', 'updated_at'];
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
     * Get all customer addresses
     */
    public function getAll(): Collection
    {
        return $this->model->with(['customer', 'country', 'governorate', 'area'])->get();
    }

    /**
     * Get customer address by ID
     */
    public function findById(int $id): ?CustomerAddress
    {
        return $this->model->with(['customer', 'country', 'governorate', 'area'])->find($id);
    }

    /**
     * Create a new customer address
     */
    public function create(array $data): CustomerAddress
    {
        return $this->model->create($data);
    }

    /**
     * Update customer address
     */
    public function update(int $id, array $data): ?CustomerAddress
    {
        $address = $this->findById($id);
        
        if ($address) {
            $address->update($data);
            return $address->fresh(['customer', 'country', 'governorate', 'area']);
        }

        return null;
    }

    /**
     * Delete customer address
     */
    public function delete(int $id): bool
    {
        $address = $this->findById($id);
        
        if ($address) {
            return $address->delete();
        }

        return false;
    }

    /**
     * Get addresses by customer ID
     */
    public function getByCustomer(int $customerId): Collection
    {
        return $this->model->with(['customer', 'country', 'governorate', 'area'])
            ->where('customer_id', $customerId)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}


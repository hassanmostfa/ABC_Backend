<?php

namespace App\Repositories\Services;

use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ServiceRepository implements ServiceRepositoryInterface
{
    protected $model;

    public function __construct(Service $service)
    {
        $this->model = $service;
    }

    /**
     * Get all services with pagination, search and filters.
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('service_provider_email', 'LIKE', "%{$search}%");
            });
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $query->orderByDesc('created_at');

        return $query->paginate($perPage);
    }

    /**
     * Get all services.
     */
    public function getAll(): Collection
    {
        return $this->model->orderByDesc('created_at')->get();
    }

    /**
     * Get active services without pagination.
     */
    public function getActive(): Collection
    {
        return $this->model->query()
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get service by ID.
     */
    public function findById(int $id): ?Service
    {
        return $this->model->find($id);
    }

    /**
     * Create a new service.
     */
    public function create(array $data): Service
    {
        return $this->model->create($data);
    }

    /**
     * Update service.
     */
    public function update(int $id, array $data): ?Service
    {
        $service = $this->model->find($id);

        if (!$service) {
            return null;
        }

        $service->update($data);

        return $service;
    }

    /**
     * Delete service.
     */
    public function delete(int $id): bool
    {
        $service = $this->model->find($id);

        if (!$service) {
            return false;
        }

        return $service->delete();
    }
}

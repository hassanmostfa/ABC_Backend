<?php

namespace App\Repositories\ProductPackagings;

use App\Models\ProductPackaging;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductPackagingRepository implements ProductPackagingRepositoryInterface
{
    public function __construct(protected ProductPackaging $model)
    {
    }

    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where('name', 'LIKE', "%{$search}%");
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            if ($filters['status'] === 'active') {
                $query->where('is_active', true);
            } elseif ($filters['status'] === 'inactive') {
                $query->where('is_active', false);
            }
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    public function getAll(): Collection
    {
        return $this->model->orderBy('name')->get();
    }

    public function getActive(): Collection
    {
        return $this->model->active()->orderBy('name')->get();
    }

    public function findById(int $id): ?ProductPackaging
    {
        return $this->model->find($id);
    }

    public function create(array $data): ProductPackaging
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data): ?ProductPackaging
    {
        $packaging = $this->model->find($id);

        if (!$packaging) {
            return null;
        }

        $packaging->update($data);

        return $packaging;
    }

    public function delete(int $id): bool
    {
        $packaging = $this->model->find($id);

        if (!$packaging) {
            return false;
        }

        return (bool) $packaging->delete();
    }
}

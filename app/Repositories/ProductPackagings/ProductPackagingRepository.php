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

        $this->applySubcategoryFilter($query, $filters);

        return $query->orderBy('name')->paginate($perPage);
    }

    public function getAll(array $filters = []): Collection
    {
        $query = $this->model->query();

        $this->applySubcategoryFilter($query, $filters);

        return $query->orderBy('name')->get();
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

    /**
     * Limit packagings to those used by product variants in the given subcategory.
     */
    private function applySubcategoryFilter($query, array $filters): void
    {
        if (!isset($filters['subcategory_id']) || !is_numeric($filters['subcategory_id'])) {
            return;
        }

        $subcategoryId = (int) $filters['subcategory_id'];

        $query->whereHas('productVariants.product', function ($productQuery) use ($subcategoryId) {
            $productQuery->where('subcategory_id', $subcategoryId);
        });
    }
}

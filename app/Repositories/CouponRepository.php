<?php

namespace App\Repositories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CouponRepository implements CouponRepositoryInterface
{
    public function __construct(protected Coupon $model)
    {
    }

    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->newQuery();

        if (isset($filters['search']) && $filters['search'] !== '') {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('code', 'LIKE', "%{$search}%")
                    ->orWhere('name', 'LIKE', "%{$search}%");
            });
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $isActive = filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }
        }

        return $query->with('productVariants.product')->orderByDesc('created_at')->paginate($perPage);
    }

    public function getAll(): Collection
    {
        return $this->model->newQuery()->orderByDesc('created_at')->get();
    }

    public function findById(int $id): ?Coupon
    {
        return $this->model->newQuery()->find($id);
    }

    public function findByCode(string $code): ?Coupon
    {
        return $this->model->newQuery()->where('code', strtoupper(trim($code)))->first();
    }

    public function create(array $data): Coupon
    {
        $data['code'] = strtoupper(trim((string) $data['code']));
        return $this->model->newQuery()->create($data);
    }

    public function update(int $id, array $data): ?Coupon
    {
        $coupon = $this->findById($id);
        if (!$coupon) {
            return null;
        }

        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim((string) $data['code']));
        }

        $coupon->update($data);
        return $coupon->fresh();
    }

    public function delete(int $id): bool
    {
        $coupon = $this->findById($id);
        if (!$coupon) {
            return false;
        }

        return (bool) $coupon->delete();
    }
}

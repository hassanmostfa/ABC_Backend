<?php

namespace App\Repositories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface CouponRepositoryInterface
{
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function getAll(): Collection;

    public function findById(int $id): ?Coupon;

    public function findByCode(string $code): ?Coupon;

    public function create(array $data): Coupon;

    public function update(int $id, array $data): ?Coupon;

    public function delete(int $id): bool;
}

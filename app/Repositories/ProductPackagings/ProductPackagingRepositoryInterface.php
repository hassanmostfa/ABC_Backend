<?php

namespace App\Repositories\ProductPackagings;

use App\Models\ProductPackaging;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProductPackagingRepositoryInterface
{
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function getAll(): Collection;

    public function getActive(): Collection;

    public function findById(int $id): ?ProductPackaging;

    public function create(array $data): ProductPackaging;

    public function update(int $id, array $data): ?ProductPackaging;

    public function delete(int $id): bool;
}

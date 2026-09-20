<?php

namespace App\Repositories\WalletChargeOffers;

use App\Models\WalletChargeOffer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface WalletChargeOfferRepositoryInterface
{
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function getAll(): Collection;

    public function getActive(): Collection;

    public function findById(int $id): ?WalletChargeOffer;

    public function findActiveById(int $id): ?WalletChargeOffer;

    public function create(array $data): WalletChargeOffer;

    public function update(int $id, array $data): ?WalletChargeOffer;

    public function delete(int $id): bool;
}

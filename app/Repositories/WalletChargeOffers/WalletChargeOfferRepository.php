<?php

namespace App\Repositories\WalletChargeOffers;

use App\Models\WalletChargeOffer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class WalletChargeOfferRepository implements WalletChargeOfferRepositoryInterface
{
    public function __construct(protected WalletChargeOffer $model)
    {
    }

    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        if (!empty($filters['search']) && is_numeric($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('charge_amount', $search)
                    ->orWhere('get_amount', $search);
            });
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $sortBy = $filters['sort_by'] ?? 'sort_order';
        $sortOrder = $filters['sort_order'] ?? 'asc';

        $query->orderBy($sortBy, $sortOrder)
            ->orderBy('charge_amount', 'asc')
            ->orderBy('id', 'desc');

        return $query->paginate($perPage);
    }

    public function getAll(): Collection
    {
        return $this->model->ordered()->get();
    }

    public function getActive(): Collection
    {
        return $this->model->active()->ordered()->get();
    }

    public function findById(int $id): ?WalletChargeOffer
    {
        return $this->model->find($id);
    }

    public function findActiveById(int $id): ?WalletChargeOffer
    {
        return $this->model->active()->find($id);
    }

    public function create(array $data): WalletChargeOffer
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data): ?WalletChargeOffer
    {
        $offer = $this->model->find($id);

        if (!$offer) {
            return null;
        }

        $offer->update($data);

        return $offer;
    }

    public function delete(int $id): bool
    {
        $offer = $this->model->find($id);

        if (!$offer) {
            return false;
        }

        return $offer->delete();
    }
}

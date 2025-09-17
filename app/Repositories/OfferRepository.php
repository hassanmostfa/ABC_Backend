<?php

namespace App\Repositories;

use App\Models\Offer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class OfferRepository implements OfferRepositoryInterface
{
    protected $model;

    public function __construct(Offer $offer)
    {
        $this->model = $offer;
    }

    /**
     * Get all offers with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->with([
            'conditions.product',
            'conditions.productVariant',
            'rewards.product',
            'rewards.productVariant',
            'charity'
        ]);

        // Apply type filter if provided
        if (isset($filters['type']) && !empty(trim($filters['type']))) {
            $type = trim($filters['type']);
            $query->where('type', 'like', "%{$type}%");
        }

        // Default sorting by created_at desc
        $query->orderBy('created_at', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get all offers
     */
    public function getAll(): Collection
    {
        return $this->model->with([
            'conditions.product',
            'conditions.productVariant',
            'rewards.product',
            'rewards.productVariant',
            'charity'
        ])->get();
    }

    /**
     * Get offer by ID
     */
    public function findById(int $id): ?Offer
    {
        return $this->model->with([
            'conditions.product',
            'conditions.productVariant',
            'rewards.product',
            'rewards.productVariant',
            'charity'
        ])->find($id);
    }

    /**
     * Create a new offer
     */
    public function create(array $data): Offer
    {
        return $this->model->create($data);
    }

    /**
     * Update offer
     */
    public function update(int $id, array $data): ?Offer
    {
        $offer = $this->model->find($id);
        
        if (!$offer) {
            return null;
        }

        $offer->update($data);
        return $offer->load([
            'conditions.product',
            'conditions.productVariant',
            'rewards.product',
            'rewards.productVariant',
            'charity'
        ]);
    }

    /**
     * Delete offer
     */
    public function delete(int $id): bool
    {
        $offer = $this->model->find($id);
        
        if (!$offer) {
            return false;
        }

        return $offer->delete();
    }
}

<?php

namespace App\Repositories;

use App\Models\Offer;
use App\Models\ProductVariant;
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

        // Filter by active offers only (for mobile API)
        if (isset($filters['active_only']) && $filters['active_only'] === true) {
            $query->active();
        }

        // Search functionality
        if (isset($filters['search']) && !empty(trim($filters['search']))) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('title_en', 'LIKE', "%{$search}%")
                  ->orWhere('title_ar', 'LIKE', "%{$search}%")
                  ->orWhere('description_en', 'LIKE', "%{$search}%")
                  ->orWhere('description_ar', 'LIKE', "%{$search}%");
            });
        }

        // Filter by type
        if (isset($filters['type']) && !empty(trim($filters['type']))) {
            $type = trim($filters['type']);
            $query->where('type', $type);
        }

        // Filter by category_id (through conditions or rewards products)
        if (isset($filters['category_id']) && is_numeric($filters['category_id'])) {
            $categoryId = $filters['category_id'];
            $query->where(function ($q) use ($categoryId) {
                // Search in conditions products
                $q->whereHas('conditions.product', function ($productQuery) use ($categoryId) {
                    $productQuery->where('category_id', $categoryId);
                })
                // Or search in rewards products
                ->orWhereHas('rewards.product', function ($productQuery) use ($categoryId) {
                    $productQuery->where('category_id', $categoryId);
                });
            });
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

    /**
     * Get offers related to a product variant (where variant appears in conditions or rewards).
     * Matches: (1) specific variant, (2) product with null variant, or (3) any variant of same product.
     */
    public function getByProductVariantId(int $productVariantId, bool $activeOnly = true): Collection
    {
        $variant = ProductVariant::find($productVariantId);
        $productId = $variant?->product_id;

        $query = $this->model->with([
            'conditions.product',
            'conditions.productVariant',
            'rewards.product',
            'rewards.productVariant',
            'charity'
        ])->where(function ($q) use ($productVariantId, $productId) {
            $conditionMatch = function ($subQuery) use ($productVariantId, $productId) {
                $subQuery->where('product_variant_id', $productVariantId);
                if ($productId) {
                    $subQuery->orWhere('product_id', $productId);
                }
            };

            $q->whereHas('conditions', $conditionMatch)
              ->orWhereHas('rewards', $conditionMatch);
        });

        if ($activeOnly) {
            $query->active();
        }

        return $query->orderBy('created_at', 'desc')->get();
    }
}

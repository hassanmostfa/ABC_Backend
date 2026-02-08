<?php

namespace App\Repositories;

use App\Models\Faq;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class FaqRepository implements FaqRepositoryInterface
{
    protected $model;

    public function __construct(Faq $faq)
    {
        $this->model = $faq;
    }

    /**
     * Get all FAQs with pagination, search and filters.
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('question_en', 'like', "%{$search}%")
                    ->orWhere('question_ar', 'like', "%{$search}%")
                    ->orWhere('answer_en', 'like', "%{$search}%")
                    ->orWhere('answer_ar', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $sortBy = $filters['sort_by'] ?? 'sort_order';
        $sortOrder = $filters['sort_order'] ?? 'asc';

        $query->orderBy($sortBy, $sortOrder)
            ->orderBy('id', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get all FAQs.
     */
    public function getAll(): Collection
    {
        return $this->model->orderBy('sort_order', 'asc')->get();
    }

    /**
     * Get FAQ by ID.
     */
    public function findById(int $id): ?Faq
    {
        return $this->model->find($id);
    }

    /**
     * Create a new FAQ.
     */
    public function create(array $data): Faq
    {
        return $this->model->create($data);
    }

    /**
     * Update FAQ.
     */
    public function update(int $id, array $data): ?Faq
    {
        $faq = $this->model->find($id);

        if (!$faq) {
            return null;
        }

        $faq->update($data);

        return $faq;
    }

    /**
     * Delete FAQ.
     */
    public function delete(int $id): bool
    {
        $faq = $this->model->find($id);

        if (!$faq) {
            return false;
        }

        return $faq->delete();
    }
}

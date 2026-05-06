<?php

namespace App\Repositories;

use App\Models\Feedback;
use Illuminate\Pagination\LengthAwarePaginator;

class FeedbackRepository implements FeedbackRepositoryInterface
{
    public function __construct(protected Feedback $model)
    {
    }

    /**
     * Get all feedbacks with pagination.
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->newQuery()->with(['customer', 'order']);

        if (isset($filters['rating']) && $filters['rating'] !== null) {
            $query->where('rating', (int) $filters['rating']);
        }

        if (isset($filters['order_id']) && $filters['order_id'] !== null) {
            $query->where('order_id', (int) $filters['order_id']);
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    /**
     * Create feedback.
     */
    public function create(array $data): Feedback
    {
        return $this->model->create($data);
    }
}


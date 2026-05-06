<?php

namespace App\Repositories;

use App\Models\Feedback;
use Illuminate\Pagination\LengthAwarePaginator;

interface FeedbackRepositoryInterface
{
    /**
     * Get all feedbacks with pagination.
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Create feedback.
     */
    public function create(array $data): Feedback;
}


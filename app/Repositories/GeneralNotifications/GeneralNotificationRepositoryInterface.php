<?php

namespace App\Repositories\GeneralNotifications;

use App\Models\GeneralNotification;
use Illuminate\Pagination\LengthAwarePaginator;

interface GeneralNotificationRepositoryInterface
{
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findById(int $id): ?GeneralNotification;

    public function create(array $data): GeneralNotification;
}

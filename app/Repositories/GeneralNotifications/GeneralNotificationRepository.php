<?php

namespace App\Repositories\GeneralNotifications;

use App\Models\GeneralNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class GeneralNotificationRepository implements GeneralNotificationRepositoryInterface
{
    public function __construct(
        protected GeneralNotification $model
    ) {}

    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->baseQuery();

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['offer_id'])) {
            $query->where('offer_id', $filters['offer_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title_en', 'LIKE', "%{$search}%")
                    ->orWhere('title_ar', 'LIKE', "%{$search}%")
                    ->orWhere('message_en', 'LIKE', "%{$search}%")
                    ->orWhere('message_ar', 'LIKE', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->orderBy('id', 'desc')->paginate($perPage);
    }

    public function findById(int $id): ?GeneralNotification
    {
        return $this->baseQuery()->find($id);
    }

    public function create(array $data): GeneralNotification
    {
        return $this->model->create($data);
    }

    protected function baseQuery(): Builder
    {
        return $this->model->query()
            ->with(['admin:id,name', 'offer:id,title_en,title_ar,image'])
            ->withCount(['notifications as read_count' => fn ($q) => $q->where('is_read', true)]);
    }
}

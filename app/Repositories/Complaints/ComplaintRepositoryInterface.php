<?php

namespace App\Repositories\Complaints;

use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\ComplaintCommunication;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;

interface ComplaintRepositoryInterface
{
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findById(int $id, bool $withRelations = true): ?Complaint;

    /**
     * @return array{success: bool, message: string, complaint?: Complaint}
     */
    public function create(array $data): array;

    /**
     * @return array{success: bool, message: string, complaint?: Complaint}
     */
    public function update(int $id, array $data): array;

    /**
     * @return array{success: bool, message: string, complaint?: Complaint}
     */
    public function updateStatus(int $id, string $status, ?string $notes = null): array;

    /**
     * @return array{success: bool, message: string, complaint?: Complaint}
     */
    public function qaSignOff(int $id, ?string $notes = null): array;

    public function storeAttachment(
        int $id,
        UploadedFile $file,
        ?string $attachmentType = 'other',
        ?string $notes = null
    ): ?ComplaintAttachment;

    /**
     * @return array{success: bool, message: string, communication?: ComplaintCommunication}
     */
    public function logCommunication(int $id, array $data): array;

    public function getTrends(array $filters = []): array;

    public function sendApproachingTargetReminders(): int;

    /**
     * @return list<string>
     */
    public function defaultRelations(): array;
}

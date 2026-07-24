<?php

namespace App\Repositories\Complaints;

use App\Enums\ComplaintStatus;
use App\Mail\ComplaintAcknowledgementMail;
use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\ComplaintAudit;
use App\Models\ComplaintCommunication;
use App\Models\ComplaintStatusHistory;
use App\Traits\ManagesFileUploads;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ComplaintRepository implements ComplaintRepositoryInterface
{
    use ManagesFileUploads;

    public const IMMUTABLE_FIELDS = ['description', 'reference_number', 'complaint_type'];

    public function __construct(protected Complaint $model)
    {
    }

    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->newQuery()
            ->with(['customer', 'product', 'createdBy', 'assignedTo'])
            ->orderByDesc('id');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['complaint_type'])) {
            $query->where('complaint_type', $filters['complaint_type']);
        }
        if (!empty($filters['receiving_channel'])) {
            $query->where('receiving_channel', $filters['receiving_channel']);
        }
        if (!empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }
        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }
        if (!empty($filters['batch_number'])) {
            $query->where('batch_number', $filters['batch_number']);
        }
        if (!empty($filters['department'])) {
            $query->where('department', $filters['department']);
        }
        if (!empty($filters['non_food_category'])) {
            $query->where('non_food_category', $filters['non_food_category']);
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('complaint_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('complaint_date', '<=', $filters['date_to']);
        }
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhere('batch_number', 'like', "%{$search}%")
                    ->orWhere('product_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    public function findById(int $id, bool $withRelations = true): ?Complaint
    {
        $query = $this->model->newQuery();

        if ($withRelations) {
            $query->with($this->defaultRelations());
        }

        return $query->find($id);
    }

    public function generateReferenceNumber(): string
    {
        $prefix = 'REF-' . now()->format('ym') . '-';

        $last = $this->model->newQuery()
            ->where('reference_number', 'like', $prefix . '%')
            ->orderByDesc('reference_number')
            ->value('reference_number');

        $next = 1;
        if ($last) {
            $seq = (int) substr($last, -4);
            $next = $seq + 1;
        }

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function create(array $data): array
    {
        try {
            DB::beginTransaction();

            $adminId = Auth::id();
            $now = now();

            $complaint = $this->model->create([
                'reference_number' => $this->generateReferenceNumber(),
                'complaint_date' => $data['complaint_date'] ?? $now->toDateString(),
                'complaint_time' => $data['complaint_time'] ?? $now->format('H:i:s'),
                'receiving_channel' => $data['receiving_channel'],
                'complaint_type' => $data['complaint_type'],
                'status' => ComplaintStatus::Open,
                'severity' => $data['severity'] ?? null,
                'description' => $data['description'],
                'customer_id' => $data['customer_id'] ?? null,
                'customer_name' => $data['customer_name'] ?? null,
                'customer_email' => $data['customer_email'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'order_id' => $data['order_id'] ?? null,
                'product_id' => $data['product_id'] ?? null,
                'product_name' => $data['product_name'] ?? null,
                'batch_number' => $data['batch_number'] ?? null,
                'department' => $data['department'] ?? null,
                'created_by' => $adminId,
                'assigned_to' => $data['assigned_to'] ?? null,
                'retention_until' => $now->copy()->addYears(5)->toDateString(),
                'food_safety_indicators' => $data['food_safety_indicators'] ?? null,
                'product_retention_status' => $data['product_retention_status'] ?? null,
                'qa_notified_at' => $data['qa_notified_at'] ?? null,
                'qa_notify_method' => $data['qa_notify_method'] ?? null,
                'qa_contact_name' => $data['qa_contact_name'] ?? null,
                'non_food_category' => $data['non_food_category'] ?? null,
                'forwarded_department' => $data['forwarded_department'] ?? null,
                'responsible_person_name' => $data['responsible_person_name'] ?? null,
                'expected_response_date' => $data['expected_response_date'] ?? null,
                'root_cause' => $data['root_cause'] ?? null,
                'non_food_corrective_action' => $data['non_food_corrective_action'] ?? null,
                'investigation_findings' => $data['investigation_findings'] ?? null,
                'immediate_action' => $data['immediate_action'] ?? null,
                'immediate_action_target_date' => $data['immediate_action_target_date'] ?? null,
                'corrective_action' => $data['corrective_action'] ?? null,
                'corrective_action_target_date' => $data['corrective_action_target_date'] ?? null,
                'preventive_action' => $data['preventive_action'] ?? null,
                'preventive_action_target_date' => $data['preventive_action_target_date'] ?? null,
                'consumer_health_risk' => (bool) ($data['consumer_health_risk'] ?? false),
                'containment_action' => $data['containment_action'] ?? null,
                'containment_notes' => $data['containment_notes'] ?? null,
            ]);

            ComplaintStatusHistory::create([
                'complaint_id' => $complaint->id,
                'from_status' => null,
                'to_status' => ComplaintStatus::Open->value,
                'changed_by' => $adminId,
                'notes' => 'Complaint registered',
            ]);

            if (!empty($data['attachments']) && is_array($data['attachments'])) {
                foreach ($data['attachments'] as $file) {
                    if ($file instanceof UploadedFile) {
                        $this->storeAttachmentForComplaint($complaint, $file, $data['attachment_type'] ?? 'other');
                    }
                }
            }

            DB::commit();

            $complaint->load([
                'customer', 'product', 'order', 'createdBy',
                'attachments', 'statusHistories', 'communications',
            ]);

            if ($complaint->isFoodSafety()) {
                $this->escalateToQa($complaint);
            }

            $this->sendAcknowledgement($complaint);
            $this->detectRepeatComplaint($complaint);

            logAdminActivity('created', 'Complaint', $complaint->id, [
                'reference_number' => $complaint->reference_number,
            ]);

            return [
                'success' => true,
                'message' => 'Complaint registered successfully',
                'complaint' => $complaint,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function update(int $id, array $data): array
    {
        $complaint = $this->findById($id, false);
        if (!$complaint) {
            return ['success' => false, 'message' => 'Complaint not found'];
        }

        if ($complaint->status === ComplaintStatus::Closed) {
            return ['success' => false, 'message' => 'Closed complaints cannot be edited'];
        }

        foreach (self::IMMUTABLE_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                unset($data[$field]);
            }
        }

        if (array_key_exists('consumer_health_risk', $data)
            && $data['consumer_health_risk']
            && empty($data['containment_action'])
            && empty($complaint->containment_action)
        ) {
            return [
                'success' => false,
                'message' => 'Containment action (hold / recall / regulatory_notification) is required when consumer health risk is YES',
            ];
        }

        $adminId = Auth::id();
        $changes = [];

        foreach ($data as $field => $newValue) {
            if (!in_array($field, $complaint->getFillable(), true)) {
                continue;
            }

            $oldValue = $complaint->getAttribute($field);
            $normalizedOld = $this->normalizeAuditValue($oldValue);
            $normalizedNew = $this->normalizeAuditValue($newValue);

            if ($normalizedOld === $normalizedNew) {
                continue;
            }

            $changes[$field] = ['old' => $normalizedOld, 'new' => $normalizedNew];
            $complaint->setAttribute($field, $newValue);
        }

        if (empty($changes)) {
            return [
                'success' => true,
                'message' => 'No changes detected',
                'complaint' => $complaint->fresh($this->defaultRelations()),
            ];
        }

        $complaint->save();

        foreach ($changes as $field => $values) {
            ComplaintAudit::create([
                'complaint_id' => $complaint->id,
                'field_name' => $field,
                'old_value' => $values['old'],
                'new_value' => $values['new'],
                'changed_by' => $adminId,
                'created_at' => now(),
            ]);
        }

        if (!empty($changes['consumer_health_risk']) && filter_var($changes['consumer_health_risk']['new'], FILTER_VALIDATE_BOOLEAN)) {
            $this->notifyContainment($complaint);
        }

        logAdminActivity('updated', 'Complaint', $complaint->id, ['fields' => array_keys($changes)]);

        return [
            'success' => true,
            'message' => 'Complaint updated successfully',
            'complaint' => $complaint->fresh($this->defaultRelations()),
        ];
    }

    public function updateStatus(int $id, string $status, ?string $notes = null): array
    {
        $complaint = $this->findById($id, false);
        if (!$complaint) {
            return ['success' => false, 'message' => 'Complaint not found'];
        }

        $targetStatus = ComplaintStatus::from($status);

        if ($complaint->status === $targetStatus) {
            return [
                'success' => true,
                'message' => 'Complaint is already in this status',
                'complaint' => $complaint->fresh($this->defaultRelations()),
            ];
        }

        if (!$complaint->canTransitionTo($targetStatus)) {
            return [
                'success' => false,
                'message' => "Invalid status transition from {$complaint->status->value} to {$targetStatus->value}. Allowed: "
                    . implode(', ', $complaint->status->nextStatusValues()),
            ];
        }

        if ($targetStatus === ComplaintStatus::Closed) {
            if ($complaint->isFoodSafety() && !$complaint->qa_signed_off) {
                return [
                    'success' => false,
                    'message' => 'Food safety complaints cannot be closed without QA sign-off',
                ];
            }
        }

        $adminId = Auth::id();
        $from = $complaint->status;

        $complaint->status = $targetStatus;
        if ($targetStatus === ComplaintStatus::Closed) {
            $complaint->closed_by = $adminId;
            $complaint->closed_at = now();
        }
        $complaint->save();

        ComplaintStatusHistory::create([
            'complaint_id' => $complaint->id,
            'from_status' => $from->value,
            'to_status' => $targetStatus->value,
            'changed_by' => $adminId,
            'notes' => $notes,
        ]);

        ComplaintAudit::create([
            'complaint_id' => $complaint->id,
            'field_name' => 'status',
            'old_value' => $from->value,
            'new_value' => $targetStatus->value,
            'changed_by' => $adminId,
            'created_at' => now(),
        ]);

        logAdminActivity('status_changed', 'Complaint', $complaint->id, [
            'from' => $from->value,
            'to' => $targetStatus->value,
        ]);

        return [
            'success' => true,
            'message' => 'Complaint status updated successfully',
            'complaint' => $complaint->fresh($this->defaultRelations()),
        ];
    }

    public function qaSignOff(int $id, ?string $notes = null): array
    {
        $complaint = $this->findById($id, false);
        if (!$complaint) {
            return ['success' => false, 'message' => 'Complaint not found'];
        }

        if (!$complaint->isFoodSafety()) {
            return ['success' => false, 'message' => 'QA sign-off applies only to food safety complaints'];
        }

        if ($complaint->qa_signed_off) {
            return [
                'success' => true,
                'message' => 'Already signed off by QA',
                'complaint' => $complaint->fresh($this->defaultRelations()),
            ];
        }

        $adminId = Auth::id();
        $complaint->update([
            'qa_signed_off' => true,
            'qa_signed_off_by' => $adminId,
            'qa_signed_off_at' => now(),
        ]);

        ComplaintAudit::create([
            'complaint_id' => $complaint->id,
            'field_name' => 'qa_signed_off',
            'old_value' => '0',
            'new_value' => '1',
            'changed_by' => $adminId,
            'created_at' => now(),
        ]);

        if ($notes) {
            ComplaintStatusHistory::create([
                'complaint_id' => $complaint->id,
                'from_status' => $complaint->status->value,
                'to_status' => $complaint->status->value,
                'changed_by' => $adminId,
                'notes' => 'QA sign-off: ' . $notes,
            ]);
        }

        logAdminActivity('qa_signed_off', 'Complaint', $complaint->id);

        return [
            'success' => true,
            'message' => 'QA sign-off recorded',
            'complaint' => $complaint->fresh($this->defaultRelations()),
        ];
    }

    public function storeAttachment(
        int $id,
        UploadedFile $file,
        ?string $attachmentType = 'other',
        ?string $notes = null
    ): ?ComplaintAttachment {
        $complaint = $this->findById($id, false);
        if (!$complaint) {
            return null;
        }

        return $this->storeAttachmentForComplaint($complaint, $file, $attachmentType, $notes);
    }

    public function logCommunication(int $id, array $data): array
    {
        $complaint = $this->findById($id, false);
        if (!$complaint) {
            return ['success' => false, 'message' => 'Complaint not found'];
        }

        $isAuthorized = (bool) ($data['is_authorized'] ?? true);

        $communication = ComplaintCommunication::create([
            'complaint_id' => $complaint->id,
            'direction' => $data['direction'] ?? 'outbound',
            'channel' => $data['channel'] ?? 'email',
            'subject' => $data['subject'] ?? null,
            'body' => $data['body'],
            'is_authorized' => $isAuthorized,
            'is_unauthorized_flagged' => !$isAuthorized,
            'recipient' => $data['recipient'] ?? $complaint->customer_email,
            'sent_by' => Auth::id(),
        ]);

        if (!$isAuthorized) {
            sendNotification(
                null,
                null,
                'Unauthorized Complaint Reply Flagged',
                "Unauthorized reply logged on complaint {$complaint->reference_number}.",
                'warning',
                ['complaint_id' => $complaint->id, 'reference_number' => $complaint->reference_number],
                'تم تسجيل رد غير مصرح به',
                "تم تسجيل رد غير مصرح به على الشكوى {$complaint->reference_number}."
            );
        }

        return [
            'success' => true,
            'message' => 'Communication logged',
            'communication' => $communication,
        ];
    }

    public function getTrends(array $filters = []): array
    {
        $base = $this->model->newQuery();

        if (!empty($filters['date_from'])) {
            $base->whereDate('complaint_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $base->whereDate('complaint_date', '<=', $filters['date_to']);
        }

        return [
            'by_type' => (clone $base)->selectRaw('complaint_type, count(*) as total')->groupBy('complaint_type')->pluck('total', 'complaint_type'),
            'by_channel' => (clone $base)->selectRaw('receiving_channel, count(*) as total')->groupBy('receiving_channel')->pluck('total', 'receiving_channel'),
            'by_severity' => (clone $base)->selectRaw('severity, count(*) as total')->groupBy('severity')->pluck('total', 'severity'),
            'by_non_food_category' => (clone $base)->whereNotNull('non_food_category')
                ->selectRaw('non_food_category, count(*) as total')
                ->groupBy('non_food_category')
                ->pluck('total', 'non_food_category'),
            'by_status' => (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'monthly' => (clone $base)
                ->selectRaw("DATE_FORMAT(complaint_date, '%Y-%m') as month, count(*) as total")
                ->groupBy('month')
                ->orderBy('month')
                ->pluck('total', 'month'),
            'total' => (clone $base)->count(),
        ];
    }

    public function sendApproachingTargetReminders(): int
    {
        $threshold = now()->addDays(2)->toDateString();
        $today = now()->toDateString();

        $complaints = $this->model->newQuery()
            ->whereNotIn('status', [ComplaintStatus::Closed])
            ->where(function ($q) use ($today, $threshold) {
                $q->whereBetween('immediate_action_target_date', [$today, $threshold])
                    ->orWhereBetween('corrective_action_target_date', [$today, $threshold])
                    ->orWhereBetween('preventive_action_target_date', [$today, $threshold])
                    ->orWhereBetween('expected_response_date', [$today, $threshold]);
            })
            ->where(function ($q) {
                $q->whereNull('reminder_sent_at')
                    ->orWhere('reminder_sent_at', '<', now()->subDay());
            })
            ->get();

        $sent = 0;
        foreach ($complaints as $complaint) {
            try {
                sendNotification(
                    $complaint->assigned_to,
                    null,
                    'Complaint Target Date Reminder',
                    "Complaint {$complaint->reference_number} has an action target date approaching.",
                    'warning',
                    [
                        'complaint_id' => $complaint->id,
                        'reference_number' => $complaint->reference_number,
                        'status' => $complaint->status?->value,
                    ],
                    'تذكير بموعد إجراء الشكوى',
                    "الشكوى {$complaint->reference_number} لديها موعد مستهدف قريب."
                );
                $complaint->update(['reminder_sent_at' => now()]);
                $sent++;
            } catch (\Exception $e) {
                Log::warning('Failed to send complaint reminder', [
                    'complaint_id' => $complaint->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    public function defaultRelations(): array
    {
        return [
            'customer',
            'product',
            'order',
            'createdBy',
            'assignedTo',
            'closedBy',
            'qaSignedOffBy',
            'attachments',
            'statusHistories.changedBy',
            'communications.sentBy',
            'audits.changedBy',
        ];
    }

    protected function storeAttachmentForComplaint(
        Complaint $complaint,
        UploadedFile $file,
        ?string $attachmentType = 'other',
        ?string $notes = null
    ): ComplaintAttachment {
        $path = $this->uploadFile($file, Complaint::$STORAGE_DIR, 'public');

        return ComplaintAttachment::create([
            'complaint_id' => $complaint->id,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'attachment_type' => $attachmentType ?? 'other',
            'notes' => $notes,
            'uploaded_by' => Auth::id(),
        ]);
    }

    protected function escalateToQa(Complaint $complaint): void
    {
        try {
            sendNotification(
                null,
                null,
                'Food Safety Complaint Escalation',
                "Food safety complaint {$complaint->reference_number} requires immediate QA attention.",
                'alert',
                [
                    'complaint_id' => $complaint->id,
                    'reference_number' => $complaint->reference_number,
                    'batch_number' => $complaint->batch_number,
                ],
                'تصعيد شكوى سلامة غذائية',
                "شكوى سلامة غذائية {$complaint->reference_number} تتطلب اهتمام QA فوري."
            );
        } catch (\Exception $e) {
            Log::warning('Failed to escalate food safety complaint to QA', [
                'complaint_id' => $complaint->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function notifyContainment(Complaint $complaint): void
    {
        try {
            $action = $complaint->containment_action?->value ?? 'pending';
            sendNotification(
                null,
                null,
                'Containment Workflow Triggered',
                "Consumer health risk flagged on {$complaint->reference_number}. Containment: {$action}.",
                'alert',
                [
                    'complaint_id' => $complaint->id,
                    'reference_number' => $complaint->reference_number,
                    'containment_action' => $action,
                ],
                'تم تفعيل إجراء الاحتواء',
                "تم تحديد خطر صحي على المستهلك في {$complaint->reference_number}. الإجراء: {$action}."
            );
        } catch (\Exception $e) {
            Log::warning('Failed to notify containment workflow', [
                'complaint_id' => $complaint->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function sendAcknowledgement(Complaint $complaint): void
    {
        $email = $complaint->customer_email;
        $subject = "Complaint Acknowledgement - {$complaint->reference_number}";
        $body = "We have received your complaint ({$complaint->reference_number}). Our team is reviewing it and will follow up shortly.";

        ComplaintCommunication::create([
            'complaint_id' => $complaint->id,
            'direction' => 'outbound',
            'channel' => 'email',
            'subject' => $subject,
            'body' => $body,
            'is_authorized' => true,
            'is_unauthorized_flagged' => false,
            'recipient' => $email,
            'sent_by' => Auth::id(),
        ]);

        if (!$email) {
            return;
        }

        try {
            Mail::to($email)->send(new ComplaintAcknowledgementMail($complaint));
        } catch (\Exception $e) {
            Log::warning('Failed to send complaint acknowledgement email', [
                'complaint_id' => $complaint->id,
                'email' => $email,
                'message' => $e->getMessage(),
            ]);
        }

        if ($complaint->customer_id) {
            try {
                sendNotification(
                    null,
                    $complaint->customer_id,
                    'Complaint Received',
                    $body,
                    'info',
                    [
                        'complaint_id' => $complaint->id,
                        'reference_number' => $complaint->reference_number,
                    ],
                    'تم استلام الشكوى',
                    "تم استلام شكواك رقم {$complaint->reference_number}. سيقوم فريقنا بمراجعتها والرد عليك قريباً."
                );
            } catch (\Exception $e) {
                Log::warning('Failed to send complaint acknowledgement notification', [
                    'complaint_id' => $complaint->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function detectRepeatComplaint(Complaint $complaint): void
    {
        if (!$complaint->product_id && !$complaint->batch_number) {
            return;
        }

        $query = $this->model->newQuery()->where('id', '!=', $complaint->id);

        if ($complaint->product_id && $complaint->batch_number) {
            $query->where('product_id', $complaint->product_id)
                ->where('batch_number', $complaint->batch_number);
        } elseif ($complaint->product_id) {
            $query->where('product_id', $complaint->product_id);
        } else {
            $query->where('batch_number', $complaint->batch_number);
        }

        $count = $query->count();
        if ($count < 1) {
            return;
        }

        try {
            sendNotification(
                null,
                null,
                'Repeat Complaint Alert',
                "Repeat complaint detected for product/batch on {$complaint->reference_number}. Prior matches: {$count}.",
                'warning',
                [
                    'complaint_id' => $complaint->id,
                    'reference_number' => $complaint->reference_number,
                    'product_id' => $complaint->product_id,
                    'batch_number' => $complaint->batch_number,
                    'prior_count' => $count,
                ],
                'تنبيه شكوى متكررة',
                "تم اكتشاف شكوى متكررة على نفس المنتج/الدفعة ({$complaint->reference_number}). العدد السابق: {$count}."
            );
        } catch (\Exception $e) {
            Log::warning('Failed to send repeat complaint alert', [
                'complaint_id' => $complaint->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function normalizeAuditValue(mixed $value): ?string
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_array($value)) {
            return json_encode($value);
        }
        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->toDateTimeString();
        }

        return (string) $value;
    }
}

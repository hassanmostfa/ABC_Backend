<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ComplaintReceivingChannel;
use App\Enums\ComplaintSeverity;
use App\Enums\ComplaintStatus;
use App\Enums\ComplaintType;
use App\Enums\NonFoodCategory;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\StoreComplaintRequest;
use App\Http\Requests\Admin\UpdateComplaintRequest;
use App\Http\Requests\Admin\UpdateComplaintStatusRequest;
use App\Http\Resources\Admin\ComplaintResource;
use App\Repositories\Complaints\ComplaintRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ComplaintController extends BaseApiController
{
    public function __construct(
        protected ComplaintRepositoryInterface $complaintRepository
    ) {}

    /**
     * List complaints with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(ComplaintStatus::class)],
            'complaint_type' => ['nullable', Rule::enum(ComplaintType::class)],
            'receiving_channel' => ['nullable', Rule::enum(ComplaintReceivingChannel::class)],
            'severity' => ['nullable', Rule::enum(ComplaintSeverity::class)],
            'product_id' => 'nullable|exists:products,id',
            'batch_number' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:255',
            'non_food_category' => ['nullable', Rule::enum(NonFoodCategory::class)],
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $filters = $request->only([
            'status',
            'complaint_type',
            'receiving_channel',
            'severity',
            'product_id',
            'batch_number',
            'department',
            'non_food_category',
            'date_from',
            'date_to',
            'search',
        ]);

        $complaints = $this->complaintRepository->getAllPaginated(
            $filters,
            (int) $request->input('per_page', 15)
        );

        return $this->paginatedResponse(
            $complaints->through(fn ($c) => new ComplaintResource($c)),
            'Complaints retrieved successfully'
        );
    }

    /**
     * Create / register a complaint.
     */
    public function store(StoreComplaintRequest $request): JsonResponse
    {
        try {
            $result = $this->complaintRepository->create($request->validated());

            return $this->createdResponse(
                new ComplaintResource($result['complaint']),
                $result['message']
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Show complaint detail.
     */
    public function show(int $id): JsonResponse
    {
        $complaint = $this->complaintRepository->findById($id);

        if (!$complaint) {
            return $this->notFoundResponse('Complaint not found');
        }

        return $this->successResponse(
            new ComplaintResource($complaint),
            'Complaint retrieved successfully'
        );
    }

    /**
     * Update complaint fields (description is immutable).
     */
    public function update(UpdateComplaintRequest $request, int $id): JsonResponse
    {
        try {
            $result = $this->complaintRepository->update($id, $request->validated());

            if (!$result['success']) {
                $code = ($result['message'] ?? '') === 'Complaint not found' ? 404 : 400;
                return $code === 404
                    ? $this->notFoundResponse($result['message'])
                    : $this->errorResponse($result['message'], 400);
            }

            return $this->updatedResponse(
                new ComplaintResource($result['complaint']),
                $result['message']
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Update complaint status along the pipeline.
     */
    public function updateStatus(UpdateComplaintStatusRequest $request, int $id): JsonResponse
    {
        try {
            $result = $this->complaintRepository->updateStatus(
                $id,
                $request->validated('status'),
                $request->validated('notes')
            );

            if (!$result['success']) {
                $code = ($result['message'] ?? '') === 'Complaint not found' ? 404 : 400;
                return $code === 404
                    ? $this->notFoundResponse($result['message'])
                    : $this->errorResponse($result['message'], 400);
            }

            return $this->updatedResponse(
                new ComplaintResource($result['complaint']),
                $result['message']
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * QA sign-off (required before closing food safety complaints).
     */
    public function qaSignOff(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'notes' => 'nullable|string|max:2000',
        ]);

        try {
            $result = $this->complaintRepository->qaSignOff($id, $request->input('notes'));

            if (!$result['success']) {
                $code = ($result['message'] ?? '') === 'Complaint not found' ? 404 : 400;
                return $code === 404
                    ? $this->notFoundResponse($result['message'])
                    : $this->errorResponse($result['message'], 400);
            }

            return $this->updatedResponse(
                new ComplaintResource($result['complaint']),
                $result['message']
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Upload attachment to a complaint.
     */
    public function storeAttachment(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx',
            'attachment_type' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
        ]);

        $attachment = $this->complaintRepository->storeAttachment(
            $id,
            $request->file('file'),
            $request->input('attachment_type', 'other'),
            $request->input('notes')
        );

        if (!$attachment) {
            return $this->notFoundResponse('Complaint not found');
        }

        logAdminActivity('attachment_uploaded', 'Complaint', $id, [
            'attachment_id' => $attachment->id,
        ]);

        return $this->createdResponse([
            'id' => $attachment->id,
            'original_name' => $attachment->original_name,
            'attachment_type' => $attachment->attachment_type,
        ], 'Attachment uploaded successfully');
    }

    /**
     * Log customer communication.
     */
    public function storeCommunication(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'body' => 'required|string',
            'subject' => 'nullable|string|max:255',
            'channel' => 'nullable|in:email,phone,sms,in_app,other',
            'direction' => 'nullable|in:outbound,inbound',
            'recipient' => 'nullable|string|max:255',
            'is_authorized' => 'nullable|boolean',
        ]);

        $result = $this->complaintRepository->logCommunication($id, $request->all());

        if (!$result['success']) {
            return $this->notFoundResponse($result['message']);
        }

        return $this->createdResponse($result['communication'], $result['message']);
    }

    /**
     * Simple trend aggregates for dashboard.
     */
    public function trends(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $trends = $this->complaintRepository->getTrends($request->only(['date_from', 'date_to']));

        return $this->successResponse($trends, 'Complaint trends retrieved successfully');
    }
}

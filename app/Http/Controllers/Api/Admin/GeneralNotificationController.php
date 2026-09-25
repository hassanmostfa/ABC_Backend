<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\StoreGeneralNotificationRequest;
use App\Http\Resources\Admin\GeneralNotificationResource;
use App\Models\GeneralNotification;
use App\Repositories\GeneralNotifications\GeneralNotificationRepositoryInterface;
use App\Services\Notification\GeneralNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class GeneralNotificationController extends BaseApiController
{
    public function __construct(
        protected GeneralNotificationRepositoryInterface $generalNotificationRepository,
        protected GeneralNotificationService $generalNotificationService
    ) {}

    /**
     * Display the history of notifications sent to all customers.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['nullable', Rule::in(GeneralNotification::TYPES)],
            'status' => ['nullable', Rule::in(GeneralNotification::STATUSES)],
            'offer_id' => 'nullable|integer',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $filters = array_filter([
            'type' => $request->input('type'),
            'status' => $request->input('status'),
            'offer_id' => $request->input('offer_id'),
            'search' => $request->input('search'),
        ], fn ($value) => $value !== null && $value !== '');

        $perPage = (int) $request->input('per_page', 15);
        $notifications = $this->generalNotificationRepository->getAllPaginated($filters, $perPage);

        $response = [
            'success' => true,
            'message' => 'General notifications retrieved successfully',
            'data' => GeneralNotificationResource::collection($notifications->items()),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'from' => $notifications->firstItem(),
                'to' => $notifications->lastItem(),
            ],
        ];

        if (!empty($filters)) {
            $response['filters'] = $filters;
        }

        return response()->json($response);
    }

    /**
     * Send a notification to all active customers (optionally linked to an offer).
     */
    public function store(StoreGeneralNotificationRequest $request): JsonResponse
    {
        $generalNotification = $this->generalNotificationService->create(
            $request->validated(),
            Auth::id()
        );

        logAdminActivity('created', 'GeneralNotification', $generalNotification->id);

        return $this->createdResponse(
            new GeneralNotificationResource($this->generalNotificationRepository->findById($generalNotification->id)),
            'Notification is being sent to all customers'
        );
    }

    /**
     * Display a sent notification with its delivery stats.
     */
    public function show(int $id): JsonResponse
    {
        $generalNotification = $this->generalNotificationRepository->findById($id);

        if (!$generalNotification) {
            return $this->notFoundResponse('General notification not found');
        }

        return $this->resourceResponse(
            new GeneralNotificationResource($generalNotification),
            'General notification retrieved successfully'
        );
    }
}

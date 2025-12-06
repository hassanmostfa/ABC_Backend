<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Admin;
use App\Repositories\NotificationRepositoryInterface;
use App\Http\Resources\Admin\NotificationResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends BaseApiController
{
    protected $notificationRepository;

    public function __construct(NotificationRepositoryInterface $notificationRepository)
    {
        $this->notificationRepository = $notificationRepository;
    }

    /**
     * Display a listing of notifications with pagination and filters.
     * Returns all admin notifications (can be filtered by admin_id).
     */
    public function index(Request $request): JsonResponse
    {
        // Validate filter parameters
        $request->validate([
            'admin_id' => 'nullable|integer|exists:admins,id',
            'type' => 'nullable|string|max:255',
            'is_read' => 'nullable|string|in:true,false',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Prepare filters - filter by Admin type and optionally by specific admin_id
        $filters = [
            'notifiable_type' => Admin::class,
            'notifiable_id' => $request->input('admin_id'),
            'type' => $request->input('type'),
            'is_read' => $request->input('is_read'),
            'search' => $request->input('search'),
        ];

        // Remove empty filters
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        $perPage = $request->input('per_page', 15);
        $notifications = $this->notificationRepository->getAllPaginated($filters, $perPage);

        // Transform data using NotificationResource
        $transformedNotifications = NotificationResource::collection($notifications->items());

        // Get unread count for authenticated admin (for personal reference)
        $admin = Auth::user();
        $unreadCount = $this->notificationRepository->getUnreadCountByAdminId($admin->id);

        // Create a custom response with pagination and filters
        $response = [
            'success' => true,
            'message' => 'Notifications retrieved successfully',
            'data' => $transformedNotifications,
            'my_unread_count' => $unreadCount, // Personal unread count for authenticated admin
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'from' => $notifications->firstItem(),
                'to' => $notifications->lastItem(),
            ]
        ];

        if (!empty($filters)) {
            $response['filters'] = $filters;
        }

        return response()->json($response);
    }

    /**
     * Get unread notifications for the authenticated admin.
     */
    public function unread(Request $request): JsonResponse
    {
        $admin = Auth::user();
        $notifications = $this->notificationRepository->getUnreadByAdminId($admin->id);

        $transformedNotifications = NotificationResource::collection($notifications);

        return $this->successResponse($transformedNotifications, 'Unread notifications retrieved successfully');
    }

    /**
     * Get unread count for the authenticated admin.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $admin = Auth::user();
        $count = $this->notificationRepository->getUnreadCountByAdminId($admin->id);

        return $this->successResponse(['count' => $count], 'Unread count retrieved successfully');
    }

    /**
     * Display the specified notification.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $admin = Auth::user();
        $notification = $this->notificationRepository->findById($id);

        if (!$notification) {
            return $this->notFoundResponse('Notification not found');
        }

        // Check if notification belongs to the admin (polymorphic)
        if ($notification->notifiable_type !== Admin::class || $notification->notifiable_id !== $admin->id) {
            return $this->forbiddenResponse('You do not have access to this notification');
        }

        // Transform data using NotificationResource
        $transformedNotification = new NotificationResource($notification);

        return $this->resourceResponse($transformedNotification, 'Notification retrieved successfully');
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(int $id): JsonResponse
    {
        $admin = Auth::user();
        $notification = $this->notificationRepository->findById($id);

        if (!$notification) {
            return $this->notFoundResponse('Notification not found');
        }

        // Check if notification belongs to the admin (polymorphic)
        if ($notification->notifiable_type !== Admin::class || $notification->notifiable_id !== $admin->id) {
            return $this->forbiddenResponse('You do not have access to this notification');
        }

        $this->notificationRepository->markAsRead($id);

        // Reload notification
        $notification = $this->notificationRepository->findById($id);
        $transformedNotification = new NotificationResource($notification);

        return $this->updatedResponse($transformedNotification, 'Notification marked as read');
    }

    /**
     * Mark all notifications as read for the authenticated admin.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $admin = Auth::user();
        $count = $this->notificationRepository->markAllAsReadForAdmin($admin->id);

        return $this->successResponse(
            ['marked_count' => $count],
            "Marked {$count} notification(s) as read"
        );
    }

    /**
     * Remove the specified notification from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $admin = Auth::user();
        $notification = $this->notificationRepository->findById($id);

        if (!$notification) {
            return $this->notFoundResponse('Notification not found');
        }

        // Check if notification belongs to the admin (polymorphic)
        if ($notification->notifiable_type !== Admin::class || $notification->notifiable_id !== $admin->id) {
            return $this->forbiddenResponse('You do not have access to this notification');
        }

        $deleted = $this->notificationRepository->delete($id);

        if (!$deleted) {
            return $this->notFoundResponse('Notification not found');
        }

        // Log activity
        logAdminActivity('deleted', 'Notification', $id);

        return $this->deletedResponse('Notification deleted successfully');
    }
}


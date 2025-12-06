<?php

namespace App\Http\Controllers\Api\Web;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Customer;
use App\Repositories\NotificationRepositoryInterface;
use App\Http\Resources\Web\CustomerNotificationResource;
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
     */
    public function index(Request $request): JsonResponse
    {
        // Validate filter parameters
        $request->validate([
            'type' => 'nullable|string|max:255',
            'is_read' => 'nullable|string|in:true,false',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $customer = Auth::guard('sanctum')->user();
        
        if (!$customer) {
            return $this->unauthorizedResponse('No authenticated customer found');
        }
        
        // Prepare filters
        $filters = [
            'customer_id' => $customer->id,
            'type' => $request->input('type'),
            'is_read' => $request->input('is_read'),
            'search' => $request->input('search'),
        ];

        // Remove empty filters
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        $perPage = $request->input('per_page', 15);
        $notifications = $this->notificationRepository->getByCustomerId($customer->id, $filters, $perPage);

        // Transform data using CustomerNotificationResource
        $transformedNotifications = CustomerNotificationResource::collection($notifications->items());

        // Get unread count
        $unreadCount = $this->notificationRepository->getUnreadCountByCustomerId($customer->id);

        // Create a custom response with pagination and filters
        $response = [
            'success' => true,
            'message' => 'Notifications retrieved successfully',
            'data' => $transformedNotifications,
            'unread_count' => $unreadCount,
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
     * Get unread notifications for the authenticated customer.
     */
    public function unread(Request $request): JsonResponse
    {
        $customer = Auth::guard('sanctum')->user();
        
        if (!$customer) {
            return $this->unauthorizedResponse('No authenticated customer found');
        }
        
        $notifications = $this->notificationRepository->getUnreadByCustomerId($customer->id);

        $transformedNotifications = CustomerNotificationResource::collection($notifications);

        return $this->successResponse($transformedNotifications, 'Unread notifications retrieved successfully');
    }

    /**
     * Get unread count for the authenticated customer.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $customer = Auth::guard('sanctum')->user();
        
        if (!$customer) {
            return $this->unauthorizedResponse('No authenticated customer found');
        }
        
        $count = $this->notificationRepository->getUnreadCountByCustomerId($customer->id);

        return $this->successResponse(['count' => $count], 'Unread count retrieved successfully');
    }

    /**
     * Display the specified notification.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $customer = Auth::guard('sanctum')->user();
        
        if (!$customer) {
            return $this->unauthorizedResponse('No authenticated customer found');
        }
        
        $notification = $this->notificationRepository->findById($id);

        if (!$notification) {
            return $this->notFoundResponse('Notification not found');
        }

        // Check if notification belongs to the customer (polymorphic)
        if ($notification->notifiable_type !== Customer::class || $notification->notifiable_id !== $customer->id) {
            return $this->forbiddenResponse('You do not have access to this notification');
        }

        // Transform data using CustomerNotificationResource
        $transformedNotification = new CustomerNotificationResource($notification);

        return $this->resourceResponse($transformedNotification, 'Notification retrieved successfully');
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(int $id): JsonResponse
    {
        $customer = Auth::guard('sanctum')->user();
        
        if (!$customer) {
            return $this->unauthorizedResponse('No authenticated customer found');
        }
        
        $notification = $this->notificationRepository->findById($id);

        if (!$notification) {
            return $this->notFoundResponse('Notification not found');
        }

        // Check if notification belongs to the customer (polymorphic)
        if ($notification->notifiable_type !== Customer::class || $notification->notifiable_id !== $customer->id) {
            return $this->forbiddenResponse('You do not have access to this notification');
        }

        $this->notificationRepository->markAsRead($id);

        // Reload notification
        $notification = $this->notificationRepository->findById($id);
        $transformedNotification = new CustomerNotificationResource($notification);

        return $this->updatedResponse($transformedNotification, 'Notification marked as read');
    }

    /**
     * Mark all notifications as read for the authenticated customer.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $customer = Auth::guard('sanctum')->user();
        
        if (!$customer) {
            return $this->unauthorizedResponse('No authenticated customer found');
        }
        
        $count = $this->notificationRepository->markAllAsReadForCustomer($customer->id);

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
        $customer = Auth::guard('sanctum')->user();
        
        if (!$customer) {
            return $this->unauthorizedResponse('No authenticated customer found');
        }
        
        $notification = $this->notificationRepository->findById($id);

        if (!$notification) {
            return $this->notFoundResponse('Notification not found');
        }

        // Check if notification belongs to the customer (polymorphic)
        if ($notification->notifiable_type !== Customer::class || $notification->notifiable_id !== $customer->id) {
            return $this->forbiddenResponse('You do not have access to this notification');
        }

        $deleted = $this->notificationRepository->delete($id);

        if (!$deleted) {
            return $this->notFoundResponse('Notification not found');
        }

        return $this->deletedResponse('Notification deleted successfully');
    }
}


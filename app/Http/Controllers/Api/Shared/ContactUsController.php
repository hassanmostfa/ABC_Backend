<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\ContactUsRequest;
use App\Http\Resources\ContactUsResource;
use App\Repositories\ContactUsRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactUsController extends BaseApiController
{
    protected $contactUsRepository;

    public function __construct(ContactUsRepositoryInterface $contactUsRepository)
    {
        $this->contactUsRepository = $contactUsRepository;
    }

    /**
     * Store a newly created contact message in storage.
     */
    public function store(ContactUsRequest $request): JsonResponse
    {
        $contactUs = $this->contactUsRepository->create($request->validated());
        $transformedContactUs = new ContactUsResource($contactUs);

        return $this->createdResponse($transformedContactUs, 'Contact message sent successfully');
    }

    /**
     * Display a listing of contact messages (for admin use).
     */
    public function index(Request $request): JsonResponse
    {
        // Validate filter parameters
        $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|in:read,unread',
            'sort_by' => 'nullable|in:name,email,is_read,created_at,updated_at',
            'sort_order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Prepare filters
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'sort_by' => $request->input('sort_by', 'created_at'),
            'sort_order' => $request->input('sort_order', 'desc'),
        ];

        // Remove empty filters
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        $perPage = $request->input('per_page', 15);
        $contactMessages = $this->contactUsRepository->getAllPaginated($filters, $perPage);

        // Transform the data using ContactUsResource
        $transformedMessages = ContactUsResource::collection($contactMessages->items());

        // Create a custom response with pagination and filters
        $response = [
            'success' => true,
            'message' => 'Contact messages retrieved successfully',
            'data' => $transformedMessages,
            'pagination' => [
                'current_page' => $contactMessages->currentPage(),
                'last_page' => $contactMessages->lastPage(),
                'per_page' => $contactMessages->perPage(),
                'total' => $contactMessages->total(),
                'from' => $contactMessages->firstItem(),
                'to' => $contactMessages->lastItem(),
            ]
        ];

        if (!empty($filters)) {
            $response['filters'] = $filters;
        }

        return response()->json($response);
    }

    /**
     * Display the specified contact message.
     */
    public function show(int $id): JsonResponse
    {
        $contactMessage = $this->contactUsRepository->findById($id);

        if (!$contactMessage) {
            return $this->notFoundResponse('Contact message not found');
        }

        $transformedContactMessage = new ContactUsResource($contactMessage);
        return $this->resourceResponse($transformedContactMessage, 'Contact message retrieved successfully');
    }

    /**
     * Mark contact message as read.
     */
    public function markAsRead(int $id): JsonResponse
    {
        $success = $this->contactUsRepository->markAsRead($id);

        if (!$success) {
            return $this->notFoundResponse('Contact message not found');
        }

        return $this->successResponse([], 'Contact message marked as read successfully');
    }


    /**
     * Remove the specified contact message from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->contactUsRepository->delete($id);

        if (!$deleted) {
            return $this->notFoundResponse('Contact message not found');
        }

        return $this->deletedResponse('Contact message deleted successfully');
    }
}

<?php

namespace App\Http\Controllers\Api\Mobile\feedbacks;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Mobile\StoreFeedbackRequest;
use App\Http\Resources\Mobile\FeedbackResource;
use App\Repositories\FeedbackRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FeedbackController extends BaseApiController
{
    public function __construct(protected FeedbackRepositoryInterface $feedbackRepository)
    {
    }

    /**
     * Get all feedbacks with pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'rating' => 'nullable|integer|min:1|max:5',
            'order_id' => 'nullable|integer|exists:orders,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $filters = [
            'rating' => $request->input('rating'),
            'order_id' => $request->input('order_id'),
        ];

        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        $perPage = (int) $request->input('per_page', 15);
        $feedbacks = $this->feedbackRepository->getAllPaginated($filters, $perPage);

        return response()->json([
            'success' => true,
            'message' => 'Feedbacks retrieved successfully',
            'data' => FeedbackResource::collection($feedbacks->items()),
            'pagination' => [
                'current_page' => $feedbacks->currentPage(),
                'last_page' => $feedbacks->lastPage(),
                'per_page' => $feedbacks->perPage(),
                'total' => $feedbacks->total(),
                'from' => $feedbacks->firstItem(),
                'to' => $feedbacks->lastItem(),
            ],
            'filters' => $filters,
        ]);
    }

    /**
     * Store customer feedback.
     */
    public function store(StoreFeedbackRequest $request): JsonResponse
    {
        $customerId = Auth::guard('sanctum')->id();

        $feedback = $this->feedbackRepository->create([
            'customer_id' => $customerId,
            'order_id' => $request->validated('order_id'),
            'rating' => $request->validated('rating'),
            'review' => $request->validated('review'),
        ]);

        $feedback->load(['customer', 'order']);

        return $this->createdResponse(new FeedbackResource($feedback), 'Feedback submitted successfully');
    }
}


<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\FaqRequest;
use App\Http\Resources\Admin\FaqResource;
use App\Repositories\FaqRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FaqController extends BaseApiController
{
    protected $faqRepository;

    public function __construct(FaqRepositoryInterface $faqRepository)
    {
        $this->faqRepository = $faqRepository;
    }

    /**
     * Display a listing of the FAQs with pagination, search and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'is_active' => 'nullable|in:true,false,1,0',
            'sort_by' => 'nullable|in:sort_order,created_at,updated_at,id',
            'sort_order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $search = $request->input('search');
        $isActive = $request->input('is_active');
        $sortBy = $request->input('sort_by', 'sort_order');
        $sortOrder = $request->input('sort_order', 'asc');
        $perPage = $request->input('per_page', 15);

        $filters = [
            'search' => $search,
            'is_active' => $isActive,
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder,
        ];

        $faqs = $this->faqRepository->getAllPaginated($filters, $perPage);

        $transformedFaqs = FaqResource::collection($faqs->items());

        $response = [
            'success' => true,
            'message' => 'FAQs retrieved successfully',
            'data' => $transformedFaqs,
            'pagination' => [
                'current_page' => $faqs->currentPage(),
                'last_page' => $faqs->lastPage(),
                'per_page' => $faqs->perPage(),
                'total' => $faqs->total(),
                'from' => $faqs->firstItem(),
                'to' => $faqs->lastItem(),
            ],
        ];

        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        if (!empty($filters)) {
            $response['filters'] = $filters;
        }

        return response()->json($response);
    }

    /**
     * Store a newly created FAQ.
     */
    public function store(FaqRequest $request): JsonResponse
    {
        $faq = $this->faqRepository->create($request->validated());

        logAdminActivity('created', 'Faq', $faq->id);

        return $this->createdResponse(new FaqResource($faq), 'FAQ created successfully');
    }

    /**
     * Display the specified FAQ.
     */
    public function show(int $id): JsonResponse
    {
        $faq = $this->faqRepository->findById($id);

        if (!$faq) {
            return $this->notFoundResponse('FAQ not found');
        }

        return $this->resourceResponse(new FaqResource($faq), 'FAQ retrieved successfully');
    }

    /**
     * Update the specified FAQ.
     */
    public function update(FaqRequest $request, int $id): JsonResponse
    {
        $faq = $this->faqRepository->update($id, $request->validated());

        if (!$faq) {
            return $this->notFoundResponse('FAQ not found');
        }

        logAdminActivity('updated', 'Faq', $faq->id);

        return $this->updatedResponse(new FaqResource($faq), 'FAQ updated successfully');
    }

    /**
     * Remove the specified FAQ.
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->faqRepository->delete($id);

        if (!$deleted) {
            return $this->notFoundResponse('FAQ not found');
        }

        logAdminActivity('deleted', 'Faq', $id);

        return $this->deletedResponse('FAQ deleted successfully');
    }
}

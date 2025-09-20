<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Repositories\SocialMediaLinkRepositoryInterface;
use App\Http\Resources\SocialMediaLinkResource;
use App\Http\Requests\SocialMediaLinkRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SocialMediaLinkController extends BaseApiController
{
    protected $socialMediaLinkRepository;

    public function __construct(SocialMediaLinkRepositoryInterface $socialMediaLinkRepository)
    {
        $this->socialMediaLinkRepository = $socialMediaLinkRepository;
    }

    /**
     * Display a listing of the social media links with pagination, search and filters.
     */
    public function index(Request $request): JsonResponse
    {
        // Validate filter parameters
        $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Prepare filters
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
        ];

        // Remove empty filters
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        $perPage = $request->input('per_page', 15);
        $socialMediaLinks = $this->socialMediaLinkRepository->getAllPaginated($filters, $perPage);

        // Transform data using SocialMediaLinkResource
        $transformedSocialMediaLinks = SocialMediaLinkResource::collection($socialMediaLinks->items());

        // Create a custom response with pagination and filters
        $response = [
            'success' => true,
            'message' => 'Social media links retrieved successfully',
            'data' => $transformedSocialMediaLinks,
            'pagination' => [
                'current_page' => $socialMediaLinks->currentPage(),
                'last_page' => $socialMediaLinks->lastPage(),
                'per_page' => $socialMediaLinks->perPage(),
                'total' => $socialMediaLinks->total(),
                'from' => $socialMediaLinks->firstItem(),
                'to' => $socialMediaLinks->lastItem(),
            ]
        ];

        if (!empty($filters)) {
            $response['filters'] = $filters;
        }

        return response()->json($response);
    }

    /**
     * Store a newly created social media link in storage.
     */
    public function store(SocialMediaLinkRequest $request): JsonResponse
    {
        $socialMediaLink = $this->socialMediaLinkRepository->create($request->validated());
        $transformedSocialMediaLink = new SocialMediaLinkResource($socialMediaLink);

        return $this->createdResponse($transformedSocialMediaLink, 'Social media link created successfully');
    }

    /**
     * Display the specified social media link.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $socialMediaLink = $this->socialMediaLinkRepository->findById($id);

        if (!$socialMediaLink) {
            return $this->notFoundResponse('Social media link not found');
        }

        // Transform data using SocialMediaLinkResource
        $transformedSocialMediaLink = new SocialMediaLinkResource($socialMediaLink);

        return $this->resourceResponse($transformedSocialMediaLink, 'Social media link retrieved successfully');
    }

    /**
     * Update the specified social media link in storage.
     */
    public function update(SocialMediaLinkRequest $request, int $id): JsonResponse
    {
        $socialMediaLink = $this->socialMediaLinkRepository->update($id, $request->validated());

        if (!$socialMediaLink) {
            return $this->notFoundResponse('Social media link not found');
        }

        $transformedSocialMediaLink = new SocialMediaLinkResource($socialMediaLink);
        return $this->updatedResponse($transformedSocialMediaLink, 'Social media link updated successfully');
    }

    /**
     * Remove the specified social media link from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->socialMediaLinkRepository->delete($id);

        if (!$deleted) {
            return $this->notFoundResponse('Social media link not found');
        }

        return $this->deletedResponse('Social media link deleted successfully');
    }

    /**
     * Get all active social media links.
     */
    public function active(Request $request): JsonResponse
    {
        $socialMediaLinks = $this->socialMediaLinkRepository->getActive();

        // Transform data using SocialMediaLinkResource
        $transformedSocialMediaLinks = SocialMediaLinkResource::collection($socialMediaLinks);

        return $this->successResponse($transformedSocialMediaLinks, 'Active social media links retrieved successfully');
    }
}

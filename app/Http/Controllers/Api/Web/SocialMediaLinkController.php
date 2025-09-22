<?php

namespace App\Http\Controllers\Api\Web;

use App\Http\Controllers\Api\BaseApiController;
use App\Repositories\SocialMediaLinkRepositoryInterface;
use App\Http\Resources\Admin\SocialMediaLinkResource;
use Illuminate\Http\JsonResponse;

class SocialMediaLinkController extends BaseApiController
{
    protected $socialMediaLinkRepository;

    public function __construct(SocialMediaLinkRepositoryInterface $socialMediaLinkRepository)
    {
        $this->socialMediaLinkRepository = $socialMediaLinkRepository;
    }

    /**
     * Get all active social media links
     */
    public function getAllActiveLinks(): JsonResponse
    {
        try {
            $socialMediaLinks = $this->socialMediaLinkRepository->getActive();
            
            return $this->collectionResponse(
                SocialMediaLinkResource::collection($socialMediaLinks),
                'Active social media links retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred while retrieving social media links');
        }
    }

    /**
     * Get all social media links (active and inactive)
     */
    public function getAllLinks(): JsonResponse
    {
        try {
            $socialMediaLinks = $this->socialMediaLinkRepository->getAll();
            
            return $this->collectionResponse(
                SocialMediaLinkResource::collection($socialMediaLinks),
                'All social media links retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred while retrieving social media links');
        }
    }

    /**
     * Get a specific social media link by ID
     */
    public function getLinkById(int $id): JsonResponse
    {
        try {
            $socialMediaLink = $this->socialMediaLinkRepository->findById($id);
            
            if (!$socialMediaLink) {
                return $this->notFoundResponse('Social media link not found');
            }
            
            return $this->resourceResponse(
                new SocialMediaLinkResource($socialMediaLink),
                'Social media link retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred while retrieving the social media link');
        }
    }
}
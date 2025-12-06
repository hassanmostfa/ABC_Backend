<?php

namespace App\Http\Controllers\Api\Web;

use App\Http\Controllers\Api\BaseApiController;
use App\Repositories\SliderRepositoryInterface;
use App\Http\Resources\Web\WebSliderResource;
use Illuminate\Http\JsonResponse;

class SliderController extends BaseApiController
{
    protected $sliderRepository;

    public function __construct(SliderRepositoryInterface $sliderRepository)
    {
        $this->sliderRepository = $sliderRepository;
    }

    /**
     * Get all published sliders
     */
    public function getAllPublished(): JsonResponse
    {
        try {
            $sliders = $this->sliderRepository->getPublished();
            
            return $this->collectionResponse(
                WebSliderResource::collection($sliders),
                'Published sliders retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred while retrieving sliders: ' . $e->getMessage());
        }
    }
}


<?php

namespace App\Http\Controllers\Api\Mobile\locations;

use App\Http\Controllers\Api\BaseApiController;
use App\Repositories\CountryRepositoryInterface;
use App\Repositories\GovernorateRepositoryInterface;
use App\Repositories\AreaRepositoryInterface;
use App\Http\Resources\Admin\CountryResource;
use App\Http\Resources\Admin\GovernorateResource;
use App\Http\Resources\Admin\AreaResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LocationController extends BaseApiController
{
    protected $countryRepository;
    protected $governorateRepository;
    protected $areaRepository;

    public function __construct(
        CountryRepositoryInterface $countryRepository,
        GovernorateRepositoryInterface $governorateRepository,
        AreaRepositoryInterface $areaRepository
    ) {
        $this->countryRepository = $countryRepository;
        $this->governorateRepository = $governorateRepository;
        $this->areaRepository = $areaRepository;
    }

    /**
     * Get all countries (mobile API)
     */
    public function getCountries(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->input('search'),
        ];
        
        // Remove null values from filters
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });
        
        $countries = $this->countryRepository->getAll($filters);
        $transformedCountries = CountryResource::collection($countries);
        
        return $this->successResponse(
            $transformedCountries,
            'Countries retrieved successfully'
        );
    }

    /**
     * Get all governorates by country ID (mobile API)
     */
    public function getGovernoratesByCountry(Request $request, int $countryId): JsonResponse
    {
        $governorates = $this->governorateRepository->getByCountry($countryId);
        $transformedGovernorates = GovernorateResource::collection($governorates);

        return $this->successResponse(
            $transformedGovernorates,
            'Governorates retrieved successfully'
        );
    }

    /**
     * Get all areas by governorate ID (mobile API)
     */
    public function getAreasByGovernorate(Request $request, int $governorateId): JsonResponse
    {
        $areas = $this->areaRepository->getByGovernorate($governorateId);
        $transformedAreas = AreaResource::collection($areas);

        return $this->successResponse(
            $transformedAreas,
            'Areas retrieved successfully'
        );
    }
}

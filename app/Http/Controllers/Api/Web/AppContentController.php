<?php

namespace App\Http\Controllers\Api\Web;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Mobile\OfferListResource;
use App\Http\Resources\Mobile\OfferResource;
use App\Models\Setting;
use App\Repositories\OfferRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppContentController extends BaseApiController
{
    public function __construct(
        protected OfferRepositoryInterface $offerRepository
    ) {}

    /**
     * Get locale from Accept-Language header (or locale param), defaults to 'en'.
     */
    private function getLocaleFromRequest(Request $request): string
    {
        $acceptLanguage = $request->header('Accept-Language');

        if ($acceptLanguage) {
            $languages = explode(',', $acceptLanguage);
            $primaryLanguage = strtolower(trim(explode(';', $languages[0])[0]));

            if (str_starts_with($primaryLanguage, 'ar')) {
                return 'ar';
            }
            if (str_starts_with($primaryLanguage, 'en')) {
                return 'en';
            }
        }

        $localeParam = $request->input('locale', 'en');
        return in_array($localeParam, ['en', 'ar']) ? $localeParam : 'en';
    }

    /**
     * Get about content by Accept-Language header.
     */
    public function getAbout(Request $request): JsonResponse
    {
        $locale = $this->getLocaleFromRequest($request);
        $content = Setting::getTranslatedValue('about', $locale, '');

        return $this->successResponse(
            ['content' => $content, 'locale' => $locale],
            'About content retrieved successfully'
        );
    }

    /**
     * Get terms and conditions by Accept-Language header.
     */
    public function getTermsAndConditions(Request $request): JsonResponse
    {
        $locale = $this->getLocaleFromRequest($request);
        $content = Setting::getTranslatedValue('terms_and_conditions', $locale, '');

        return $this->successResponse(
            ['content' => $content, 'locale' => $locale],
            'Terms and conditions retrieved successfully'
        );
    }

    /**
     * Display a listing of active offers with pagination and filters.
     * Response shape matches the mobile offers index API.
     */
    public function getOffers(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'per_page' => 'nullable|integer|min:1|max:100',
                'type' => 'nullable|in:normal,charity',
                'category_id' => 'nullable|integer|exists:categories,id',
                'search' => 'nullable|string|max:1000',
            ]);

            $perPage = $request->input('per_page', 15);
            $filters = [
                'active_only' => true,
                'type' => $request->input('type'),
                'category_id' => $request->input('category_id'),
                'search' => $request->input('search'),
            ];

            $filtered = [];
            foreach ($filters as $key => $value) {
                if ($key === 'active_only' || ($value !== null && $value !== '')) {
                    $filtered[$key] = $value;
                }
            }
            $filters = $filtered;

            $offers = $this->offerRepository->getAllPaginated($filters, $perPage);
            $transformedOffers = OfferListResource::collection($offers->items());

            $response = [
                'success' => true,
                'message' => 'Offers retrieved successfully',
                'data' => $transformedOffers,
                'pagination' => [
                    'current_page' => $offers->currentPage(),
                    'last_page' => $offers->lastPage(),
                    'per_page' => $offers->perPage(),
                    'total' => $offers->total(),
                    'from' => $offers->firstItem(),
                    'to' => $offers->lastItem(),
                ],
            ];

            if (!empty($filters)) {
                $response['filters'] = $filters;
            }

            return response()->json($response);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred while retrieving offers: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified offer.
     * Response shape matches the mobile offer details API.
     */
    public function getOfferDetails(int $id): JsonResponse
    {
        try {
            $offer = $this->offerRepository->findById($id);

            if (!$offer) {
                return $this->notFoundResponse('Offer not found');
            }

            return $this->successResponse(
                new OfferResource($offer),
                'Offer retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred while retrieving the offer: ' . $e->getMessage());
        }
    }
}

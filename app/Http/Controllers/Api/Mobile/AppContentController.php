<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Mobile\FaqResource;
use App\Http\Resources\Web\WebSocialMediaLinkResource;
use App\Models\Faq;
use App\Models\Setting;
use App\Repositories\SocialMediaLinkRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AppContentController extends BaseApiController
{
    public function __construct(
        protected SocialMediaLinkRepositoryInterface $socialMediaLinkRepository
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
     * Get active social media links (title based on Accept-Language).
     */
    public function getSocialMediaLinks(Request $request): JsonResponse
    {
        try {
            $links = $this->socialMediaLinkRepository->getActive();
            return $this->successResponse(
                WebSocialMediaLinkResource::collection($links),
                'Social media links retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred while retrieving social media links');
        }
    }

    /**
     * Get active FAQs (question/answer based on Accept-Language).
     */
    public function getFaqs(Request $request): JsonResponse
    {
        try {
            $faqs = Faq::active()->ordered()->get();
            return $this->successResponse(
                FaqResource::collection($faqs),
                'FAQs retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred while retrieving FAQs');
        }
    }

    /**
     * Get order-related settings: tax, delivery_price, minimum_home_order.
     */
    public function getOrderSettings(Request $request): JsonResponse
    {
        $tax = (float) Setting::getValue('tax', 0.15);
        $deliveryPrice = (float) Setting::getValue('delivery_price', 0);
        $minimumHomeOrder = (float) Setting::getValue('minimum_home_order', 0);

        return $this->successResponse(
            [
                'tax' => $tax,
                'delivery_price' => $deliveryPrice,
                'minimum_home_order' => $minimumHomeOrder,
            ],
            'Order settings retrieved successfully'
        );
    }
}

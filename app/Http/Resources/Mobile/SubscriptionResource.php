<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Traits\ManagesFileUploads;

class SubscriptionResource extends JsonResource
{
    use ManagesFileUploads;

    public function toArray(Request $request): array
    {
        $lang = $this->getLanguage($request);
        
        return [
            'id' => $this->id,
            'offer_id' => $this->offer_id,
            'offer' => [
                'id' => $this->offer->id,
                'title' => $lang === 'ar' ? $this->offer->title_ar : $this->offer->title_en,
                'description' => $lang === 'ar' ? $this->offer->description_ar : $this->offer->description_en,
                'image' => $this->getFileUrl($this->offer->image, 'public', 'no-image.png'),
            ],
            'period' => $this->period,
            'period_months' => (int) $this->period,
            'period_label' => $this->getPeriodLabel($lang),
            'points' => (int) $this->points,
            'is_active' => (bool) $this->is_active,
        ];
    }

    private function getPeriodLabel($lang): string
    {
        $labels = [
            '3' => ['en' => '3 Months', 'ar' => '3 أشهر'],
            '6' => ['en' => '6 Months', 'ar' => '6 أشهر'],
            '12' => ['en' => '12 Months', 'ar' => '12 شهر'],
        ];
        
        return $labels[$this->period][$lang] ?? $this->period;
    }

    private function getLanguage(Request $request): string
    {
        $locale = strtolower($request->header('Accept-Language', $request->input('locale', 'ar')));
        return in_array($locale, ['ar', 'en']) ? $locale : 'ar';
    }
}

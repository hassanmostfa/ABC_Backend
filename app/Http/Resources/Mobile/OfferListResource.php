<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Traits\ManagesFileUploads;

class OfferListResource extends JsonResource
{
    use ManagesFileUploads;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get language from request header
        $lang = $this->getLanguageFromHeader($request);

        return [
            'id' => $this->id,
            'title' => $lang === 'ar' ? $this->title_ar : $this->title_en,
            'description' => $lang === 'ar' ? $this->description_ar : $this->description_en,
            'image' => $this->getFileUrl($this->image, 'public', 'no-image.png'),
            'type' => $this->type,
            'points' => (int) $this->points,
            'offer_start_date' => $this->offer_start_date?->format('Y-m-d'),
            'offer_end_date' => $this->offer_end_date?->format('Y-m-d'),
            'status' => $this->getOfferStatus(),
            'charity' => $this->whenLoaded('charity', function () use ($lang) {
                return [
                    'id' => $this->charity->id,
                    'name' => $lang === 'ar' ? $this->charity->name_ar : $this->charity->name_en,
                ];
            }),
        ];
    }

    /**
     * Get the offer status based on dates and active state
     */
    private function getOfferStatus(): string
    {
        if (!$this->is_active) {
            return 'inactive';
        }

        $now = now();
        
        if ($this->offer_start_date > $now) {
            return 'upcoming';
        }
        
        if ($this->offer_end_date < $now) {
            return 'expired';
        }
        
        return 'active';
    }

    /**
     * Get language from request header
     */
    private function getLanguageFromHeader(Request $request): string
    {
        $locale = strtolower($request->header('Accept-Language', $request->input('locale', 'ar')));
        return in_array($locale, ['ar', 'en']) ? $locale : 'ar';
    }
}


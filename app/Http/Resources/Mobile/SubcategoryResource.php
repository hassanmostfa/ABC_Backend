<?php

namespace App\Http\Resources\Mobile;

use App\Traits\ManagesFileUploads;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubcategoryResource extends JsonResource
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
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', function () use ($lang) {
                return [
                    'id' => $this->category->id,
                    'name' => $lang === 'ar' ? $this->category->name_ar : $this->category->name_en,
                ];
            }),
            'name' => $lang === 'ar' ? $this->name_ar : $this->name_en,
            'image_url' => $this->getFileUrl($this->image_path, 'public', 'no-image.png'),
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->format('Y-m-d'),
            'updated_at' => $this->updated_at?->format('Y-m-d'),
        ];
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


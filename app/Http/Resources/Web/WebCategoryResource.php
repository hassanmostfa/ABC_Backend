<?php

namespace App\Http\Resources\Web;

use App\Traits\ManagesFileUploads;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebCategoryResource extends JsonResource
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
            'name' => $lang === 'ar' ? $this->name_ar : $this->name_en,
            'image_url' => $this->getFileUrl($this->image_path, 'public', 'no-image.png'),
            'is_active' => (bool) $this->is_active,
            'created_at' => \format_datetime_app_tz($this->created_at),
            'updated_at' => \format_datetime_app_tz($this->updated_at),
        ];
    }

    /**
     * Get language from request header
     */
    private function getLanguageFromHeader(Request $request): string
    {
        // Check for Accept-Language header
        $acceptLanguage = $request->header('Accept-Language');
        
        if ($acceptLanguage) {
            // Parse Accept-Language header (e.g., "ar,en;q=0.9,en-US;q=0.8")
            $languages = explode(',', $acceptLanguage);
            $primaryLanguage = trim(explode(';', $languages[0])[0]);
            
            // Check if it's Arabic
            if (in_array($primaryLanguage, ['ar', 'ar-SA', 'ar-EG', 'ar-AE', 'ar-KW', 'ar-BH', 'ar-QA', 'ar-OM', 'ar-YE', 'ar-JO', 'ar-LB', 'ar-SY', 'ar-IQ', 'ar-PS'])) {
                return 'ar';
            }
        }
        
        // Check for custom X-Language header
        $customLanguage = $request->header('X-Language');
        if ($customLanguage && in_array($customLanguage, ['en', 'ar'])) {
            return $customLanguage;
        }
        
        // Default to English
        return 'en';
    }
}

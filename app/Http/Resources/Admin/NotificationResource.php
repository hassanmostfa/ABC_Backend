<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    protected function resolveLocale(Request $request): string
    {
        $requestedLocale = strtolower((string) $request->input('locale', 'en'));
        return in_array($requestedLocale, ['en', 'ar'], true) ? $requestedLocale : 'en';
    }

    protected function resolveTranslation(string $locale): ?\App\Models\NotificationTranslation
    {
        if ($this->relationLoaded('translations')) {
            return $this->translations->firstWhere('locale', $locale)
                ?? $this->translations->firstWhere('locale', 'en')
                ?? $this->translations->first();
        }

        return $this->translations()
            ->where('locale', $locale)
            ->orWhere('locale', 'en')
            ->orderByRaw("CASE WHEN locale = ? THEN 0 WHEN locale = 'en' THEN 1 ELSE 2 END", [$locale])
            ->first();
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = $this->resolveLocale($request);
        $translation = $this->resolveTranslation($locale);
        $titleEn = $this->relationLoaded('translations')
            ? optional($this->translations->firstWhere('locale', 'en'))->title
            : optional($this->translations()->where('locale', 'en')->first())->title;
        $messageEn = $this->relationLoaded('translations')
            ? optional($this->translations->firstWhere('locale', 'en'))->message
            : optional($this->translations()->where('locale', 'en')->first())->message;
        $titleAr = $this->relationLoaded('translations')
            ? optional($this->translations->firstWhere('locale', 'ar'))->title
            : optional($this->translations()->where('locale', 'ar')->first())->title;
        $messageAr = $this->relationLoaded('translations')
            ? optional($this->translations->firstWhere('locale', 'ar'))->message
            : optional($this->translations()->where('locale', 'ar')->first())->message;

        return [
            'id' => $this->id,
            'notifiable_type' => $this->notifiable_type,
            'notifiable_id' => $this->notifiable_id,
            'title' => $translation?->title,
            'title_en' => $titleEn,
            'title_ar' => $titleAr,
            'message' => $translation?->message,
            'message_en' => $messageEn,
            'message_ar' => $messageAr,
            'type' => $this->type,
            'is_read' => (bool) $this->is_read,
            'read_at' => \format_datetime_app_tz($this->read_at),
            'data' => $this->data,
            'created_at' => \format_datetime_app_tz($this->created_at),
            'updated_at' => \format_datetime_app_tz($this->updated_at),
        ];
    }
}


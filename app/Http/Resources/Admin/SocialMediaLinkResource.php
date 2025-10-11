<?php

namespace App\Http\Resources\Admin;

use App\Traits\ManagesFileUploads;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SocialMediaLinkResource extends JsonResource
{
    use ManagesFileUploads;
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'icon_url' => $this->getFileUrl($this->icon, 'public', 'no-image.png'),
            'title_en' => $this->title_en,
            'title_ar' => $this->title_ar,
            'url' => $this->url,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

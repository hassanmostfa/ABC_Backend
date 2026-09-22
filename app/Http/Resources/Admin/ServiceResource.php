<?php

namespace App\Http\Resources\Admin;

use App\Traits\ManagesFileUploads;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    use ManagesFileUploads;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'image' => $this->getFileUrl($this->image, 'public', 'no-image.png'),
            'price' => (float) $this->price,
            'service_provider_email' => $this->service_provider_email,
            'is_active' => (bool) $this->is_active,
            'created_at' => \format_datetime_app_tz($this->created_at),
            'updated_at' => \format_datetime_app_tz($this->updated_at),
        ];
    }
}

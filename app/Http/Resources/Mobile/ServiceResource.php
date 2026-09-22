<?php

namespace App\Http\Resources\Mobile;

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
            'is_active' => (bool) $this->is_active,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactUsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'message' => $this->message,
            'is_read' => (bool) $this->is_read,
            'created_at' => $this->created_at?->setTimezone('Asia/Kuwait')->format('Y-m-d\TH:i:s.vP'),
            'updated_at' => $this->updated_at?->setTimezone('Asia/Kuwait')->format('Y-m-d\TH:i:s.vP'),
        ];
    }
}

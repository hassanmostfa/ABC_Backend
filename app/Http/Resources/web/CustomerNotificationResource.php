<?php

namespace App\Http\Resources\Web;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerNotificationResource extends JsonResource
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
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'is_read' => (bool) $this->is_read,
            'read_at' => \format_datetime_app_tz($this->read_at),
            'data' => $this->data,
            'created_at' => \format_datetime_app_tz($this->created_at),
        ];
    }
}


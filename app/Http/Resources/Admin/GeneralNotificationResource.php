<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeneralNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'offer_id' => $this->offer_id,
            'offer' => $this->whenLoaded('offer', fn () => $this->offer ? [
                'id' => $this->offer->id,
                'title_en' => $this->offer->title_en,
                'title_ar' => $this->offer->title_ar,
                'image_url' => $this->offer->image_url,
            ] : null),
            'title_en' => $this->title_en,
            'title_ar' => $this->title_ar,
            'message_en' => $this->message_en,
            'message_ar' => $this->message_ar,
            'status' => $this->status,
            'recipients_count' => (int) $this->recipients_count,
            'read_count' => (int) ($this->read_count ?? 0),
            'push_sent_count' => (int) $this->push_sent_count,
            'push_failed_count' => (int) $this->push_failed_count,
            'total_chunks' => (int) $this->total_chunks,
            'processed_chunks' => (int) $this->processed_chunks,
            'created_by' => $this->whenLoaded('admin', fn () => $this->admin ? [
                'id' => $this->admin->id,
                'name' => $this->admin->name,
            ] : null),
            'started_at' => \format_datetime_app_tz($this->started_at),
            'completed_at' => \format_datetime_app_tz($this->completed_at),
            'created_at' => \format_datetime_app_tz($this->created_at),
            'updated_at' => \format_datetime_app_tz($this->updated_at),
        ];
    }
}

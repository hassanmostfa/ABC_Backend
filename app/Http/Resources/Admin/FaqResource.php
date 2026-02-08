<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FaqResource extends JsonResource
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
            'question_en' => $this->question_en,
            'question_ar' => $this->question_ar,
            'answer_en' => $this->answer_en,
            'answer_ar' => $this->answer_ar,
            'sort_order' => $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

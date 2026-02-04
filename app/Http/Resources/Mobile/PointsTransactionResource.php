<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PointsTransactionResource extends JsonResource
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
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),
            'amount' => (float) $this->amount,
            'points' => (int) $this->points,
            'description' => $this->description,
            'reference_id' => $this->reference_id,
            'reference_type' => $this->reference_type,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * Get human-readable label for transaction type
     */
    protected function getTypeLabel(): string
    {
        return match ($this->type) {
            'points_to_wallet' => 'Points converted to wallet',
            'points_earned' => 'Points earned',
            default => $this->type,
        };
    }
}

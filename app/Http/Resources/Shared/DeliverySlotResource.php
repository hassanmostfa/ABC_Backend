<?php

namespace App\Http\Resources\Shared;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One delivery slot: start/end range (HH:MM), plus delivery_time for orders (slot start).
 */
class DeliverySlotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $row = is_array($this->resource) ? $this->resource : (array) $this->resource;

        return [
            'start' => $row['start'],
            'end' => $row['end'],
            'delivery_time' => $row['delivery_time'],
            'remaining' => (int) ($row['remaining'] ?? 0),
            'capacity' => (int) ($row['capacity'] ?? 0),
            'booked' => (int) ($row['booked'] ?? 0),
        ];
    }
}

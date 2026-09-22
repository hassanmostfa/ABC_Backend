<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceCheckoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'checkout_number' => $this->checkout_number,
            'status' => $this->status,
            'is_checkout' => true,
            'service_id' => $this->service_id,
            'service_name' => $this->service_name,
            'src' => $this->payment_gateway_src,
            'amount_due' => (float) $this->amount_due,
            'payment_link' => $this->payment_link,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => \format_datetime_app_tz($this->created_at),
        ];
    }
}

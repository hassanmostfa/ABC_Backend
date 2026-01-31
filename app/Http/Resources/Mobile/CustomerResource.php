<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use App\Http\Resources\Mobile\CustomerAddressResource;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
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
            'phone' => $this->phone,
            'profile_image' => $this->profile_image_url,
            'points' => (int) ($this->points ?? 0),
            'balance' => $this->whenLoaded('wallet', function () {
                return (float) ($this->wallet->balance ?? 0.00);
            }, function () {
                // If wallet is not loaded, try to get it
                $wallet = $this->wallet;
                return $wallet ? (float) $wallet->balance : 0.00;
            }),
            'is_active' => (bool) $this->is_active,
            'is_completed' => (bool) $this->is_completed,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'addresses' => $this->whenLoaded('addresses', function () {
                return CustomerAddressResource::collection($this->addresses);
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

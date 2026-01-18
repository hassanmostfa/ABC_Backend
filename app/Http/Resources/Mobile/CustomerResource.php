<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
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
                return $this->addresses->map(function ($address) {
                    return [
                        'id' => $address->id,
                        'country' => $address->country ? [
                            'id' => $address->country->id,
                            'name_en' => $address->country->name_en,
                            'name_ar' => $address->country->name_ar,
                        ] : null,
                        'governorate' => $address->governorate ? [
                            'id' => $address->governorate->id,
                            'name_en' => $address->governorate->name_en,
                            'name_ar' => $address->governorate->name_ar,
                        ] : null,
                        'area' => $address->area ? [
                            'id' => $address->area->id,
                            'name_en' => $address->area->name_en,
                            'name_ar' => $address->area->name_ar,
                        ] : null,
                        'street' => $address->street,
                        'house' => $address->house,
                        'block' => $address->block,
                        'floor' => $address->floor,
                    ];
                });
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

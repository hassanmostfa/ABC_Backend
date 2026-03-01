<?php

namespace App\Http\Resources\Admin;

use App\Traits\CustomerUnreadNotificationsCountTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    use CustomerUnreadNotificationsCountTrait;

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
            'phone' => $this->phone,
            'email' => $this->email,
            'points' => (int) ($this->points ?? 0),
            'current_language' => $this->current_language ?? 'en',
            'unread_notifications_count' => $this->getUnreadNotificationsCount($this->id),
            'is_active' => (bool) $this->is_active,
            'wallet' => $this->whenLoaded('wallet', function () {
                return [
                    'id' => $this->wallet->id,
                    'balance' => (float) $this->wallet->balance,
                ];
            }),
            'addresses' => $this->whenLoaded('addresses', function () {
                return CustomerAddressResource::collection($this->addresses);
            }),
            'created_at' => \format_datetime_app_tz($this->created_at),
            'updated_at' => \format_datetime_app_tz($this->updated_at),
        ];
    }
}


<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletChargeOfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'charge_amount' => (float) $this->charge_amount,
            'get_amount' => (float) $this->get_amount,
            'bonus_amount' => $this->bonusAmount(),
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'created_at' => \format_datetime_app_tz($this->created_at),
            'updated_at' => \format_datetime_app_tz($this->updated_at),
        ];
    }
}

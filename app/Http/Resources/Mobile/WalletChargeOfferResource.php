<?php

namespace App\Http\Resources\Mobile;

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
        ];
    }
}

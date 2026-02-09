<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for wallet charge payment (Payment with type=wallet_charge)
 */
class WalletChargeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_number' => $this->payment_number,
            'reference' => $this->reference,
            'amount' => (float) $this->amount,
            'bonus_amount' => (float) ($this->bonus_amount ?? 0),
            'total_amount' => (float) ($this->total_amount ?? $this->amount),
            'status' => $this->status,
            'created_at' => \format_datetime_app_tz($this->created_at),
        ];
    }
}

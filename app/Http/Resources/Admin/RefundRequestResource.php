<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RefundRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'invoice_id' => $this->invoice_id,
            'customer_id' => $this->customer_id,
            'amount' => (float) $this->amount,
            'status' => $this->status,
            'reason' => $this->reason,
            'admin_notes' => $this->admin_notes,
            'approved_by' => $this->approved_by,
            'approved_at' => \format_datetime_app_tz($this->approved_at),
            'created_at' => \format_datetime_app_tz($this->created_at),
            'order' => $this->whenLoaded('order', fn () => $this->order ? [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'status' => $this->order->status,
            ] : null),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
            ] : null),
        ];
    }
}

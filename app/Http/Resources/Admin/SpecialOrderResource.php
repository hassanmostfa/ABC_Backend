<?php

namespace App\Http\Resources\Admin;

use App\Models\SpecialOrder;
use App\Services\Orders\OrderDraft;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SpecialOrderResource extends JsonResource
{
    /**
     * @param SpecialOrder $resource
     */
    public function toArray(Request $request): array
    {
        /** @var SpecialOrder $specialOrder */
        $specialOrder = $this->resource;
        $draft = OrderDraft::fromPayloadArray($specialOrder->draft());
        $orderData = $draft->orderData;
        $invoiceAmounts = $draft->invoiceAmounts;

        return [
            'id' => $specialOrder->id,
            'order_number' => $specialOrder->order_number,
            'status' => $specialOrder->status,
            'source' => $specialOrder->source,
            'payment_method' => $specialOrder->payment_method,
            'src' => $specialOrder->payment_gateway_src,
            'customer_id' => $specialOrder->customer_id,
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $specialOrder->customer->id,
                'name' => $specialOrder->customer->name,
                'phone' => $specialOrder->customer->phone,
                'email' => $specialOrder->customer->email,
            ]),
            'pricing' => [
                'total_before_discounts' => (float) $draft->totalAmount,
                'original_amount_due' => (float) $specialOrder->original_amount_due,
                'final_price' => (float) $specialOrder->final_price,
                'special_discount' => (float) $specialOrder->special_discount,
                'discount_percentage' => (float) $specialOrder->discount_percentage,
                'offer_discount' => (float) $draft->offerDiscount,
                'coupons_discount' => (float) $draft->couponsDiscount,
                'used_points' => (int) $draft->usedPoints,
                'points_discount' => (float) $draft->pointsDiscount,
                'tax_amount' => (float) ($invoiceAmounts['taxAmount'] ?? 0),
                'delivery_fee' => (float) ($invoiceAmounts['deliveryFee'] ?? 0),
                'total_discount' => (float) ($invoiceAmounts['totalDiscount'] ?? 0),
            ],
            'charity_id' => $orderData['charity_id'] ?? null,
            'customer_address_id' => $orderData['customer_address_id'] ?? null,
            'note' => $orderData['note'] ?? null,
            'delivery_type' => $orderData['delivery_type'] ?? $draft->deliveryType,
            'delivery_date' => !empty($orderData['delivery_date']) ? \format_date_app_tz($orderData['delivery_date']) : null,
            'delivery_time' => !empty($orderData['delivery_time'])
                ? \Carbon\Carbon::parse($orderData['delivery_time'])->format('H:i')
                : null,
            'items' => $this->buildItemsPreview($draft),
            'requested_by' => $this->whenLoaded('requestedBy', fn () => [
                'id' => $specialOrder->requestedBy->id,
                'name' => $specialOrder->requestedBy->name,
                'email' => $specialOrder->requestedBy->email,
                'employee_code' => $specialOrder->requestedBy->employee_code,
            ]),
            'reviewed_by' => $this->whenLoaded('reviewedBy', fn () => $specialOrder->reviewedBy ? [
                'id' => $specialOrder->reviewedBy->id,
                'name' => $specialOrder->reviewedBy->name,
                'email' => $specialOrder->reviewedBy->email,
            ] : null),
            'reviewed_at' => \format_datetime_app_tz($specialOrder->reviewed_at),
            'review_notes' => $specialOrder->review_notes,
            'rejection_reason' => $specialOrder->rejection_reason,
            'order_id' => $specialOrder->order_id,
            'order' => $this->when(
                $specialOrder->relationLoaded('order') && $specialOrder->order,
                fn () => new OrderResource($specialOrder->order)
            ),
            'payment_link' => $specialOrder->orderCheckout?->payment_link,
            'created_at' => \format_datetime_app_tz($specialOrder->created_at),
            'updated_at' => \format_datetime_app_tz($specialOrder->updated_at),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildItemsPreview(OrderDraft $draft): array
    {
        $items = [];

        foreach ($draft->orderItemsData as $index => $item) {
            $items[] = [
                'id' => $index + 1,
                'product_id' => $item['product_id'] ?? null,
                'variant_id' => $item['variant_id'] ?? null,
                'name' => $item['name'] ?? null,
                'sku' => $item['sku'] ?? null,
                'quantity' => (int) ($item['quantity'] ?? 1),
                'unit_price' => (float) ($item['unit_price'] ?? 0),
                'total_price' => (float) ($item['total_price'] ?? 0),
                'tax' => (float) ($item['tax'] ?? 0),
                'discount' => (float) ($item['discount'] ?? 0),
                'is_offer' => (bool) ($item['is_offer'] ?? false),
                'offer_line_kind' => $item['offer_line_kind'] ?? null,
            ];
        }

        return $items;
    }
}

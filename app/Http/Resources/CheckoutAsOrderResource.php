<?php

namespace App\Http\Resources;

use App\Models\CustomerAddress;
use App\Models\OrderCheckout;
use App\Services\OrderDraft;
use App\Support\OrderCheckoutResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckoutAsOrderResource extends JsonResource
{
    /**
     * @param OrderCheckout $resource
     */
    public function toArray(Request $request): array
    {
        /** @var OrderCheckout $checkout */
        $checkout = $this->resource;
        $draft = OrderDraft::fromPayloadArray($checkout->draft());
        $orderData = $draft->orderData;
        $invoiceAmounts = $draft->invoiceAmounts;
        $resolver = app(OrderCheckoutResolver::class);

        $customerAddress = null;
        if (!empty($orderData['customer_address_id'])) {
            $customerAddress = CustomerAddress::query()
                ->with(['country', 'governorate', 'area'])
                ->find($orderData['customer_address_id']);
        }

        return [
            'id' => $checkout->id,
            'customer_id' => $checkout->customer_id,
            'customer_address' => $customerAddress ? [
                'id' => $customerAddress->id,
                'type' => $customerAddress->type ?? 'house',
                'formatted_address' => $customerAddress->formatted_address ?? null,
                'lat' => $customerAddress->lat ? (float) $customerAddress->lat : null,
                'lng' => $customerAddress->lng ? (float) $customerAddress->lng : null,
                'phone_number' => $customerAddress->phone_number,
                'block' => $customerAddress->block,
                'street' => $customerAddress->street,
                'house' => $customerAddress->house,
                'building_name' => $customerAddress->building_name,
                'apartment_number' => $customerAddress->apartment_number,
                'company' => $customerAddress->company,
                'floor' => $customerAddress->floor,
                'additional_directions' => $customerAddress->additional_directions,
                'country' => $customerAddress->country?->name_en,
                'governorate' => $customerAddress->governorate?->name_en,
                'area' => $customerAddress->area?->name_en,
            ] : null,
            'charity_id' => $orderData['charity_id'] ?? null,
            'address' => $orderData['address'] ?? null,
            'type' => !empty($orderData['charity_id']) ? 'charity' : ($checkout->customer_id ? 'customer' : null),
            'payment_method' => 'online_link',
            'src' => $checkout->payment_gateway_src,
            'order_number' => $checkout->order_number,
            'status' => 'pending',
            'feedback_submited' => false,
            'is_sent_to_erp' => false,
            'created_by_type' => isset($orderData['created_by_type']) ? strtolower(class_basename($orderData['created_by_type'])) : null,
            'total_amount' => (float) $draft->totalAmount,
            'offer_snapshot' => $orderData['offer_snapshot'] ?? null,
            'delivery_type' => $orderData['delivery_type'] ?? $draft->deliveryType,
            'delivery_date' => !empty($orderData['delivery_date']) ? \format_date_app_tz($orderData['delivery_date']) : null,
            'delivery_time' => !empty($orderData['delivery_time'])
                ? \Carbon\Carbon::parse($orderData['delivery_time'])->format('H:i')
                : null,
            'customer' => $this->when($checkout->relationLoaded('customer') && $checkout->customer, function () use ($checkout) {
                return [
                    'id' => $checkout->customer->id,
                    'name' => $checkout->customer->name,
                    'phone' => $checkout->customer->phone,
                    'email' => $checkout->customer->email,
                    'points' => (int) ($checkout->customer->points ?? 0),
                    'unread_notifications_count' => 0,
                    'is_active' => (bool) $checkout->customer->is_active,
                ];
            }),
            'charity' => null,
            'created_by' => null,
            'offers' => [],
            'items' => $resolver->buildCheckoutItemsPreview($checkout),
            'invoice' => [
                'id' => null,
                'invoice_number' => null,
                'total_before_discounts' => (float) $draft->totalAmount,
                'tax_amount' => (float) ($invoiceAmounts['taxAmount'] ?? 0),
                'delivery_fee' => (float) ($invoiceAmounts['deliveryFee'] ?? 0),
                'offer_discount' => (float) $draft->offerDiscount,
                'coupons_discount' => (float) $draft->couponsDiscount,
                'used_points' => (int) $draft->usedPoints,
                'points_discount' => (float) $draft->pointsDiscount,
                'total_discount' => (float) ($invoiceAmounts['totalDiscount'] ?? 0),
                'amount_due' => (float) $checkout->amount_due,
                'status' => 'pending',
                'paid_at' => null,
                'payment_link' => $checkout->payment_link,
            ],
            'payment_link' => $checkout->payment_link,
            'created_at' => \format_datetime_app_tz($checkout->created_at),
            'updated_at' => \format_datetime_app_tz($checkout->updated_at),
        ];
    }
}

<?php

namespace App\Http\Resources\Admin;

use App\Models\Admin;
use App\Models\Charity;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderCheckout;
use App\Models\Payment;
use App\Services\OrderDraft;
use App\Support\OrderCheckoutResolver;
use App\Traits\CustomerUnreadNotificationsCountTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
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
            'invoice_id' => $this->invoice_id,
            'customer_id' => $this->customer_id,
            'creator_type' => $this->creator_type ? strtolower(class_basename($this->creator_type)) : null,
            'creator_id' => $this->creator_id,
            'creator' => $this->resolveCreator(),
            'reference' => $this->reference,
            'type' => $this->type ?? 'order',
            'payment_for' => $this->resolvePaymentFor(),
            'payment_number' => $this->payment_number,
            'amount' => (float) $this->amount,
            'bonus_amount' => (float) ($this->bonus_amount ?? 0),
            'total_amount' => isset($this->total_amount) ? (float) $this->total_amount : null,
            'method' => $this->method,
            'src' => $this->payment_gateway_src,
            'status' => $this->status,
            'paid_at' => \format_datetime_app_tz($this->paid_at),
            'receipt_id' => $this->receipt_id,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
                'email' => $this->customer->email,
                'unread_notifications_count' => $this->getUnreadNotificationsCount($this->customer->id),
            ] : null),
            'invoice' => $this->whenLoaded('invoice', fn () => $this->invoice ? [
                    'id' => $this->invoice->id,
                    'invoice_number' => $this->invoice->invoice_number,
                    'amount_due' => (float) $this->invoice->amount_due,
                    'status' => $this->invoice->status,
                    'order' => $this->when($this->invoice->relationLoaded('order') && $this->invoice->order, function () {
                        return $this->formatOrderSummary($this->invoice->order);
                    }),
                ] : null),
            'created_at' => \format_datetime_app_tz($this->created_at),
            'updated_at' => \format_datetime_app_tz($this->updated_at),
        ];
    }

    private function resolveCreator(): ?array
    {
        if (empty($this->creator_id) || empty($this->creator_type)) {
            return null;
        }

        $creator = $this->relationLoaded('creator') ? $this->creator : $this->findCreatorByTypeAndId();
        if (!$creator) {
            return null;
        }

        $creatorData = [
            'id' => $creator->id,
            'type' => strtolower(class_basename($this->creator_type)),
            'name' => $creator->name ?? null,
            'email' => $creator->email ?? null,
        ];

        if ($this->creator_type === Customer::class) {
            $creatorData['phone'] = $creator->phone ?? null;
        }

        if ($this->creator_type === Admin::class) {
            $creatorData['employee_code'] = $creator->employee_code ?? null;
            $creatorData['phone'] = $creator->phone ?? null;
        }

        return $creatorData;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePaymentFor(): array
    {
        $type = $this->type ?? Payment::TYPE_ORDER;

        return match ($type) {
            Payment::TYPE_WALLET_CHARGE => $this->resolveWalletChargePaymentFor(),
            Payment::TYPE_ORDER_CHECKOUT => $this->resolveOrderCheckoutPaymentFor(),
            default => $this->resolveOrderPaymentFor(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveWalletChargePaymentFor(): array
    {
        $reference = $this->reference;

        return [
            'type' => Payment::TYPE_WALLET_CHARGE,
            'label' => 'Wallet Top-up',
            'description' => $reference
                ? "Wallet top-up {$reference}"
                : 'Wallet top-up',
            'reference' => $reference,
            'order_id' => null,
            'order_number' => null,
            'checkout_id' => null,
            'invoice_id' => null,
            'invoice_number' => null,
            'order' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveOrderCheckoutPaymentFor(): array
    {
        $checkout = $this->relationLoaded('orderCheckout') ? $this->orderCheckout : null;
        $orderNumber = $checkout?->order_number ?? $this->reference;
        $order = $checkout?->relationLoaded('order') ? $checkout->order : null;

        return [
            'type' => Payment::TYPE_ORDER_CHECKOUT,
            'label' => 'Order Checkout',
            'description' => $orderNumber
                ? "Order checkout payment for {$orderNumber}"
                : 'Order checkout payment',
            'reference' => $this->reference,
            'checkout_id' => $checkout?->id ?? $this->order_checkout_id,
            'checkout_status' => $checkout?->status,
            'checkout_source' => $checkout?->source,
            'order_id' => $order?->id ?? $checkout?->order_id,
            'order_number' => $order?->order_number ?? $orderNumber,
            'invoice_id' => $order?->relationLoaded('invoice') ? $order->invoice?->id : null,
            'invoice_number' => $order?->relationLoaded('invoice') ? $order->invoice?->invoice_number : null,
            'order' => $order
                ? $this->formatOrderSummary($order)
                : $this->formatCheckoutOrderSummary($checkout),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveOrderPaymentFor(): array
    {
        $invoice = $this->relationLoaded('invoice') ? $this->invoice : null;
        $order = $invoice && $invoice->relationLoaded('order') ? $invoice->order : null;
        $orderNumber = $order?->order_number ?? $this->reference;

        return [
            'type' => Payment::TYPE_ORDER,
            'label' => 'Order Payment',
            'description' => $orderNumber
                ? "Payment for order {$orderNumber}"
                : ($this->reference ? "Payment for {$this->reference}" : 'Order payment'),
            'reference' => $this->reference,
            'order_id' => $order?->id,
            'order_number' => $orderNumber,
            'checkout_id' => null,
            'invoice_id' => $invoice?->id ?? $this->invoice_id,
            'invoice_number' => $invoice?->invoice_number,
            'order' => $this->formatOrderSummary($order),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatOrderSummary(?Order $order): ?array
    {
        if (!$order) {
            return null;
        }

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'payment_method' => $order->payment_method,
            'total_amount' => (float) $order->total_amount,
            'delivery_type' => $order->delivery_type,
            'delivery_date' => $order->delivery_date ? \format_date_app_tz($order->delivery_date) : null,
            'delivery_time' => $order->delivery_time
                ? \Carbon\Carbon::parse($order->delivery_time)->format('H:i')
                : null,
            'address' => $order->address,
            'customer_address' => $order->relationLoaded('customerAddress') && $order->customerAddress ? [
                'id' => $order->customerAddress->id,
                'type' => $order->customerAddress->type ?? 'house',
                'formatted_address' => $order->customerAddress->formatted_address ?? null,
                'block' => $order->customerAddress->block,
                'street' => $order->customerAddress->street,
                'house' => $order->customerAddress->house,
                'building_name' => $order->customerAddress->building_name,
                'apartment_number' => $order->customerAddress->apartment_number,
                'company' => $order->customerAddress->company,
            ] : null,
            'customer' => $order->relationLoaded('customer') && $order->customer ? [
                'id' => $order->customer->id,
                'name' => $order->customer->name,
                'phone' => $order->customer->phone,
                'email' => $order->customer->email,
                'unread_notifications_count' => $this->getUnreadNotificationsCount($order->customer->id),
            ] : null,
            'charity' => $order->relationLoaded('charity') && $order->charity ? [
                'id' => $order->charity->id,
                'name_ar' => $order->charity->name_ar,
                'name_en' => $order->charity->name_en,
                'phone' => $order->charity->phone,
            ] : null,
            'items' => $order->relationLoaded('items') ? $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'variant_id' => $item->variant_id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'total_price' => (float) $item->total_price,
                    'tax' => (float) ($item->tax ?? 0),
                    'discount' => (float) ($item->discount ?? 0),
                    'is_offer' => (bool) $item->is_offer,
                    'offer_line_kind' => $item->offer_line_kind,
                ];
            })->values()->all() : [],
        ];
    }

    /**
     * Build order preview from a pending checkout draft (pay-first flow).
     *
     * @return array<string, mixed>|null
     */
    private function formatCheckoutOrderSummary(?OrderCheckout $checkout): ?array
    {
        if (!$checkout) {
            return null;
        }

        $draft = OrderDraft::fromPayloadArray($checkout->draft());
        $orderData = $draft->orderData;
        $invoiceAmounts = $draft->invoiceAmounts;

        $customerAddress = null;
        if (!empty($orderData['customer_address_id'])) {
            $customerAddress = CustomerAddress::query()->find($orderData['customer_address_id']);
        }

        $charity = null;
        if (!empty($orderData['charity_id'])) {
            $charityModel = Charity::query()->find($orderData['charity_id']);
            if ($charityModel) {
                $charity = [
                    'id' => $charityModel->id,
                    'name_ar' => $charityModel->name_ar,
                    'name_en' => $charityModel->name_en,
                    'phone' => $charityModel->phone,
                ];
            }
        }

        $customer = $checkout->relationLoaded('customer') ? $checkout->customer : null;
        if (!$customer && $checkout->customer_id) {
            $customer = Customer::query()->find($checkout->customer_id);
        }

        return [
            'id' => null,
            'is_checkout_preview' => true,
            'checkout_id' => $checkout->id,
            'order_number' => $checkout->order_number,
            'status' => 'pending',
            'payment_method' => 'online_link',
            'total_amount' => (float) $draft->totalAmount,
            'amount_due' => (float) $checkout->amount_due,
            'delivery_type' => $orderData['delivery_type'] ?? $draft->deliveryType,
            'delivery_date' => !empty($orderData['delivery_date'])
                ? \format_date_app_tz($orderData['delivery_date'])
                : null,
            'delivery_time' => !empty($orderData['delivery_time'])
                ? \Carbon\Carbon::parse($orderData['delivery_time'])->format('H:i')
                : null,
            'address' => $orderData['address'] ?? null,
            'customer_address' => $customerAddress ? [
                'id' => $customerAddress->id,
                'type' => $customerAddress->type ?? 'house',
                'formatted_address' => $customerAddress->formatted_address ?? null,
                'block' => $customerAddress->block,
                'street' => $customerAddress->street,
                'house' => $customerAddress->house,
                'building_name' => $customerAddress->building_name,
                'apartment_number' => $customerAddress->apartment_number,
                'company' => $customerAddress->company,
            ] : null,
            'customer' => $customer ? [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'unread_notifications_count' => $this->getUnreadNotificationsCount($customer->id),
            ] : null,
            'charity' => $charity,
            'items' => app(OrderCheckoutResolver::class)->buildCheckoutItemsPreview($checkout),
            'invoice' => [
                'amount_due' => (float) $checkout->amount_due,
                'tax_amount' => (float) ($invoiceAmounts['taxAmount'] ?? 0),
                'delivery_fee' => (float) ($invoiceAmounts['deliveryFee'] ?? 0),
                'offer_discount' => (float) $draft->offerDiscount,
                'coupons_discount' => (float) $draft->couponsDiscount,
                'points_discount' => (float) $draft->pointsDiscount,
                'total_discount' => (float) ($invoiceAmounts['totalDiscount'] ?? 0),
                'status' => 'pending',
            ],
        ];
    }

    private function findCreatorByTypeAndId(): ?object
    {
        if ($this->creator_type === Admin::class) {
            return Admin::query()->find($this->creator_id);
        }

        if ($this->creator_type === Customer::class) {
            return Customer::query()->find($this->creator_id);
        }

        if (class_exists($this->creator_type)) {
            return $this->creator_type::query()->find($this->creator_id);
        }

        return null;
    }
}


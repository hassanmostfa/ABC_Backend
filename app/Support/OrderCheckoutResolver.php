<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderCheckout;
use App\Services\OrderCheckoutService;
use App\Services\OrderDraft;

class OrderCheckoutResolver
{
    public function findCheckout(int $id): ?OrderCheckout
    {
        return OrderCheckout::query()->find($id);
    }

    public function findOrder(int $id): ?Order
    {
        return Order::query()->find($id);
    }

    /**
     * Resolve checkout first, then fulfilled order linked to checkout.
     */
    public function resolveCheckoutOrOrder(int $id): OrderCheckout|Order|null
    {
        $checkout = $this->findCheckout($id);
        if ($checkout) {
            if ($checkout->order_id) {
                return Order::query()->find($checkout->order_id) ?? $checkout;
            }

            return $checkout;
        }

        return $this->findOrder($id);
    }

    public function belongsToCustomer(OrderCheckout|Order $entity, int $customerId): bool
    {
        return (int) $entity->customer_id === (int) $customerId;
    }

    public function cancel(int $id, ?string $reason = null): array
    {
        $checkout = $this->findCheckout($id);
        if ($checkout && !$checkout->order_id) {
            return app(OrderCheckoutService::class)->cancelCheckout($checkout, $reason);
        }

        return ['success' => false, 'message' => 'Order not found'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildCheckoutItemsPreview(OrderCheckout $checkout): array
    {
        $draft = OrderDraft::fromPayloadArray($checkout->draft());
        $items = [];

        foreach ($draft->orderItemsData as $index => $item) {
            $items[] = [
                'id' => $index + 1,
                'order_id' => $checkout->id,
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
                'product_variant' => null,
            ];
        }

        return $items;
    }
}

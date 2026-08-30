<?php

namespace App\Support;

use App\Models\Offer;
use App\Models\Subscription;

class SubscriptionSize
{
    /**
     * Build a pack size label such as "200ml * 12".
     */
    public static function label(?string $size, ?int $quantity): ?string
    {
        $size = trim((string) $size);
        if ($size === '') {
            return null;
        }

        $quantity = (int) $quantity;

        return $quantity > 0 ? $size . ' * ' . $quantity : $size;
    }

    /**
     * Parse a filter value such as "200ml * 12" or "200ml".
     *
     * @return array{size: string, quantity: int|null}
     */
    public static function parse(string $value): array
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        if (preg_match('/^(.+?)\s*\*\s*(\d+)$/', $value, $matches)) {
            return [
                'size' => trim($matches[1]),
                'quantity' => (int) $matches[2],
            ];
        }

        return [
            'size' => $value,
            'quantity' => null,
        ];
    }

    /**
     * Unique pack sizes from an offer's condition variants.
     *
     * @return list<string>
     */
    public static function fromOffer(?Offer $offer): array
    {
        if (!$offer) {
            return [];
        }

        $offer->loadMissing(['conditions.productVariant']);

        $sizes = [];
        foreach ($offer->conditions as $condition) {
            $label = self::label($condition->productVariant?->size, $condition->quantity);
            if ($label !== null) {
                $sizes[$label] = $label;
            }
        }

        return array_values($sizes);
    }

    /**
     * Unique pack sizes from a collection of subscriptions.
     *
     * @param  iterable<int, Subscription>  $subscriptions
     * @return list<string>
     */
    public static function fromSubscriptions(iterable $subscriptions): array
    {
        $sizes = [];

        foreach ($subscriptions as $subscription) {
            foreach (self::fromOffer($subscription->offer ?? null) as $label) {
                $sizes[$label] = $label;
            }
        }

        return array_values($sizes);
    }
}

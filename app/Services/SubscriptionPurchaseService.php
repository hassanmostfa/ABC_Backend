<?php

namespace App\Services;

use App\Models\CustomerSubscription;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionOrderItem;
use App\Models\Subscription;
use App\Models\Customer;
use App\Models\PointsTransaction;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SubscriptionPurchaseService
{
    /**
     * Purchase a subscription and create all orders
     */
    public function purchase(Customer $customer, array $data): CustomerSubscription
    {
        return DB::transaction(function () use ($customer, $data) {
            // Load subscription with offer details
            $subscription = Subscription::with([
                'offer.conditions.product',
                'offer.conditions.productVariant',
                'offer.rewards.product',
                'offer.rewards.productVariant'
            ])->findOrFail($data['subscription_id']);

            // Calculate dates
            $startDate = Carbon::parse($data['start_date'] ?? now());
            $periodMonths = (int) $subscription->period;
            $endDate = $startDate->copy()->addMonths($periodMonths);

            // Calculate total orders
            $ordersPerMonth = $data['orders_per_month'];
            $totalOrders = $periodMonths * $ordersPerMonth;

            // Calculate total amount
            $totalAmount = $this->calculateTotalAmount($subscription->offer, $totalOrders);

            // Create customer subscription
            $customerSubscription = CustomerSubscription::create([
                'customer_id' => $customer->id,
                'subscription_id' => $subscription->id,
                'orders_per_month' => $ordersPerMonth,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => 'active',
                'total_amount' => $totalAmount,
                'total_orders' => $totalOrders,
                'metadata' => [
                    'offer_id' => $subscription->offer_id,
                    'period' => $subscription->period,
                    'points' => $subscription->points,
                ],
            ]);

            // Generate all subscription orders
            $this->generateSubscriptionOrders(
                $customerSubscription,
                $subscription,
                $data['delivery_schedule'] // Array of delivery dates
            );

            // Award points to customer
            if ($subscription->points > 0) {
                $this->awardPoints($customer, $subscription->points);
            }

            return $customerSubscription->load(['subscription.offer', 'orders']);
        });
    }

    /**
     * Generate all subscription orders
     */
    protected function generateSubscriptionOrders(
        CustomerSubscription $customerSubscription,
        Subscription $subscription,
        array $deliverySchedule
    ): void {
        $ordersPerMonth = $customerSubscription->orders_per_month;
        $periodMonths = (int) $subscription->period;
        $offer = $subscription->offer;

        $orderSequence = 1;

        for ($month = 1; $month <= $periodMonths; $month++) {
            // Get items with quantities for this month
            $monthItems = $this->distributeItemsForMonth($offer, $ordersPerMonth);

            for ($orderInMonth = 1; $orderInMonth <= $ordersPerMonth; $orderInMonth++) {
                // Get delivery date for this order
                $deliveryDate = $deliverySchedule[$orderSequence - 1] ?? null;
                
                if (!$deliveryDate) {
                    throw new \Exception("Delivery date missing for order {$orderSequence}");
                }

                // Calculate order total
                $orderTotal = $this->calculateOrderTotal($monthItems[$orderInMonth - 1]);

                // Create subscription order
                $subscriptionOrder = SubscriptionOrder::create([
                    'order_number' => SubscriptionOrder::generateOrderNumber(),
                    'customer_subscription_id' => $customerSubscription->id,
                    'customer_id' => $customerSubscription->customer_id,
                    'order_sequence' => $orderSequence,
                    'month_number' => $month,
                    'order_in_month' => $orderInMonth,
                    'scheduled_delivery_date' => $deliveryDate,
                    'status' => 'pending',
                    'total_amount' => $orderTotal,
                ]);

                // Create order items
                $this->createOrderItems($subscriptionOrder, $monthItems[$orderInMonth - 1]);

                // Send to ERP
                $this->sendToErp($subscriptionOrder);

                $orderSequence++;
            }
        }
    }

    /**
     * Distribute offer items across orders in a month
     * Example: 11 items, 2 orders -> [[6 items], [5 items]]
     */
    protected function distributeItemsForMonth($offer, $ordersPerMonth): array
    {
        $monthOrders = [];

        // Get all condition items
        $conditionItems = [];
        foreach ($offer->conditions as $condition) {
            $conditionItems[] = [
                'type' => 'condition',
                'product_id' => $condition->product_id,
                'product_variant_id' => $condition->product_variant_id,
                'total_quantity' => $condition->quantity,
                'unit_price' => $condition->productVariant 
                    ? $condition->productVariant->price 
                    : $condition->product->price,
            ];
        }

        // Get all reward items (if product type)
        $rewardItems = [];
        if ($offer->reward_type === 'products') {
            foreach ($offer->rewards as $reward) {
                if ($reward->product_id) {
                    $rewardItems[] = [
                        'type' => 'reward',
                        'product_id' => $reward->product_id,
                        'product_variant_id' => $reward->product_variant_id,
                        'total_quantity' => $reward->quantity,
                        'unit_price' => $reward->productVariant 
                            ? $reward->productVariant->price 
                            : ($reward->product ? $reward->product->price : 0),
                    ];
                }
            }
        }

        $allItems = array_merge($conditionItems, $rewardItems);

        // Distribute quantities across orders
        for ($i = 0; $i < $ordersPerMonth; $i++) {
            $orderItems = [];
            
            foreach ($allItems as $item) {
                // Calculate quantity for this order
                $baseQty = floor($item['total_quantity'] / $ordersPerMonth);
                $remainder = $item['total_quantity'] % $ordersPerMonth;
                
                // Give remainder to first orders
                $quantity = $baseQty + ($i < $remainder ? 1 : 0);
                
                if ($quantity > 0) {
                    $orderItems[] = [
                        'type' => $item['type'],
                        'product_id' => $item['product_id'],
                        'product_variant_id' => $item['product_variant_id'],
                        'quantity' => $quantity,
                        'unit_price' => $item['unit_price'],
                    ];
                }
            }
            
            $monthOrders[] = $orderItems;
        }

        return $monthOrders;
    }

    /**
     * Create order items
     */
    protected function createOrderItems(SubscriptionOrder $order, array $items): void
    {
        foreach ($items as $item) {
            SubscriptionOrderItem::create([
                'subscription_order_id' => $order->id,
                'product_id' => $item['product_id'],
                'product_variant_id' => $item['product_variant_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total_price' => $item['quantity'] * $item['unit_price'],
                'type' => $item['type'],
            ]);
        }
    }

    /**
     * Calculate order total
     */
    protected function calculateOrderTotal(array $items): float
    {
        $total = 0;
        foreach ($items as $item) {
            $total += $item['quantity'] * $item['unit_price'];
        }
        return $total;
    }

    /**
     * Calculate total amount for entire subscription
     */
    protected function calculateTotalAmount($offer, int $totalOrders): float
    {
        $monthlyAmount = 0;

        // Calculate from conditions
        foreach ($offer->conditions as $condition) {
            $price = $condition->productVariant 
                ? $condition->productVariant->price 
                : $condition->product->price;
            $monthlyAmount += $price * $condition->quantity;
        }

        // For product rewards, they're free
        // For discount rewards, apply discount
        if ($offer->reward_type === 'discount') {
            foreach ($offer->rewards as $reward) {
                if ($reward->discount_type === 'percentage') {
                    $monthlyAmount -= ($monthlyAmount * $reward->discount_amount / 100);
                } else {
                    $monthlyAmount -= $reward->discount_amount;
                }
            }
        }

        return max(0, $monthlyAmount * $totalOrders);
    }

    /**
     * Send order to ERP
     */
    protected function sendToErp(SubscriptionOrder $order): void
    {
        // TODO: Implement ERP integration
        // For now, just mark as sent
        $order->update([
            'sent_to_erp_at' => now(),
            'erp_data' => [
                'status' => 'sent',
                'sent_at' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Award points to customer
     */
    protected function awardPoints(Customer $customer, int $points): void
    {
        if ($points > 0) {
            $customer->increment('points', $points);
            
            // Log points transaction
            PointsTransaction::create([
                'customer_id' => $customer->id,
                'type' => 'earned',
                'points' => $points,
                'description' => 'Subscription purchase reward',
                'balance_after' => $customer->fresh()->points,
            ]);
        }
    }
}

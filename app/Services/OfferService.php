<?php

namespace App\Services;

use App\Repositories\OrderItemRepositoryInterface;
use App\Models\Offer;
use App\Models\ProductVariant;
use App\Traits\ChecksOfferActive;
use Illuminate\Support\Facades\DB;

class OfferService
{
    use ChecksOfferActive;

    protected $orderItemRepository;

    public function __construct(OrderItemRepositoryInterface $orderItemRepository)
    {
        $this->orderItemRepository = $orderItemRepository;
    }

    /**
     * Validate offer for order
     *
     * @param int|null $offerId
     * @param int|null $customerId
     * @return Offer|null
     * @throws \Exception
     */
    public function validateOffer(?int $offerId, ?int $customerId = null): ?Offer
    {
        if (!$offerId) {
            return null;
        }

        $offer = $this->getActiveOffer($offerId);
        
        // Check if offer type is charity and customer_id is provided
        if ($offer->type === 'charity' && $customerId) {
            DB::rollBack();
            throw new \Exception('This offer is for charities only.');
        }

        return $offer;
    }

    /**
     * Process offer rewards and return updated order items data and discounts
     *
     * @param Offer $offer
     * @param array $orderItemsData
     * @param float $totalAmount
     * @return array
     * @throws \Exception
     */
    public function processOfferRewards(Offer $offer, array $orderItemsData, float $totalAmount): array
    {
        $offerDiscount = 0.00;

        if ($offer->reward_type === 'products') {
            // Add condition products to order items (if not already present)
            $activeConditions = $offer->activeConditions()->with(['product', 'productVariant'])->get();
            foreach ($activeConditions as $condition) {
                if ($condition->product_variant_id) {
                    $variant = ProductVariant::with('product')->find($condition->product_variant_id);
                    if ($variant && $variant->is_active) {
                        // Check if this variant is already in order items
                        $existingItem = collect($orderItemsData)->firstWhere('variant_id', $variant->id);
                        
                        $conditionQuantity = $condition->quantity;
                        
                        // Calculate total quantity needed (existing + condition)
                        $existingQuantity = $existingItem ? $existingItem['quantity'] : 0;
                        $totalNeededQuantity = $existingQuantity + $conditionQuantity;
                        
                        // Check if variant has sufficient quantity
                        $availableQuantity = $variant->quantity ?? 0;
                        if ($availableQuantity < $totalNeededQuantity) {
                            DB::rollBack();
                            $productName = $variant->product->name_en ?? $variant->product->name_ar ?? 'Product';
                            $sizeInfo = $variant->size ? ' - ' . $variant->size : '';
                            throw new \Exception(
                                "Insufficient quantity for offer condition product {$productName}{$sizeInfo}. Available: {$availableQuantity}, Required: {$totalNeededQuantity}"
                            );
                        }
                        
                        if (!$existingItem) {
                            // Add condition product with normal price
                            $productName = $variant->product->name_en ?? $variant->product->name_ar ?? 'Product';
                            if ($variant->size) {
                                $productName .= ' - ' . $variant->size;
                            }
                            
                            $conditionUnitPrice = $variant->price;
                            $conditionTotalPrice = $conditionUnitPrice * $conditionQuantity;
                            
                            $orderItemsData[] = [
                                'variant_id' => $variant->id,
                                'product_id' => $variant->product_id,
                                'name' => $productName,
                                'sku' => $variant->sku,
                                'quantity' => $conditionQuantity,
                                'unit_price' => $conditionUnitPrice,
                                'total_price' => $conditionTotalPrice,
                                'is_offer' => true,
                            ];
                            
                            // Add to total amount
                            $totalAmount += $conditionTotalPrice;
                        }
                    }
                }
            }
            
            // Add reward products to order items with normal prices
            // Their total price will be added to offer_discount
            $rewardProductsTotal = 0.00;
            $activeRewards = $offer->activeRewards()->with(['product', 'productVariant'])->get();
            foreach ($activeRewards as $reward) {
                if ($reward->product_variant_id) {
                    $variant = ProductVariant::with('product')->find($reward->product_variant_id);
                    if ($variant && $variant->is_active) {
                        $productName = $variant->product->name_en ?? $variant->product->name_ar ?? 'Product';
                        if ($variant->size) {
                            $productName .= ' - ' . $variant->size;
                        }
                        
                        $rewardQuantity = $reward->quantity;
                        
                        // Check if variant has sufficient quantity
                        $availableQuantity = $variant->quantity ?? 0;
                        if ($availableQuantity < $rewardQuantity) {
                            DB::rollBack();
                            $sizeInfo = $variant->size ? ' - ' . $variant->size : '';
                            throw new \Exception(
                                "Insufficient quantity for offer reward product {$productName}{$sizeInfo}. Available: {$availableQuantity}, Required: {$rewardQuantity}"
                            );
                        }
                        
                        $rewardUnitPrice = $variant->price;
                        $rewardTotalPrice = $rewardUnitPrice * $rewardQuantity;
                        $rewardProductsTotal += $rewardTotalPrice;
                        
                        $orderItemsData[] = [
                            'variant_id' => $variant->id,
                            'product_id' => $variant->product_id,
                            'name' => $productName,
                            'sku' => $variant->sku,
                            'quantity' => $rewardQuantity,
                            'unit_price' => $rewardUnitPrice,
                            'total_price' => $rewardTotalPrice,
                            'is_offer' => true,
                        ];
                        
                        // Add to total amount
                        $totalAmount += $rewardTotalPrice;
                    }
                }
            }
            
            // Add reward products total price to offer discount
            $offerDiscount = $rewardProductsTotal;
        } elseif ($offer->reward_type === 'discount') {
            // Calculate discount from rewards
            $activeRewards = $offer->activeRewards()->get();
            foreach ($activeRewards as $reward) {
                if ($reward->discount_amount && $reward->discount_type) {
                    if ($reward->discount_type === 'percentage') {
                        $discount = ($totalAmount * $reward->discount_amount) / 100;
                    } else {
                        $discount = $reward->discount_amount;
                    }
                    $offerDiscount += $discount;
                }
            }
            // Don't allow discount to exceed total amount
            $offerDiscount = min($offerDiscount, $totalAmount);
        }

        return [
            'orderItemsData' => $orderItemsData,
            'totalAmount' => $totalAmount,
            'offerDiscount' => $offerDiscount
        ];
    }

    /**
     * Create offer reward items for update order
     *
     * @param int $orderId
     * @param Offer $offer
     * @return float Additional total amount from reward products
     * @throws \Exception
     */
    public function createOfferRewardItems(int $orderId, Offer $offer): float
    {
        if ($offer->reward_type !== 'products') {
            return 0.00;
        }

        $additionalTotal = 0.00;
        $activeRewards = $offer->activeRewards()->with(['product', 'productVariant'])->get();
        
        foreach ($activeRewards as $reward) {
            if ($reward->product_variant_id) {
                $variant = ProductVariant::with('product')->find($reward->product_variant_id);
                if ($variant && $variant->is_active) {
                    $productName = $variant->product->name_en ?? $variant->product->name_ar ?? 'Product';
                    if ($variant->size) {
                        $productName .= ' - ' . $variant->size;
                    }
                    
                    $rewardQuantity = $reward->quantity;
                    $availableQuantity = $variant->quantity ?? 0;
                    
                    if ($availableQuantity < $rewardQuantity) {
                        DB::rollBack();
                        $sizeInfo = $variant->size ? ' - ' . $variant->size : '';
                        throw new \Exception(
                            "Insufficient quantity for offer reward product {$productName}{$sizeInfo}. Available: {$availableQuantity}, Required: {$rewardQuantity}"
                        );
                    }
                    
                    $rewardUnitPrice = $variant->price;
                    $rewardTotalPrice = $rewardUnitPrice * $rewardQuantity;
                    
                    $rewardItemData = [
                        'order_id' => $orderId,
                        'variant_id' => $variant->id,
                        'product_id' => $variant->product_id,
                        'name' => $productName,
                        'sku' => $variant->sku,
                        'quantity' => $rewardQuantity,
                        'unit_price' => $rewardUnitPrice,
                        'total_price' => $rewardTotalPrice,
                        'is_offer' => true,
                    ];
                    
                    $this->orderItemRepository->create($rewardItemData);
                    $additionalTotal += $rewardTotalPrice;
                    
                    // Update variant quantity
                    $variant = ProductVariant::find($reward->product_variant_id);
                    if ($variant) {
                        $newQuantity = max(0, ($variant->quantity ?? 0) - $rewardQuantity);
                        $variant->update(['quantity' => $newQuantity]);
                    }
                }
            }
        }

        return $additionalTotal;
    }

    /**
     * Calculate offer discount for an order
     *
     * @param Offer|null $offer
     * @param float $totalAmount
     * @return float
     */
    public function calculateOfferDiscount(?Offer $offer, float $totalAmount): float
    {
        if (!$offer) {
            return 0.00;
        }

        if ($offer->reward_type === 'products') {
            // Calculate reward products total for discount
            $rewardProductsTotal = 0.00;
            $activeRewards = $offer->activeRewards()->with(['product', 'productVariant'])->get();
            foreach ($activeRewards as $reward) {
                if ($reward->product_variant_id) {
                    $variant = ProductVariant::with('product')->find($reward->product_variant_id);
                    if ($variant && $variant->is_active) {
                        $rewardQuantity = $reward->quantity;
                        $rewardUnitPrice = $variant->price;
                        $rewardTotalPrice = $rewardUnitPrice * $rewardQuantity;
                        $rewardProductsTotal += $rewardTotalPrice;
                    }
                }
            }
            return $rewardProductsTotal;
        } elseif ($offer->reward_type === 'discount') {
            // Calculate discount from rewards
            $offerDiscount = 0.00;
            $activeRewards = $offer->activeRewards()->get();
            foreach ($activeRewards as $reward) {
                if ($reward->discount_amount && $reward->discount_type) {
                    if ($reward->discount_type === 'percentage') {
                        $discount = ($totalAmount * $reward->discount_amount) / 100;
                    } else {
                        $discount = $reward->discount_amount;
                    }
                    $offerDiscount += $discount;
                }
            }
            return min($offerDiscount, $totalAmount);
        }

        return 0.00;
    }

    /**
     * Add offer points to customer when order is completed
     *
     * @param \App\Models\Order $order
     * @param \App\Repositories\CustomerRepositoryInterface $customerRepository
     * @return void
     */
    public function addOfferPointsToCustomer($order, $customerRepository): void
    {
        if (!$order->customer_id) {
            return;
        }

        // Load offers if not already loaded
        if (!$order->relationLoaded('offers')) {
            $order->load('offers');
        }

        if ($order->offers->isEmpty()) {
            return;
        }

        // Calculate total points from all offers (multiply by quantity)
        $totalPoints = 0;
        foreach ($order->offers as $offer) {
            if ($offer->points && $offer->points > 0) {
                $quantity = isset($offer->pivot->quantity) ? (int) $offer->pivot->quantity : 1;
                $totalPoints += $offer->points * $quantity;
            }
        }

        if ($totalPoints <= 0) {
            return;
        }

        $customer = $customerRepository->findById($order->customer_id);
        if ($customer) {
            $currentPoints = $customer->points ?? 0;
            $customerRepository->update($customer->id, [
                'points' => $currentPoints + $totalPoints
            ]);
        }
    }
}

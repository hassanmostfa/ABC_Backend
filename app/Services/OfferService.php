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
    protected $walletTransactionService;

    public function __construct(
        OrderItemRepositoryInterface $orderItemRepository,
        ?PointsTransactionService $pointsTransactionService = null
    ) {
        $this->orderItemRepository = $orderItemRepository;
        $this->pointsTransactionService = $pointsTransactionService ?? app(PointsTransactionService::class);
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
                        // Find existing item index (if exists)
                        $existingItemIndex = collect($orderItemsData)->search(function ($item) use ($variant) {
                            return isset($item['variant_id']) && $item['variant_id'] == $variant->id;
                        });
                        
                        $conditionQuantity = $condition->quantity;
                        
                        // Check if variant has sufficient quantity
                        $availableQuantity = $variant->quantity ?? 0;
                        if ($availableQuantity < $conditionQuantity) {
                            DB::rollBack();
                            $productName = $variant->product->name_en ?? $variant->product->name_ar ?? 'Product';
                            $sizeInfo = $variant->size ? ' - ' . $variant->size : '';
                            throw new \Exception(
                                "Insufficient quantity for offer condition product {$productName}{$sizeInfo}. Available: {$availableQuantity}, Required: {$conditionQuantity}"
                            );
                        }
                        
                        $productName = $variant->product->name_en ?? $variant->product->name_ar ?? 'Product';
                        if ($variant->size) {
                            $productName .= ' - ' . $variant->size;
                        }
                        
                        $conditionUnitPrice = $variant->price;
                        $conditionTotalPrice = $conditionUnitPrice * $conditionQuantity;
                        
                        if ($existingItemIndex !== false) {
                            // Item exists - update quantity and price
                            $existingItem = $orderItemsData[$existingItemIndex];
                            $oldTotalPrice = $existingItem['total_price'];
                            $existingItem['quantity'] += $conditionQuantity;
                            $existingItem['total_price'] = $existingItem['unit_price'] * $existingItem['quantity'];
                            $orderItemsData[$existingItemIndex] = $existingItem;
                            
                            // Update total amount (remove old, add new)
                            $totalAmount = $totalAmount - $oldTotalPrice + $existingItem['total_price'];
                        } else {
                            // Add new condition product item
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
            
            // Add ALL reward products to order items (always add, even if duplicates from multiple offers)
            // Reward products are free, so they should always be added as separate items
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
                        
                        // IMPORTANT: Always add reward products to order items (they're free)
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
            // Add condition products to order items (if not already present)
            // Discount offers also need their conditions fulfilled
            $totalBeforeConditions = $totalAmount;
            $activeConditions = $offer->activeConditions()->with(['product', 'productVariant'])->get();
            foreach ($activeConditions as $condition) {
                if ($condition->product_variant_id) {
                    $variant = ProductVariant::with('product')->find($condition->product_variant_id);
                    if ($variant && $variant->is_active) {
                        // Find existing item index (if exists)
                        $existingItemIndex = collect($orderItemsData)->search(function ($item) use ($variant) {
                            return isset($item['variant_id']) && $item['variant_id'] == $variant->id;
                        });
                        
                        $conditionQuantity = $condition->quantity;
                        
                        // Check if variant has sufficient quantity
                        $availableQuantity = $variant->quantity ?? 0;
                        if ($availableQuantity < $conditionQuantity) {
                            DB::rollBack();
                            $productName = $variant->product->name_en ?? $variant->product->name_ar ?? 'Product';
                            $sizeInfo = $variant->size ? ' - ' . $variant->size : '';
                            throw new \Exception(
                                "Insufficient quantity for offer condition product {$productName}{$sizeInfo}. Available: {$availableQuantity}, Required: {$conditionQuantity}"
                            );
                        }
                        
                        $productName = $variant->product->name_en ?? $variant->product->name_ar ?? 'Product';
                        if ($variant->size) {
                            $productName .= ' - ' . $variant->size;
                        }
                        
                        $conditionUnitPrice = $variant->price;
                        $conditionTotalPrice = $conditionUnitPrice * $conditionQuantity;
                        
                        if ($existingItemIndex !== false) {
                            // Item exists - update quantity and price
                            $existingItem = $orderItemsData[$existingItemIndex];
                            $oldTotalPrice = $existingItem['total_price'];
                            $existingItem['quantity'] += $conditionQuantity;
                            $existingItem['total_price'] = $existingItem['unit_price'] * $existingItem['quantity'];
                            $orderItemsData[$existingItemIndex] = $existingItem;
                            
                            // Update total amount (remove old, add new)
                            $totalAmount = $totalAmount - $oldTotalPrice + $existingItem['total_price'];
                        } else {
                            // Add new condition product item
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
            
            // Calculate discount from rewards applied only to THIS offer's condition total
            // (processOfferRewards is called once per offer quantity, so we must not use full totalAmount)
            $conditionTotalThisOffer = $totalAmount - $totalBeforeConditions;
            $activeRewards = $offer->activeRewards()->get();
            foreach ($activeRewards as $reward) {
                if ($reward->discount_amount && $reward->discount_type) {
                    if ($reward->discount_type === 'percentage') {
                        $discount = ($conditionTotalThisOffer * $reward->discount_amount) / 100;
                    } else {
                        $discount = $reward->discount_amount;
                    }
                    $offerDiscount += $discount;
                }
            }
            // Cap this offer's discount to this offer's condition total (safety)
            $offerDiscount = min($offerDiscount, $conditionTotalThisOffer > 0 ? $conditionTotalThisOffer : $totalAmount);
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

            // Record points earned in points transaction history
            $this->pointsTransactionService->recordPointsEarned(
                $order->customer_id,
                $totalPoints,
                $order->id,
                "Earned {$totalPoints} points from order #{$order->id}"
            );
        }
    }
}

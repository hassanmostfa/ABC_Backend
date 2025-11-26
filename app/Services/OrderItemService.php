<?php

namespace App\Services;

use App\Repositories\OrderItemRepositoryInterface;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class OrderItemService
{
    protected $orderItemRepository;

    public function __construct(OrderItemRepositoryInterface $orderItemRepository)
    {
        $this->orderItemRepository = $orderItemRepository;
    }

    /**
     * Process order items from request data
     *
     * @param array $items
     * @return array
     * @throws \Exception
     */
    public function processItems(array $items): array
    {
        $totalAmount = 0;
        $orderItemsData = [];

        foreach ($items as $item) {
            $variant = ProductVariant::with('product')->find($item['variant_id']);
            
            if (!$variant) {
                DB::rollBack();
                throw new \Exception('Variant not found: ' . $item['variant_id']);
            }

            if (!$variant->is_active) {
                DB::rollBack();
                throw new \Exception('Variant is not active: ' . $item['variant_id']);
            }

            $quantity = $item['quantity'];
            
            // Check if variant has sufficient quantity
            $availableQuantity = $variant->quantity ?? 0;
            if ($availableQuantity < $quantity) {
                DB::rollBack();
                $productName = $variant->product->name_en ?? $variant->product->name_ar ?? 'Product';
                $sizeInfo = $variant->size ? ' - ' . $variant->size : '';
                throw new \Exception(
                    "Insufficient quantity for {$productName}{$sizeInfo}. Available: {$availableQuantity}, Requested: {$quantity}"
                );
            }
            
            $unitPrice = $variant->price;
            $totalPrice = $unitPrice * $quantity;
            $totalAmount += $totalPrice;

            // Build product name (using English name, fallback to Arabic)
            $productName = $variant->product->name_en ?? $variant->product->name_ar ?? 'Product';
            if ($variant->size) {
                $productName .= ' - ' . $variant->size;
            }

            $orderItemsData[] = [
                'variant_id' => $variant->id,
                'product_id' => $variant->product_id,
                'name' => $productName,
                'sku' => $variant->sku,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
                'is_offer' => false,
            ];
        }

        return [
            'orderItemsData' => $orderItemsData,
            'totalAmount' => $totalAmount
        ];
    }

    /**
     * Create order items and update variant quantities
     *
     * @param int $orderId
     * @param array $orderItemsData
     * @return void
     */
    public function createOrderItems(int $orderId, array $orderItemsData): void
    {
        foreach ($orderItemsData as $itemData) {
            $itemData['order_id'] = $orderId;
            $this->orderItemRepository->create($itemData);
            
            // Update product variant quantity
            $variant = ProductVariant::find($itemData['variant_id']);
            if ($variant) {
                $newQuantity = max(0, ($variant->quantity ?? 0) - $itemData['quantity']);
                $variant->update(['quantity' => $newQuantity]);
            }
        }
    }

    /**
     * Process order items for update
     *
     * @param int $orderId
     * @param array $items
     * @return array
     * @throws \Exception
     */
    public function processItemsForUpdate(int $orderId, array $items): array
    {
        if (empty($items)) {
            return ['totalAmount' => 0, 'orderItemsData' => []];
        }

        $existingItemIds = collect($items)->pluck('id')->filter()->toArray();
        
        // Get all current order items
        $currentItems = $this->orderItemRepository->getByOrder($orderId);
        $currentItemIds = $currentItems->pluck('id')->toArray();
        
        // Delete items that are not in the request and restore quantities
        $itemsToDelete = array_diff($currentItemIds, $existingItemIds);
        foreach ($itemsToDelete as $itemId) {
            $itemToDelete = $this->orderItemRepository->findById($itemId);
            if ($itemToDelete) {
                // Restore variant quantity
                $variant = ProductVariant::find($itemToDelete->variant_id);
                if ($variant) {
                    $newQuantity = ($variant->quantity ?? 0) + $itemToDelete->quantity;
                    $variant->update(['quantity' => $newQuantity]);
                }
            }
            $this->orderItemRepository->delete($itemId);
        }

        // Calculate new total amount
        $totalAmount = 0;
        $orderItemsData = [];

        // Fetch variants and prepare order items data
        foreach ($items as $item) {
            $variant = ProductVariant::with('product')->find($item['variant_id']);
            
            if (!$variant) {
                DB::rollBack();
                throw new \Exception('Variant not found: ' . $item['variant_id']);
            }

            if (!$variant->is_active) {
                DB::rollBack();
                throw new \Exception('Variant is not active: ' . $item['variant_id']);
            }

            $quantity = $item['quantity'];
            
            // Check quantity availability
            if (isset($item['id']) && $item['id']) {
                // Updating existing item - need to account for restored quantity
                $existingItem = $this->orderItemRepository->findById($item['id']);
                if ($existingItem) {
                    $oldQuantity = $existingItem->quantity;
                    // Available quantity = current + old quantity (since we'll restore it)
                    $availableQuantity = ($variant->quantity ?? 0) + $oldQuantity;
                } else {
                    $availableQuantity = $variant->quantity ?? 0;
                }
            } else {
                // New item - use current quantity
                $availableQuantity = $variant->quantity ?? 0;
            }
            
            if ($availableQuantity < $quantity) {
                DB::rollBack();
                $productName = $variant->product->name_en ?? $variant->product->name_ar ?? 'Product';
                $sizeInfo = $variant->size ? ' - ' . $variant->size : '';
                throw new \Exception(
                    "Insufficient quantity for {$productName}{$sizeInfo}. Available: {$availableQuantity}, Requested: {$quantity}"
                );
            }
            
            $unitPrice = $variant->price;
            $totalPrice = $unitPrice * $quantity;
            $totalAmount += $totalPrice;

            // Build product name
            $productName = $variant->product->name_en ?? $variant->product->name_ar ?? 'Product';
            if ($variant->size) {
                $productName .= ' - ' . $variant->size;
            }

            $orderItemData = [
                'variant_id' => $variant->id,
                'product_id' => $variant->product_id,
                'name' => $productName,
                'sku' => $variant->sku,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
                'is_offer' => false,
            ];

            if (isset($item['id']) && $item['id']) {
                // Update existing item - adjust quantities
                $existingItem = $this->orderItemRepository->findById($item['id']);
                if ($existingItem) {
                    $oldQuantity = $existingItem->quantity;
                    $newQuantity = $quantity;
                    
                    // Restore old quantity and deduct new quantity
                    $variant = ProductVariant::find($item['variant_id']);
                    if ($variant) {
                        $currentQuantity = $variant->quantity ?? 0;
                        $newVariantQuantity = $currentQuantity + $oldQuantity - $newQuantity;
                        $newVariantQuantity = max(0, $newVariantQuantity);
                        $variant->update(['quantity' => $newVariantQuantity]);
                    }
                }
                
                $orderItemData['order_id'] = $orderId;
                $this->orderItemRepository->update($item['id'], $orderItemData);
            } else {
                // Create new item - deduct quantity
                $orderItemData['order_id'] = $orderId;
                $this->orderItemRepository->create($orderItemData);
                
                // Update variant quantity
                $variant = ProductVariant::find($item['variant_id']);
                if ($variant) {
                    $newQuantity = max(0, ($variant->quantity ?? 0) - $quantity);
                    $variant->update(['quantity' => $newQuantity]);
                }
            }
            
            // Store for offer processing
            $orderItemsData[] = $orderItemData;
        }

        return [
            'totalAmount' => $totalAmount,
            'orderItemsData' => $orderItemsData
        ];
    }

    /**
     * Clear order items and restore quantities
     *
     * @param int $orderId
     * @return void
     */
    public function clearOrderItems(int $orderId): void
    {
        $currentItems = $this->orderItemRepository->getByOrder($orderId);
        foreach ($currentItems as $currentItem) {
            // Restore variant quantity
            $variant = ProductVariant::find($currentItem->variant_id);
            if ($variant) {
                $newQuantity = ($variant->quantity ?? 0) + $currentItem->quantity;
                $variant->update(['quantity' => $newQuantity]);
            }
            $this->orderItemRepository->delete($currentItem->id);
        }
    }
}


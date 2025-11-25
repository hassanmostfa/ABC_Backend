<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\StoreOrderRequest;
use App\Http\Requests\Admin\UpdateOrderRequest;
use App\Http\Resources\Admin\OrderResource;
use App\Repositories\OrderRepositoryInterface;
use App\Repositories\OrderItemRepositoryInterface;
use App\Repositories\InvoiceRepositoryInterface;
use App\Repositories\DeliveryRepositoryInterface;
use App\Repositories\CustomerRepositoryInterface;
use App\Models\ProductVariant;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Wallet;
use App\Traits\ChecksOfferActive;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OrderController extends BaseApiController
{
    use ChecksOfferActive;

    protected $orderRepository;
    protected $orderItemRepository;
    protected $invoiceRepository;
    protected $deliveryRepository;
    protected $customerRepository;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        OrderItemRepositoryInterface $orderItemRepository,
        InvoiceRepositoryInterface $invoiceRepository,
        DeliveryRepositoryInterface $deliveryRepository,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderItemRepository = $orderItemRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->deliveryRepository = $deliveryRepository;
        $this->customerRepository = $customerRepository;
    }

    /**
     * Display a listing of the orders with pagination, search and filters.
     */
    public function index(Request $request): JsonResponse
    {
        // Validate filter parameters
        $request->validate([
            'search' => 'nullable|string|max:1000',
            'status' => 'nullable|in:pending,processing,completed,cancelled',
            'delivery_type' => 'nullable|in:pickup,delivery',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Prepare filters
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'delivery_type' => $request->input('delivery_type'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];

        // Remove empty filters
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        $perPage = $request->input('per_page', 15);
        $orders = $this->orderRepository->getAllPaginated($filters, $perPage);

        // Transform orders using resource
        $transformedOrders = OrderResource::collection($orders->items());

        // Create a custom response with pagination and filters
        $response = [
            'success' => true,
            'message' => 'Orders retrieved successfully',
            'data' => $transformedOrders,
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'from' => $orders->firstItem(),
                'to' => $orders->lastItem(),
            ]
        ];

        if (!empty($filters)) {
            $response['filters'] = $filters;
        }

        return response()->json($response);
    }

    /**
     * Store a newly created order in storage.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {

        try {
            DB::beginTransaction();

            // Check and validate offer if provided
            $offer = null;
            $offerDiscount = 0.00;
            if ($request->has('offer_id') && $request->input('offer_id')) {
                try {
                    $offer = $this->getActiveOffer($request->input('offer_id'));
                    
                    // Check if offer type is charity and customer_id is provided
                    if ($offer->type === 'charity' && $request->has('customer_id') && $request->input('customer_id')) {
                        DB::rollBack();
                        return $this->errorResponse('This offer is for charities only.', 400);
                    }
                } catch (\Exception $e) {
                    DB::rollBack();
                    return $this->errorResponse($e->getMessage(), 400);
                }
            }

            // Fetch variants and calculate total amount
            $items = $request->input('items', []);
            $totalAmount = 0;
            $orderItemsData = [];

            // Process user-provided items (if any)
            if (!empty($items)) {
                foreach ($items as $item) {
                    $variant = ProductVariant::with('product')->find($item['variant_id']);
                    
                    if (!$variant) {
                        DB::rollBack();
                        return $this->errorResponse('Variant not found: ' . $item['variant_id'], 404);
                    }

                    if (!$variant->is_active) {
                        DB::rollBack();
                        return $this->errorResponse('Variant is not active: ' . $item['variant_id'], 400);
                    }

                    $quantity = $item['quantity'];
                    
                    // Check if variant has sufficient quantity
                    $availableQuantity = $variant->quantity ?? 0;
                    if ($availableQuantity < $quantity) {
                        DB::rollBack();
                        $productName = $variant->product->name_en ?? $variant->product->name_ar ?? 'Product';
                        $sizeInfo = $variant->size ? ' - ' . $variant->size : '';
                        return $this->errorResponse(
                            "Insufficient quantity for {$productName}{$sizeInfo}. Available: {$availableQuantity}, Requested: {$quantity}",
                            400
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
                        'is_offer' => false, // Regular items are not from offer
                    ];
                }
            }

            // Handle offer rewards
            if ($offer) {
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
                                    return $this->errorResponse(
                                        "Insufficient quantity for offer condition product {$productName}{$sizeInfo}. Available: {$availableQuantity}, Required: {$totalNeededQuantity}",
                                        400
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
                                        'is_offer' => true, // Mark as offer item
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
                                    return $this->errorResponse(
                                        "Insufficient quantity for offer reward product {$productName}{$sizeInfo}. Available: {$availableQuantity}, Required: {$rewardQuantity}",
                                        400
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
                                    'unit_price' => $rewardUnitPrice, // Normal price
                                    'total_price' => $rewardTotalPrice, // Normal price
                                    'is_offer' => true, // Mark as offer item
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
            }

            // Generate order number based on source
            $source = 'call_center'; // Default to call_center for admin
            $orderNumber = $this->generateOrderNumber($source);
            
            // Create the order
            $orderData = $request->except(['items', 'source']);
            $orderData['total_amount'] = $totalAmount;
            $orderData['order_number'] = $orderNumber;
            $order = $this->orderRepository->create($orderData);

            // Create order items and update product variant quantities
            foreach ($orderItemsData as $itemData) {
                $itemData['order_id'] = $order->id;
                $this->orderItemRepository->create($itemData);
                
                // Update product variant quantity
                $variant = ProductVariant::find($itemData['variant_id']);
                if ($variant) {
                    $newQuantity = max(0, ($variant->quantity ?? 0) - $itemData['quantity']);
                    $variant->update(['quantity' => $newQuantity]);
                }
            }

            // Handle points discount
            $usedPoints = 0;
            $pointsDiscount = 0.00;
            if ($request->has('used_points') && $request->input('used_points') > 0) {
                $requestedPoints = $request->input('used_points');
                // Get one point discount value from settings
                $onePointDiscount = (float) Setting::getValue('one_point_dicount', 0.1);
                // Calculate discount: points * one_point_discount
                $requestedDiscount = $requestedPoints * $onePointDiscount;
                
                // Don't allow points discount to exceed the remaining amount after offer discount
                $remainingAmount = $totalAmount - $offerDiscount;
                $pointsDiscount = min($requestedDiscount, $remainingAmount);
                
                // Recalculate actual points used based on capped discount
                $usedPoints = (int)($pointsDiscount / $onePointDiscount);
                
                // Reduce points from customer
                if ($order->customer_id && $usedPoints > 0) {
                    $customer = $this->customerRepository->findById($order->customer_id);
                    if ($customer) {
                        $currentPoints = $customer->points ?? 0;
                        $newPoints = max(0, $currentPoints - $usedPoints);
                        $this->customerRepository->update($customer->id, [
                            'points' => $newPoints
                        ]);
                    }
                }
            }

            // Calculate final amount after all discounts
            $finalAmount = $totalAmount - $offerDiscount - $pointsDiscount;
            $totalDiscount = $offerDiscount + $pointsDiscount;

            // $taxAmount = $finalAmount * 0.15;
            // Generate invoice if it doesn't exist
            $existingInvoice = $this->invoiceRepository->getByOrder($order->id);
            if (!$existingInvoice) {
                $invoiceNumber = 'INV-' . $order->order_number;
                $invoice = $this->invoiceRepository->create([
                    'order_id' => $order->id,
                    'invoice_number' => $invoiceNumber,
                    'amount_due' => $finalAmount,
                    'tax_amount' => 0.00,
                    'offer_discount' => $offerDiscount,
                    'used_points' => $usedPoints,
                    'points_discount' => $pointsDiscount,
                    'total_discount' => $totalDiscount,
                    'status' => 'pending',
                ]);
            } else {
                $invoice = $existingInvoice;
            }

            // Handle wallet payment if payment_method is wallet
            $paymentMethod = $request->input('payment_method');
            if ($paymentMethod === 'wallet') {
                if (!$order->customer_id) {
                    DB::rollBack();
                    return $this->errorResponse('Customer ID is required for wallet payment', 400);
                }

                $customer = $this->customerRepository->findById($order->customer_id);
                if (!$customer) {
                    DB::rollBack();
                    return $this->errorResponse('Customer not found', 404);
                }

                $wallet = Wallet::where('customer_id', $customer->id)->first();
                if (!$wallet) {
                    DB::rollBack();
                    return $this->errorResponse('Customer wallet not found', 404);
                }

                // Check if wallet has enough balance
                if ($wallet->balance < $finalAmount) {
                    DB::rollBack();
                    return $this->errorResponse(
                        'Insufficient wallet balance. Available: ' . number_format($wallet->balance, 2) . ', Required: ' . number_format($finalAmount, 2),
                        400
                    );
                }

                // Deduct amount from wallet balance
                $newBalance = max(0, $wallet->balance - $finalAmount);
                $wallet->update(['balance' => $newBalance]);

                // Update invoice status to paid
                $this->invoiceRepository->update($invoice->id, [
                    'paid_at' => now(),
                    'status' => 'paid',
                ]);

            }

            // Create delivery record if delivery_type is "delivery"
            if ($order->delivery_type === 'delivery') {
                $existingDelivery = $this->deliveryRepository->getByOrder($order->id);
                if (!$existingDelivery) {
                    $deliveryData = $request->input('delivery', []);
                    $deliveryData['order_id'] = $order->id;
                    $deliveryData['delivery_status'] = $deliveryData['delivery_status'] ?? 'pending';
                    // Use payment_method from order level if provided, otherwise from delivery or default to cash
                    $deliveryData['payment_method'] = $paymentMethod ?? $deliveryData['payment_method'] ?? 'cash';
                    $this->deliveryRepository->create($deliveryData);
                }
            }

            DB::commit();

            // Reload order with relationships
            $order->load(['customer', 'charity', 'offer', 'items.product', 'items.variant', 'invoice', 'delivery']);

            return $this->createdResponse(new OrderResource($order), 'Order created successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to create order: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified order.
     */
    public function show(int $id): JsonResponse
    {
        $order = $this->orderRepository->findById($id);

        if (!$order) {
            return $this->notFoundResponse('Order not found');
        }

        // Load all relationships
        $order->load(['customer', 'charity', 'offer', 'items.product', 'items.variant', 'invoice', 'delivery']);

        return $this->resourceResponse(new OrderResource($order), 'Order retrieved successfully');
    }

    /**
     * Update the specified order in storage.
     */
    public function update(UpdateOrderRequest $request, int $id): JsonResponse
    {
        $order = $this->orderRepository->findById($id);

        if (!$order) {
            return $this->notFoundResponse('Order not found');
        }

        try {
            DB::beginTransaction();

            // Get current order to check status change and invoice
            $currentOrder = $this->orderRepository->findById($id);
            $oldStatus = $currentOrder ? $currentOrder->status : null;
            $currentInvoice = $this->invoiceRepository->getByOrder($id);
            $oldUsedPoints = $currentInvoice ? $currentInvoice->used_points : 0;

            // Check and validate offer if provided in update
            $offer = null;
            if ($request->has('offer_id') && $request->input('offer_id')) {
                try {
                    $offer = $this->getActiveOffer($request->input('offer_id'));
                    
                    // Check if offer type is charity and customer_id is provided (from request or existing order)
                    $customerId = $request->input('customer_id') ?? $currentOrder->customer_id;
                    if ($offer->type === 'charity' && $customerId) {
                        DB::rollBack();
                        return $this->errorResponse('This offer is for charities only. Please remove customer_id or use a charity_id instead.', 400);
                    }
                } catch (\Exception $e) {
                    DB::rollBack();
                    return $this->errorResponse($e->getMessage(), 400);
                }
            }

            // Update the order (excluding items and total_amount - will be recalculated)
            $orderData = $request->except(['items', 'total_amount', 'used_points']);
            if (!empty($orderData)) {
                $order = $this->orderRepository->update($id, $orderData);
            } else {
                $order = $currentOrder;
            }

            // Check if order status changed to "completed" and add offer points
            $newStatus = isset($orderData['status']) ? $orderData['status'] : $oldStatus;
            if ($order && $oldStatus !== 'completed' && $newStatus === 'completed') {
                // Reload order to get fresh data
                $order = $this->orderRepository->findById($id);
                $this->addOfferPointsToCustomer($order);
            }

            // Handle order items if provided
            $recalculatedTotalAmount = null;
            if ($request->has('items')) {
                $items = $request->input('items', []);
                
                // If items array is provided but empty, and offer is present, allow it
                // Otherwise, process items normally
                if (!empty($items)) {
                    $existingItemIds = collect($items)->pluck('id')->filter()->toArray();
                    
                    // Get all current order items
                    $currentItems = $this->orderItemRepository->getByOrder($id);
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
                            return $this->errorResponse('Variant not found: ' . $item['variant_id'], 404);
                        }

                        if (!$variant->is_active) {
                            DB::rollBack();
                            return $this->errorResponse('Variant is not active: ' . $item['variant_id'], 400);
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
                            return $this->errorResponse(
                                "Insufficient quantity for {$productName}{$sizeInfo}. Available: {$availableQuantity}, Requested: {$quantity}",
                                400
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
                            'is_offer' => false, // Regular items are not from offer
                        ];

                        if (isset($item['id']) && $item['id']) {
                            // Update existing item - adjust quantities
                            $existingItem = $this->orderItemRepository->findById($item['id']);
                            if ($existingItem) {
                                $oldQuantity = $existingItem->quantity;
                                $newQuantity = $quantity;
                                $quantityDiff = $newQuantity - $oldQuantity;
                                
                                // Restore old quantity and deduct new quantity
                                $variant = ProductVariant::find($item['variant_id']);
                                if ($variant) {
                                    $currentQuantity = $variant->quantity ?? 0;
                                    $newVariantQuantity = $currentQuantity + $oldQuantity - $newQuantity;
                                    $newVariantQuantity = max(0, $newVariantQuantity);
                                    $variant->update(['quantity' => $newVariantQuantity]);
                                }
                            }
                            
                            $orderItemData['order_id'] = $id;
                            $this->orderItemRepository->update($item['id'], $orderItemData);
                        } else {
                            // Create new item - deduct quantity
                            $orderItemData['order_id'] = $id;
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
                } else {
                    // Items array is empty - if offer is provided, it will add items
                    // Otherwise, we need to ensure there are items
                    $updatedOfferId = $request->input('offer_id') ?? $order->offer_id;
                    if (!$updatedOfferId) {
                        DB::rollBack();
                        return $this->errorResponse('Items are required when no offer is provided.', 400);
                    }
                    
                    // Clear existing items if offer will add new ones and restore quantities
                    $currentItems = $this->orderItemRepository->getByOrder($id);
                    foreach ($currentItems as $currentItem) {
                        // Restore variant quantity
                        $variant = ProductVariant::find($currentItem->variant_id);
                        if ($variant) {
                            $newQuantity = ($variant->quantity ?? 0) + $currentItem->quantity;
                            $variant->update(['quantity' => $newQuantity]);
                        }
                        $this->orderItemRepository->delete($currentItem->id);
                    }
                    
                    $totalAmount = 0;
                    $orderItemsData = [];
                }

                // Handle offer rewards if offer is provided (check updated offer or existing offer)
                $updatedOfferId = $request->input('offer_id') ?? $order->offer_id;
                $currentOffer = $updatedOfferId ? Offer::find($updatedOfferId) : null;
                if ($currentOffer && $currentOffer->reward_type === 'products') {
                    // Add condition products to order items (if not already present)
                    $activeConditions = $currentOffer->activeConditions()->with(['product', 'productVariant'])->get();
                    foreach ($activeConditions as $condition) {
                        if ($condition->product_variant_id) {
                            $variant = ProductVariant::with('product')->find($condition->product_variant_id);
                            if ($variant && $variant->is_active) {
                                // Check if this variant is already in order items
                                $existingItem = collect($orderItemsData)->firstWhere('variant_id', $variant->id);
                                
                                if (!$existingItem) {
                                    // Add condition product with normal price
                                    $productName = $variant->product->name_en ?? $variant->product->name_ar ?? 'Product';
                                    if ($variant->size) {
                                        $productName .= ' - ' . $variant->size;
                                    }
                                    
                                    $conditionQuantity = $condition->quantity;
                                    $conditionUnitPrice = $variant->price;
                                    $conditionTotalPrice = $conditionUnitPrice * $conditionQuantity;
                                    
                                    $conditionItemData = [
                                        'order_id' => $id,
                                        'variant_id' => $variant->id,
                                        'product_id' => $variant->product_id,
                                        'name' => $productName,
                                        'sku' => $variant->sku,
                                        'quantity' => $conditionQuantity,
                                        'unit_price' => $conditionUnitPrice,
                                        'total_price' => $conditionTotalPrice,
                                        'is_offer' => true,
                                    ];
                                    
                                    $this->orderItemRepository->create($conditionItemData);
                                    $totalAmount += $conditionTotalPrice;
                                    
                                    // Update variant quantity
                                    $variant = ProductVariant::find($condition->product_variant_id);
                                    if ($variant) {
                                        $newQuantity = max(0, ($variant->quantity ?? 0) - $conditionQuantity);
                                        $variant->update(['quantity' => $newQuantity]);
                                    }
                                }
                            }
                        }
                    }
                    
                    // Add reward products to order items with normal prices
                    // Their total price will be added to offer_discount
                    $rewardProductsTotal = 0.00;
                    $activeRewards = $currentOffer->activeRewards()->with(['product', 'productVariant'])->get();
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
                                    return $this->errorResponse(
                                        "Insufficient quantity for offer reward product {$productName}{$sizeInfo}. Available: {$availableQuantity}, Required: {$rewardQuantity}",
                                        400
                                    );
                                }
                                
                                $rewardUnitPrice = $variant->price;
                                $rewardTotalPrice = $rewardUnitPrice * $rewardQuantity;
                                $rewardProductsTotal += $rewardTotalPrice;
                                
                                $rewardItemData = [
                                    'order_id' => $id,
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
                                $totalAmount += $rewardTotalPrice;
                                
                                // Update variant quantity
                                $variant = ProductVariant::find($reward->product_variant_id);
                                if ($variant) {
                                    $newQuantity = max(0, ($variant->quantity ?? 0) - $rewardQuantity);
                                    $variant->update(['quantity' => $newQuantity]);
                                }
                            }
                        }
                    }
                }

                // Update order total amount
                $this->orderRepository->update($id, ['total_amount' => $totalAmount]);
                $recalculatedTotalAmount = $totalAmount;
            }

            // Handle points discount update
            $usedPoints = 0;
            $pointsDiscount = 0.00;
            
            // Get offer discount - use calculated value if items were processed with offer, otherwise use existing
            $calculatedOfferDiscount = null;
            if ($request->has('items')) {
                $updatedOfferId = $request->input('offer_id') ?? $order->offer_id;
                $currentOffer = $updatedOfferId ? Offer::find($updatedOfferId) : null;
                if ($currentOffer && $currentOffer->reward_type === 'products') {
                    // Calculate reward products total for discount
                    $rewardProductsTotal = 0.00;
                    $activeRewards = $currentOffer->activeRewards()->with(['product', 'productVariant'])->get();
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
                    $calculatedOfferDiscount = $rewardProductsTotal;
                } elseif ($currentOffer && $currentOffer->reward_type === 'discount') {
                    // Calculate discount from rewards
                    $currentTotalAmount = $recalculatedTotalAmount ?? ($order->total_amount ?? 0);
                    $calculatedOfferDiscount = 0.00;
                    $activeRewards = $currentOffer->activeRewards()->get();
                    foreach ($activeRewards as $reward) {
                        if ($reward->discount_amount && $reward->discount_type) {
                            if ($reward->discount_type === 'percentage') {
                                $discount = ($currentTotalAmount * $reward->discount_amount) / 100;
                            } else {
                                $discount = $reward->discount_amount;
                            }
                            $calculatedOfferDiscount += $discount;
                        }
                    }
                    $calculatedOfferDiscount = min($calculatedOfferDiscount, $currentTotalAmount);
                }
            }
            
            $offerDiscount = $calculatedOfferDiscount !== null 
                ? $calculatedOfferDiscount 
                : ($currentInvoice ? ($currentInvoice->offer_discount ?? 0.00) : 0.00);
            
            // Get current total amount (either from recalculated items or existing order)
            $currentTotalAmount = $recalculatedTotalAmount ?? ($order->total_amount ?? 0);

            if ($request->has('used_points')) {
                $requestedPoints = $request->input('used_points', 0);
                
                // Refund old points to customer
                if ($oldUsedPoints > 0 && $order->customer_id) {
                    $customer = $this->customerRepository->findById($order->customer_id);
                    if ($customer) {
                        $currentPoints = $customer->points ?? 0;
                        $this->customerRepository->update($customer->id, [
                            'points' => $currentPoints + $oldUsedPoints
                        ]);
                    }
                }

                // Handle new points if provided
                if ($requestedPoints > 0) {
                    // Get one point discount value from settings
                    $onePointDiscount = (float) Setting::getValue('one_point_dicount', 0.1);
                    // Calculate discount: points * one_point_discount
                    $requestedDiscount = $requestedPoints * $onePointDiscount;
                    
                    // Don't allow points discount to exceed the remaining amount after offer discount
                    $remainingAmount = $currentTotalAmount - $offerDiscount;
                    $pointsDiscount = min($requestedDiscount, $remainingAmount);
                    
                    // Recalculate actual points used based on capped discount
                    $usedPoints = (int)($pointsDiscount / $onePointDiscount);
                    
                    // Deduct new points from customer
                    if ($order->customer_id && $usedPoints > 0) {
                        $customer = $this->customerRepository->findById($order->customer_id);
                        if ($customer) {
                            $currentPoints = $customer->points ?? 0;
                            $newPoints = max(0, $currentPoints - $usedPoints);
                            $this->customerRepository->update($customer->id, [
                                'points' => $newPoints
                            ]);
                        }
                    }
                }
            } else {
                // Keep old points if not provided
                $usedPoints = $oldUsedPoints;
                $pointsDiscount = $currentInvoice ? ($currentInvoice->points_discount ?? 0.00) : 0.00;
            }

            // Calculate final amount after all discounts
            $finalAmount = $currentTotalAmount - $offerDiscount - $pointsDiscount;
            $totalDiscount = $offerDiscount + $pointsDiscount;

            // Update invoice with new values
            $invoice = $this->invoiceRepository->getByOrder($id);
            if ($invoice) {
                $this->invoiceRepository->update($invoice->id, [
                    'amount_due' => $finalAmount,
                    'offer_discount' => $offerDiscount,
                    'used_points' => $usedPoints,
                    'points_discount' => $pointsDiscount,
                    'total_discount' => $totalDiscount,
                ]);
            }

            DB::commit();

            // Reload order with relationships
            $order = $this->orderRepository->findById($id);
            $order->load(['customer', 'charity', 'offer', 'items.product', 'items.variant', 'invoice', 'delivery']);

            return $this->updatedResponse(new OrderResource($order), 'Order updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to update order: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified order from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->orderRepository->delete($id);

        if (!$deleted) {
            return $this->notFoundResponse('Order not found');
        }

        return $this->deletedResponse('Order deleted successfully');
    }

    /**
     * Generate unique order number based on source
     *
     * @param string $source (app, web, call_center)
     * @return string
     */
    protected function generateOrderNumber(string $source): string
    {
        // Map source to prefix
        $prefixes = [
            'app' => 'APPS',
            'web' => 'WEBS',
            'call_center' => 'CALS',
        ];
        
        $prefix = $prefixes[$source] ?? 'CALS';
        $year = date('Y');
        $pattern = $prefix . '-' . $year . '-%';
        
        // Find the last order number with this prefix and year
        $lastOrder = Order::where('order_number', 'LIKE', $pattern)
            ->orderBy('order_number', 'desc')
            ->first();
        
        // Extract sequence number from last order
        $sequence = 1;
        if ($lastOrder) {
            // Extract the sequence part (last 6 digits after the year)
            $parts = explode('-', $lastOrder->order_number);
            if (count($parts) === 3 && isset($parts[2])) {
                $sequence = (int) $parts[2] + 1;
            }
        }
        
        // Format: PREFIX-YEAR-000001 (6 digits with leading zeros)
        return sprintf('%s-%s-%06d', $prefix, $year, $sequence);
    }

    /**
     * Add offer points to customer when order is completed
     *
     * @param \App\Models\Order $order
     * @return void
     */
    protected function addOfferPointsToCustomer($order): void
    {
        if (!$order->customer_id || !$order->offer_id) {
            return;
        }

        $offer = Offer::find($order->offer_id);
        if (!$offer || !$offer->points || $offer->points <= 0) {
            return;
        }

        $customer = $this->customerRepository->findById($order->customer_id);
        if ($customer) {
            $currentPoints = $customer->points ?? 0;
            $this->customerRepository->update($customer->id, [
                'points' => $currentPoints + $offer->points
            ]);
        }
    }
}


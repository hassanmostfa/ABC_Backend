<?php

namespace App\Services;

use App\Repositories\OrderRepositoryInterface;
use App\Repositories\InvoiceRepositoryInterface;
use App\Repositories\CustomerRepositoryInterface;
use App\Models\Order;
use App\Models\Offer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class OrderService
{
    protected $orderRepository;
    protected $invoiceRepository;
    protected $orderItemService;
    protected $offerService;
    protected $invoiceService;
    protected $walletService;
    protected $pointsService;
    protected $customerRepository;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        InvoiceRepositoryInterface $invoiceRepository,
        OrderItemService $orderItemService,
        OfferService $offerService,
        InvoiceService $invoiceService,
        WalletService $walletService,
        PointsService $pointsService,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->orderItemService = $orderItemService;
        $this->offerService = $offerService;
        $this->invoiceService = $invoiceService;
        $this->walletService = $walletService;
        $this->pointsService = $pointsService;
        $this->customerRepository = $customerRepository;
    }

    /**
     * Create a new order
     *
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function createOrder(array $data): array
    {
        DB::beginTransaction();

        try {
            // Collect offers - support both old format (offer_ids) and new format (offers with quantity)
            $offersData = [];
            if (isset($data['offers']) && is_array($data['offers'])) {
                // New format: [{"offer_id": 10, "quantity": 2}, ...]
                foreach ($data['offers'] as $offerData) {
                    if (isset($offerData['offer_id'])) {
                        $offersData[] = [
                            'offer_id' => $offerData['offer_id'],
                            'quantity' => isset($offerData['quantity']) ? (int) $offerData['quantity'] : 1
                        ];
                    }
                }
            } elseif (isset($data['offer_ids']) && is_array($data['offer_ids'])) {
                // Old format: [10, 11, 12] - default quantity is 1
                foreach ($data['offer_ids'] as $offerId) {
                    $offersData[] = [
                        'offer_id' => $offerId,
                        'quantity' => 1
                    ];
                }
            }

            // Validate all offers and prepare for processing
            $offersToProcess = []; // For processing rewards
            $offersToAttach = []; // For attaching to order with quantities
            
            foreach ($offersData as $offerData) {
                $offer = $this->offerService->validateOffer($offerData['offer_id'], $data['customer_id'] ?? null);
                if ($offer) {
                    $quantity = $offerData['quantity'];
                    // Add offer multiple times based on quantity for processing
                    for ($i = 0; $i < $quantity; $i++) {
                        $offersToProcess[] = $offer;
                    }
                    // Store for attaching with quantity
                    $offersToAttach[$offer->id] = ['quantity' => $quantity];
                }
            }

            // Process order items
            $items = $data['items'] ?? [];
            $totalAmount = 0;
            $orderItemsData = [];

            if (!empty($items)) {
                $result = $this->orderItemService->processItems($items);
                $orderItemsData = $result['orderItemsData'];
                $totalAmount = $result['totalAmount'];
            }

            // Handle offer rewards for all offers (process sequentially, respecting quantities)
            $offerDiscount = 0.00;
            foreach ($offersToProcess as $offer) {
                $offerResult = $this->offerService->processOfferRewards($offer, $orderItemsData, $totalAmount);
                $orderItemsData = $offerResult['orderItemsData'];
                $totalAmount = $offerResult['totalAmount'];
                $offerDiscount += $offerResult['offerDiscount']; // Accumulate discounts from all offers
            }

            // Handle points discount (calculate before creating order)
            $requestedPoints = $data['used_points'] ?? 0;
            $pointsResult = $this->pointsService->calculateDiscount($requestedPoints, $totalAmount, $offerDiscount);
            $usedPoints = $pointsResult['usedPoints'];
            $pointsDiscount = $pointsResult['pointsDiscount'];

            // Determine delivery type for invoice calculation
            $deliveryType = $data['delivery_type'] ?? null;
            if (!$deliveryType && isset($data['customer_address_id']) && $data['customer_address_id']) {
                $deliveryType = 'delivery';
            } elseif (!$deliveryType) {
                $deliveryType = 'pickup';
            }

            // Validate minimum order amount based on order type (charity vs customer)
            $charityId = $data['charity_id'] ?? null;
            $customerId = $data['customer_id'] ?? null;
            
            if ($charityId) {
                // Charity order - check minimum charity order
                $minimumCharityOrder = (float) \App\Models\Setting::getValue('minimum_charity_order', 13);
                if ($totalAmount < $minimumCharityOrder) {
                    DB::rollBack();
                    throw new \Exception("Minimum charity order amount is {$minimumCharityOrder}. Current order amount is {$totalAmount}.");
                }
            } elseif ($customerId) {
                // Customer/home order - check minimum home order
                $minimumHomeOrder = (float) \App\Models\Setting::getValue('minimum_home_order', 5);
                if ($totalAmount < $minimumHomeOrder) {
                    DB::rollBack();
                    throw new \Exception("Minimum home order amount is {$minimumHomeOrder}. Current order amount is {$totalAmount}.");
                }
            }

            // Calculate invoice amounts (before creating order) - includes delivery fee if delivery
            $invoiceAmounts = $this->invoiceService->calculateAmounts($totalAmount, $offerDiscount, $pointsDiscount, $deliveryType);
            $amountDue = $invoiceAmounts['amountDue'];

            // Get payment method from root level
            $paymentMethod = $data['payment_method'] ?? null;
            $isWalletPayment = ($paymentMethod === 'wallet');
            
            // Validate wallet balance BEFORE creating order if payment method is wallet
            if ($isWalletPayment) {
                $customerId = $data['customer_id'] ?? null;
                if (!$customerId) {
                    DB::rollBack();
                    throw new \Exception('Customer ID is required for wallet payment');
                }
                $this->walletService->validateBalance($customerId, $amountDue);
            }

            // Generate order number
            $source = 'call_center'; // Default to call_center for admin
            $orderNumber = $this->generateOrderNumber($source);
            
            // Create the order
            $orderData = Arr::except($data, ['items', 'source', 'offer_ids', 'offers']); // Remove offer_ids and offers from order data
            
            // Remove delivery_type if it's "delivery" - it will be auto-determined
            if (isset($orderData['delivery_type']) && $orderData['delivery_type'] === 'delivery') {
                unset($orderData['delivery_type']);
            }
            
            $orderData['total_amount'] = $totalAmount;
            $orderData['order_number'] = $orderNumber;
            
            // Auto-determine delivery_type if not provided
            // If customer_address_id is provided, it's delivery; otherwise pickup
            if (!isset($orderData['delivery_type']) || $orderData['delivery_type'] === null) {
                if (isset($data['customer_address_id']) && $data['customer_address_id']) {
                    $orderData['delivery_type'] = 'delivery';
                } else {
                    $orderData['delivery_type'] = 'pickup';
                }
            }
            
            // Normalize payment method to allowed values for orders table
            $paymentMethod = $data['payment_method'] ?? null;
            if ($paymentMethod && in_array($paymentMethod, ['cash', 'wallet', 'online_link'])) {
                $orderData['payment_method'] = $paymentMethod;
            }
            $order = $this->orderRepository->create($orderData);

            // Attach all offers to the order (many-to-many) with quantities
            if (!empty($offersToAttach)) {
                $order->offers()->attach($offersToAttach);
            }

            // Create order items
            $this->orderItemService->createOrderItems($order->id, $orderItemsData);
            
            // Deduct points from customer after order is created
            if ($usedPoints > 0 && $order->customer_id) {
                $this->pointsService->deductPoints($order->customer_id, $usedPoints);
            }

            // Create invoice (as paid if wallet payment)
            $invoice = $this->invoiceService->createOrGetInvoice(
                $order->id,
                $order->order_number,
                $invoiceAmounts['amountDue'],
                $invoiceAmounts['taxAmount'],
                $invoiceAmounts['deliveryFee'],
                $offerDiscount,
                $usedPoints,
                $pointsDiscount,
                $invoiceAmounts['totalDiscount'],
                $isWalletPayment
            );

            // Process wallet payment deduction if applicable
            if ($isWalletPayment) {
                $this->walletService->deductBalance($order->customer_id, $amountDue);
            }

            DB::commit();

            // Reload order with relationships
            $order->load(['customer', 'charity', 'offers', 'items.product', 'items.variant', 'invoice', 'customerAddress']);

            return [
                'success' => true,
                'order' => $order
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update an existing order
     *
     * @param int $id
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function updateOrder(int $id, array $data): array
    {
        DB::beginTransaction();

        try {
            $order = $this->orderRepository->findById($id);
            if (!$order) {
                DB::rollBack();
                throw new \Exception('Order not found');
            }

            // Get current order state
            $currentOrder = $this->orderRepository->findById($id);
            $oldStatus = $currentOrder ? $currentOrder->status : null;
            $currentInvoice = $this->invoiceRepository->getByOrder($id);
            $oldUsedPoints = $currentInvoice ? $currentInvoice->used_points : 0;
            

            $customerId = $data['customer_id'] ?? $currentOrder->customer_id;

            // Get payment method from root level
            $paymentMethod = $data['payment_method'] ?? null;
            // Normalize payment method to allowed values for orders table
            $paymentMethodForOrder = ($paymentMethod && in_array($paymentMethod, ['cash', 'wallet', 'online_link'])) 
                ? $paymentMethod 
                : null;
            
            // Update the order (excluding items, total_amount, offer_ids, and offers)
            $orderData = Arr::except($data, ['items', 'total_amount', 'used_points', 'offer_ids', 'offers']);
            // Add payment_method to order data if it's a valid value
            if ($paymentMethodForOrder) {
                $orderData['payment_method'] = $paymentMethodForOrder;
            }
            if (!empty($orderData)) {
                $order = $this->orderRepository->update($id, $orderData);
            } else {
                $order = $currentOrder;
            }

            // Check if order status changed to "completed" and add offer points
            $newStatus = $orderData['status'] ?? $oldStatus;
            if ($order && $oldStatus !== 'completed' && $newStatus === 'completed') {
                $order = $this->orderRepository->findById($id);
                $this->offerService->addOfferPointsToCustomer($order, $this->customerRepository);
            }

            // Handle order items if provided
            $recalculatedTotalAmount = null;
            $calculatedOfferDiscount = null;
            if (isset($data['items'])) {
                // Clear items if empty and no offers
                if (empty($data['items'])) {
                    $updatedOfferIds = $data['offer_ids'] ?? [];
                    $currentOfferIds = $order->offers()->pluck('offers.id')->toArray();
                    $hasOffers = !empty($updatedOfferIds) || !empty($currentOfferIds);
                    if (!$hasOffers) {
                        DB::rollBack();
                        throw new \Exception('Items are required when no offers are provided.');
                    }
                    $this->orderItemService->clearOrderItems($id);
                    $recalculatedTotalAmount = 0;
                } else {
                    $result = $this->orderItemService->processItemsForUpdate($id, $data['items']);
                    $recalculatedTotalAmount = $result['totalAmount'];
                }
                
                // Calculate offer discount if offers are present
                $calculatedOfferDiscount = 0.00;
                $updatedOfferIds = $data['offer_ids'] ?? [];
                $offersToProcess = [];
                
                if (!empty($updatedOfferIds)) {
                    // Use updated offer IDs
                    $offersToProcess = Offer::whereIn('id', $updatedOfferIds)->get();
                } else {
                    // Use current order offers (load them if not already loaded)
                    $offersToProcess = $order->relationLoaded('offers') 
                        ? $order->offers 
                        : $order->offers()->get();
                }
                    
                if ($offersToProcess->isNotEmpty()) {
                    foreach ($offersToProcess as $currentOffer) {
                        // Create offer reward items if reward type is products
                        if ($currentOffer->reward_type === 'products') {
                            $additionalTotal = $this->offerService->createOfferRewardItems($id, $currentOffer);
                            $recalculatedTotalAmount = ($recalculatedTotalAmount ?? $order->total_amount ?? 0) + $additionalTotal;
                            $this->orderRepository->update($id, ['total_amount' => $recalculatedTotalAmount]);
                        }
                        
                        $discount = $this->offerService->calculateOfferDiscount(
                            $currentOffer,
                            $recalculatedTotalAmount ?? $order->total_amount ?? 0
                        );
                        $calculatedOfferDiscount += $discount;
                    }
                }
            }

            // Handle points discount update
            $pointsResult = $this->pointsService->handlePointsUpdate(
                $data,
                $order->customer_id,
                $oldUsedPoints,
                $recalculatedTotalAmount ?? $order->total_amount ?? 0,
                $calculatedOfferDiscount ?? ($currentInvoice ? ($currentInvoice->offer_discount ?? 0.00) : 0.00),
                $currentInvoice ? ($currentInvoice->points_discount ?? null) : null
            );
            $usedPoints = $pointsResult['usedPoints'];
            $pointsDiscount = $pointsResult['pointsDiscount'];
            $offerDiscount = $calculatedOfferDiscount ?? ($currentInvoice ? ($currentInvoice->offer_discount ?? 0.00) : 0.00);
            $currentTotalAmount = $recalculatedTotalAmount ?? ($order->total_amount ?? 0);

            // Validate minimum order amount based on order type (charity vs customer)
            $charityId = $data['charity_id'] ?? $order->charity_id;
            $customerId = $data['customer_id'] ?? $order->customer_id;
            
            if ($charityId) {
                // Charity order - check minimum charity order
                $minimumCharityOrder = (float) \App\Models\Setting::getValue('minimum_charity_order', 13);
                if ($currentTotalAmount < $minimumCharityOrder) {
                    DB::rollBack();
                    throw new \Exception("Minimum charity order amount is {$minimumCharityOrder}. Current order amount is {$currentTotalAmount}.");
                }
            } elseif ($customerId) {
                // Customer/home order - check minimum home order
                $minimumHomeOrder = (float) \App\Models\Setting::getValue('minimum_home_order', 5);
                if ($currentTotalAmount < $minimumHomeOrder) {
                    DB::rollBack();
                    throw new \Exception("Minimum home order amount is {$minimumHomeOrder}. Current order amount is {$currentTotalAmount}.");
                }
            }

            // Determine delivery type for invoice calculation
            $deliveryType = $data['delivery_type'] ?? $order->delivery_type ?? null;
            if (!$deliveryType && isset($data['customer_address_id']) && $data['customer_address_id']) {
                $deliveryType = 'delivery';
            } elseif (!$deliveryType) {
                $deliveryType = 'pickup';
            }

            // Calculate invoice amounts (includes delivery fee if delivery)
            $invoiceAmounts = $this->invoiceService->calculateAmounts($currentTotalAmount, $offerDiscount, $pointsDiscount, $deliveryType);
            $newAmountDue = $invoiceAmounts['amountDue'];

            // Get payment method from root level or existing order payment method
            $paymentMethod = $paymentMethodForOrder ?? $currentOrder->payment_method ?? null;
            $isWalletPayment = ($paymentMethod === 'wallet');
            
            // Get old payment method from order
            $oldOrderPaymentMethod = $currentOrder->payment_method ?? null;
            $oldPaymentMethod = $oldOrderPaymentMethod ?? null;
            
            // Check payment method changes
            $paymentMethodChangedToWallet = ($isWalletPayment && $oldPaymentMethod !== 'wallet');
            $paymentMethodChangedFromWallet = (!$isWalletPayment && $oldPaymentMethod === 'wallet');

            // Handle wallet balance adjustments
            if ($order->customer_id && $currentInvoice) {
                $oldAmountDue = $currentInvoice->amount_due ?? 0;
                $wasPaidWithWallet = ($currentInvoice->status === 'paid' && $currentInvoice->paid_at && $oldPaymentMethod === 'wallet');
                
                if ($wasPaidWithWallet) {
                    // Invoice was already paid with wallet - calculate paid amount and compare with new price
                    if ($isWalletPayment) {
                        // Still wallet payment - always adjust balance (refund old, deduct new)
                        $this->walletService->adjustBalance(
                            $order->customer_id,
                            $oldAmountDue,
                            $newAmountDue
                        );
                    } else {
                        // Payment method changed from wallet - refund the old paid amount
                        $this->walletService->addBalance($order->customer_id, $oldAmountDue);
                        // Mark invoice as unpaid
                        $this->invoiceService->markAsUnpaid($currentInvoice->id);
                    }
                } elseif ($paymentMethodChangedToWallet) {
                    // Payment method changed to wallet - validate and deduct new amount
                    $this->walletService->validateBalance($order->customer_id, $newAmountDue);
                    $this->walletService->deductBalance($order->customer_id, $newAmountDue);
                } elseif ($isWalletPayment && !$wasPaidWithWallet) {
                    // Already wallet payment but wasn't paid yet - validate and deduct
                    $this->walletService->validateBalance($order->customer_id, $newAmountDue);
                    $this->walletService->deductBalance($order->customer_id, $newAmountDue);
                }
            }

            // Update invoice (mark as paid if wallet payment)
            if ($currentInvoice) {
                $this->invoiceService->updateInvoice(
                    $currentInvoice->id,
                    $invoiceAmounts['amountDue'],
                    $invoiceAmounts['taxAmount'],
                    $invoiceAmounts['deliveryFee'],
                    $offerDiscount,
                    $usedPoints,
                    $pointsDiscount,
                    $invoiceAmounts['totalDiscount'],
                    $isWalletPayment // Pass isPaid flag
                );
            }

            // Handle offers sync if provided (before commit)
            if (isset($data['offers']) || isset($data['offer_ids'])) {
                $offersData = [];
                
                if (isset($data['offers']) && is_array($data['offers'])) {
                    // New format: [{"offer_id": 10, "quantity": 2}, ...]
                    foreach ($data['offers'] as $offerData) {
                        if (isset($offerData['offer_id'])) {
                            $offersData[] = [
                                'offer_id' => $offerData['offer_id'],
                                'quantity' => isset($offerData['quantity']) ? (int) $offerData['quantity'] : 1
                            ];
                        }
                    }
                } elseif (isset($data['offer_ids']) && is_array($data['offer_ids'])) {
                    // Old format: [10, 11, 12] - default quantity is 1
                    foreach ($data['offer_ids'] as $offerId) {
                        $offersData[] = [
                            'offer_id' => $offerId,
                            'quantity' => 1
                        ];
                    }
                }
                
                // Validate all offers and prepare for sync
                $offersToSync = [];
                foreach ($offersData as $offerData) {
                    $offer = $this->offerService->validateOffer($offerData['offer_id'], $customerId);
                    if ($offer) {
                        $offersToSync[$offer->id] = ['quantity' => $offerData['quantity']];
                    }
                }
                
                // Sync offers to order with quantities
                if (!empty($offersToSync)) {
                    $order->offers()->sync($offersToSync);
                }
            }

            DB::commit();

            // Reload order with relationships
            $order = $this->orderRepository->findById($id);
            $order->load(['customer', 'charity', 'offers', 'items.product', 'items.variant', 'invoice', 'customerAddress']);

            return [
                'success' => true,
                'order' => $order
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate unique order number based on source
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
}


<?php

namespace App\Services;

use App\Repositories\OrderRepositoryInterface;
use App\Repositories\InvoiceRepositoryInterface;
use App\Repositories\CustomerRepositoryInterface;
use App\Exceptions\PendingOnlineInvoiceException;
use App\Models\Order;
use App\Models\Invoice;
use App\Models\Offer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\UpaymentsService;

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
    protected $upaymentsService;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        InvoiceRepositoryInterface $invoiceRepository,
        OrderItemService $orderItemService,
        OfferService $offerService,
        InvoiceService $invoiceService,
        WalletService $walletService,
        PointsService $pointsService,
        CustomerRepositoryInterface $customerRepository,
        UpaymentsService $upaymentsService
    ) {
        $this->orderRepository = $orderRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->orderItemService = $orderItemService;
        $this->offerService = $offerService;
        $this->invoiceService = $invoiceService;
        $this->walletService = $walletService;
        $this->pointsService = $pointsService;
        $this->customerRepository = $customerRepository;
        $this->upaymentsService = $upaymentsService;
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

            // Store original total before offers for minimum order validation
            $originalTotalAmount = $totalAmount;

            // Handle offer rewards for all offers (process sequentially, respecting quantities)
            $offerDiscount = 0.00;
            foreach ($offersToProcess as $offer) {
                $offerResult = $this->offerService->processOfferRewards($offer, $orderItemsData, $totalAmount);
                $orderItemsData = $offerResult['orderItemsData'];
                $totalAmount = $offerResult['totalAmount'];
                $offerDiscount += $offerResult['offerDiscount']; // Accumulate discounts from all offers
            }

            $this->orderItemService->applyLineTax($orderItemsData);

            // Calculate final amount after discounts for minimum validation
            $finalAmountAfterDiscounts = $totalAmount - $offerDiscount;

            // Handle points discount (calculate before creating order)
            $requestedPoints = $data['used_points'] ?? 0;
            $pointsResult = $this->pointsService->calculateDiscount($requestedPoints, $totalAmount, $offerDiscount);
            $usedPoints = $pointsResult['usedPoints'];
            $pointsDiscount = $pointsResult['pointsDiscount'];
            $couponsDiscount = (float) ($data['coupons_discount'] ?? 0);

            // Calculate final amount after all discounts for minimum validation
            $finalAmountAfterAllDiscounts = $totalAmount - $offerDiscount - $pointsDiscount;

            // Determine delivery type for invoice calculation
            $deliveryType = $data['delivery_type'] ?? null;
            if (!$deliveryType && isset($data['customer_address_id']) && $data['customer_address_id']) {
                $deliveryType = 'delivery';
            } elseif (!$deliveryType) {
                $deliveryType = 'pickup';
            }

            // Validate minimum order amount based on order type (charity vs customer)
            // Check on the final amount after all discounts
            $charityId = $data['charity_id'] ?? null;
            $customerId = $data['customer_id'] ?? null;
            
            if ($charityId) {
                // Charity order - check minimum charity order
                $minimumCharityOrder = (float) \App\Models\Setting::getValue('minimum_charity_order', 13);
                if ($finalAmountAfterAllDiscounts < $minimumCharityOrder) {
                    DB::rollBack();
                    throw new \Exception("Minimum charity order amount is {$minimumCharityOrder}. Current order amount after discounts is {$finalAmountAfterAllDiscounts}.");
                }
            } elseif ($customerId) {
                // Customer/home order - check minimum home order
                $minimumHomeOrder = (float) \App\Models\Setting::getValue('minimum_home_order', 5);
                if ($finalAmountAfterAllDiscounts < $minimumHomeOrder) {
                    DB::rollBack();
                    throw new \Exception("Minimum home order amount is {$minimumHomeOrder}. Current order amount after discounts is {$finalAmountAfterAllDiscounts}.");
                }
            }

            // Calculate invoice amounts (before creating order) - includes delivery fee if delivery
            $invoiceAmounts = $this->invoiceService->calculateAmounts(
                $totalAmount,
                $offerDiscount,
                $couponsDiscount,
                $pointsDiscount,
                $deliveryType
            );
            $amountDue = $invoiceAmounts['amountDue'];

            // Get payment method from root level
            $paymentMethod = $data['payment_method'] ?? null;
            $paymentGatewaySrc = $data['src'] ?? null;
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

            if ($paymentMethod === 'online_link' && $customerId) {
                $pendingOrders = Order::query()
                    ->where('customer_id', $customerId)
                    ->where('payment_method', 'online_link')
                    ->where('status', '!=', 'cancelled')
                    ->whereHas('invoice', function ($q) {
                        $q->where('status', 'pending');
                    })
                    ->with(['invoice'])
                    ->orderByDesc('id')
                    ->get();

                if ($pendingOrders->isNotEmpty()) {
                    DB::rollBack();
                    $pendingOrders->load([
                        'customer',
                        'charity',
                        'offers',
                        'items.product',
                        'items.variant',
                        'invoice',
                        'customerAddress',
                    ]);

                    throw new PendingOnlineInvoiceException(
                        'You cannot create a new order with online payment until you pay or cancel your existing order with a pending invoice.',
                        $pendingOrders->values()
                    );
                }
            }

            // Generate order number (source: app = mobile, call_center = admin)
            $source = $data['source'] ?? 'call_center';
            $orderNumber = $this->generateOrderNumber($source);
            
            // Create the order
            $orderData = Arr::except($data, ['items', 'source', 'offer_ids', 'offers', 'src']); // Remove offer_ids and offers from order data
            
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
            if ($paymentGatewaySrc !== null && $paymentGatewaySrc !== '') {
                $orderData['payment_gateway_src'] = $paymentGatewaySrc;
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
                $couponsDiscount,
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

            $response = [
                'success' => true,
                'order' => $order
            ];

            // Generate payment link AFTER commit (avoids holding transaction; use reasonable timeout for production API)
            $paymentLink = null;
            if ($paymentMethod === 'online_link') {
                try {
                    Log::info('Starting payment link generation for order ' . $order->id);
                    $paymentLink = $this->generatePaymentLink($order, $invoice, $amountDue, 25, $paymentGatewaySrc);
                    if ($paymentLink && $invoice) {
                        $this->invoiceRepository->update($invoice->id, [
                            'payment_link' => $paymentLink
                        ]);
                        Log::info('Payment link stored in invoice ' . $invoice->id . ' for order ' . $order->id);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Payment link generation failed for order ' . $order->id . ' (order created; link can be regenerated)', [
                        'message' => $e->getMessage(),
                        'code' => $e->getCode(),
                        'class' => get_class($e),
                    ]);
                }
                $response['payment_link'] = $paymentLink;
            }

            return $response;
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
            $couponsDiscount = isset($data['coupons_discount'])
                ? (float) $data['coupons_discount']
                : (float) ($currentInvoice ? ($currentInvoice->coupons_discount ?? 0.00) : 0.00);
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
            $invoiceAmounts = $this->invoiceService->calculateAmounts(
                $currentTotalAmount,
                $offerDiscount,
                $couponsDiscount,
                $pointsDiscount,
                $deliveryType
            );
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
                    $couponsDiscount,
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

            $newStatus = $order->status;
            if ($oldStatus !== $newStatus) {
                try {
                    if ($order->customer_id) {
                        sendNotification(
                            null,
                            $order->customer_id,
                            'Order Status Updated',
                            "Your order {$order->order_number} status changed from {$oldStatus} to {$newStatus}.",
                            'order',
                            [
                                'order_id' => $order->id,
                                'order_number' => $order->order_number,
                                'old_status' => $oldStatus,
                                'new_status' => $newStatus,
                            ],
                            'تم تحديث حالة الطلب',
                            "تم تغيير حالة طلبك رقم {$order->order_number} من {$oldStatus} إلى {$newStatus}."
                        );
                    }

                    sendNotification(
                        null,
                        null,
                        'Order Updated',
                        "Order {$order->order_number} status changed from {$oldStatus} to {$newStatus}.",
                        'order',
                        [
                            'order_id' => $order->id,
                            'order_number' => $order->order_number,
                            'old_status' => $oldStatus,
                            'new_status' => $newStatus,
                        ],
                        'تم تحديث الطلب',
                        "تم تغيير حالة الطلب رقم {$order->order_number} من {$oldStatus} إلى {$newStatus}."
                    );
                } catch (\Exception $e) {
                    Log::warning('Failed to dispatch order status notifications', [
                        'order_id' => $order->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

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
     * @param int $timeoutSeconds Timeout for payment API (default from config). Use lower value (e.g. 12) when creating order to avoid long waits.
     * @param string|null $paymentGatewaySrc Upayments paymentGateway.src (e.g. knet, cc); null uses config fallback.
     */
    protected function generatePaymentLink(Order $order, Invoice $invoice, float $amount, ?int $timeoutSeconds = null, ?string $paymentGatewaySrc = null): ?string
    {
        try {
            $paymentUrl = $this->upaymentsService->createPayment($order, $amount, $timeoutSeconds, $paymentGatewaySrc);
            Log::info('Payment link generated successfully for order ' . $order->id);
            return $paymentUrl;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Regenerate payment link for an order (online_link only). Uses remaining amount due.
     *
     * @return array{success: bool, message: string, payment_link?: string}
     */
    public function regeneratePaymentLink(int $orderId, ?string $paymentGatewaySrc = null): array
    {
        $order = $this->orderRepository->findById($orderId);
        if (!$order) {
            return ['success' => false, 'message' => 'Order not found.'];
        }

        $order->load('invoice.payments');

        if ($order->payment_method !== 'online_link') {
            return ['success' => false, 'message' => 'Order payment method is not online link.'];
        }

        $invoice = $order->invoice;
        if (!$invoice) {
            return ['success' => false, 'message' => 'Order has no invoice.'];
        }

        if ($invoice->status === 'paid') {
            return ['success' => false, 'message' => 'Invoice is already paid.'];
        }

        $totalPaid = (float) $invoice->payments()->where('status', 'completed')->sum('amount');
        $amountDue = (float) $invoice->amount_due;
        $remainingAmount = max(0, $amountDue - $totalPaid);
        if ($remainingAmount <= 0) {
            return ['success' => false, 'message' => 'No amount due for this order.'];
        }

        $effectiveSrc = ($paymentGatewaySrc !== null && $paymentGatewaySrc !== '')
            ? $paymentGatewaySrc
            : $order->payment_gateway_src;
        if ($effectiveSrc === null || $effectiveSrc === '') {
            return ['success' => false, 'message' => 'No payment gateway source (src) is stored for this order; create the order with src or pass src when regenerating the link.'];
        }

        try {
            $paymentLink = $this->generatePaymentLink($order, $invoice, $remainingAmount, null, $effectiveSrc);
            if ($paymentLink) {
                $this->invoiceRepository->update($invoice->id, ['payment_link' => $paymentLink]);
                if ($paymentGatewaySrc !== null && $paymentGatewaySrc !== '') {
                    $this->orderRepository->update($order->id, ['payment_gateway_src' => $paymentGatewaySrc]);
                }
                Log::info('Payment link regenerated for order ' . $order->id);
                return ['success' => true, 'message' => 'Payment link regenerated successfully.', 'payment_link' => $paymentLink];
            }
        } catch (\Exception $e) {
            Log::error('Failed to regenerate payment link for order ' . $order->id . ': ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to generate payment link: ' . $e->getMessage()];
        }

        return ['success' => false, 'message' => 'Failed to generate payment link.'];
    }

    /**
     * Admin: switch a cash-on-delivery order to online payment (payment link) and generate Upayments URL.
     *
     * @return array{success: bool, message: string, payment_link?: string, order?: Order}
     */
    public function switchCashOrderToOnlinePayment(int $orderId, string $paymentGatewaySrc): array
    {
        $order = Order::with(['invoice.payments', 'items', 'customer'])->find($orderId);
        if (!$order) {
            return ['success' => false, 'message' => 'Order not found.'];
        }
        if ($order->payment_method !== 'cash') {
            return ['success' => false, 'message' => 'Order payment method is not cash on delivery.'];
        }
        if ($order->status === 'cancelled') {
            return ['success' => false, 'message' => 'Cannot change payment method for a cancelled order.'];
        }

        $invoice = $order->invoice;
        if (!$invoice) {
            return ['success' => false, 'message' => 'Order has no invoice.'];
        }
        if ($invoice->status === 'paid') {
            return ['success' => false, 'message' => 'Invoice is already paid.'];
        }

        $totalPaid = (float) $invoice->payments()->where('status', 'completed')->sum('amount');
        $remainingAmount = max(0, (float) $invoice->amount_due - $totalPaid);
        if ($remainingAmount <= 0) {
            return ['success' => false, 'message' => 'No amount due for this order.'];
        }

        DB::beginTransaction();
        try {
            $this->orderRepository->update($orderId, [
                'payment_method' => 'online_link',
                'payment_gateway_src' => $paymentGatewaySrc,
            ]);

            $order = Order::with(['customer', 'items', 'invoice'])->find($orderId);
            if (!$order || !$order->invoice) {
                DB::rollBack();
                return ['success' => false, 'message' => 'Order or invoice not found after update.'];
            }
            $invoice = $order->invoice;

            $paymentLink = $this->generatePaymentLink($order, $invoice, $remainingAmount, 25, $paymentGatewaySrc);
            if (!$paymentLink) {
                DB::rollBack();
                return ['success' => false, 'message' => 'Failed to generate payment link.'];
            }

            $this->invoiceRepository->update($invoice->id, ['payment_link' => $paymentLink]);

            DB::commit();

            $fresh = $this->orderRepository->findById($orderId);
            if ($fresh) {
                $fresh->load(['customer', 'charity', 'offers', 'items.product', 'items.variant', 'invoice.payments', 'customerAddress']);
            }

            return [
                'success' => true,
                'message' => 'Payment method updated to online payment. Payment link generated.',
                'payment_link' => $paymentLink,
                'order' => $fresh,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('switchCashOrderToOnlinePayment failed', [
                'order_id' => $orderId,
                'message' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => 'Failed to generate payment link: ' . $e->getMessage()];
        }
    }

    /**
     * Generate unique order number based on source
     */
    protected function generateOrderNumber(string $source): string
    {
        // Map source to prefix (APP = mobile app, CALS = call center/admin only)
        $prefixes = [
            'app' => 'APP',
            'web' => 'WEB',
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


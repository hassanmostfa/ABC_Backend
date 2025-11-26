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
    protected $deliveryService;
    protected $customerRepository;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        InvoiceRepositoryInterface $invoiceRepository,
        OrderItemService $orderItemService,
        OfferService $offerService,
        InvoiceService $invoiceService,
        WalletService $walletService,
        PointsService $pointsService,
        DeliveryService $deliveryService,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->orderItemService = $orderItemService;
        $this->offerService = $offerService;
        $this->invoiceService = $invoiceService;
        $this->walletService = $walletService;
        $this->pointsService = $pointsService;
        $this->deliveryService = $deliveryService;
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
            // Validate offer if provided
            $offer = $this->offerService->validateOffer(
                $data['offer_id'] ?? null,
                $data['customer_id'] ?? null
            );

            // Process order items
            $items = $data['items'] ?? [];
            $totalAmount = 0;
            $orderItemsData = [];

            if (!empty($items)) {
                $result = $this->orderItemService->processItems($items);
                $orderItemsData = $result['orderItemsData'];
                $totalAmount = $result['totalAmount'];
            }

            // Handle offer rewards
            $offerDiscount = 0.00;
            if ($offer) {
                $offerResult = $this->offerService->processOfferRewards($offer, $orderItemsData, $totalAmount);
                $orderItemsData = $offerResult['orderItemsData'];
                $totalAmount = $offerResult['totalAmount'];
                $offerDiscount = $offerResult['offerDiscount'];
            }

            // Handle points discount (calculate before creating order)
            $requestedPoints = $data['used_points'] ?? 0;
            $pointsResult = $this->pointsService->calculateDiscount($requestedPoints, $totalAmount, $offerDiscount);
            $usedPoints = $pointsResult['usedPoints'];
            $pointsDiscount = $pointsResult['pointsDiscount'];

            // Calculate invoice amounts (before creating order)
            $invoiceAmounts = $this->invoiceService->calculateAmounts($totalAmount, $offerDiscount, $pointsDiscount);
            $amountDue = $invoiceAmounts['amountDue'];

            // Get payment method from root level or delivery level
            $paymentMethod = $data['payment_method'] ?? $data['delivery']['payment_method'] ?? null;
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
            
            // Get payment method from root level or delivery level
            $paymentMethod = $data['payment_method'] ?? $data['delivery']['payment_method'] ?? null;
            // Normalize payment method to 'cash' or 'wallet' only for orders table
            $paymentMethodForOrder = ($paymentMethod && in_array($paymentMethod, ['cash', 'wallet'])) 
                ? $paymentMethod 
                : null;
            
            // Create the order
            $orderData = Arr::except($data, ['items', 'source']);
            $orderData['total_amount'] = $totalAmount;
            $orderData['order_number'] = $orderNumber;
            if ($paymentMethodForOrder) {
                $orderData['payment_method'] = $paymentMethodForOrder;
            }
            $order = $this->orderRepository->create($orderData);

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

            // Create delivery record
            $this->deliveryService->createDeliveryRecord(
                $order->id,
                $order->delivery_type ?? 'pickup',
                $data,
                $paymentMethod
            );

            DB::commit();

            // Reload order with relationships
            $order->load(['customer', 'charity', 'offer', 'items.product', 'items.variant', 'invoice', 'delivery']);

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
            
            // Get current delivery to check old payment method
            $currentDelivery = $this->deliveryService->getDeliveryByOrder($id);
            $oldDeliveryPaymentMethod = $currentDelivery ? $currentDelivery->payment_method : null;

            // Validate offer if provided
            $customerId = $data['customer_id'] ?? $currentOrder->customer_id;
            $offer = $this->offerService->validateOffer(
                $data['offer_id'] ?? null,
                $customerId
            );

            // Update the order (excluding items and total_amount)
            $orderData = Arr::except($data, ['items', 'total_amount', 'used_points']);
            // Add payment_method to order data if it's cash or wallet
            if (isset($paymentMethodForOrder)) {
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
                // Clear items if empty and no offer
                if (empty($data['items'])) {
                    $updatedOfferId = $data['offer_id'] ?? $order->offer_id;
                    if (!$updatedOfferId) {
                        DB::rollBack();
                        throw new \Exception('Items are required when no offer is provided.');
                    }
                    $this->orderItemService->clearOrderItems($id);
                    $recalculatedTotalAmount = 0;
                } else {
                    $result = $this->orderItemService->processItemsForUpdate($id, $data['items']);
                    $recalculatedTotalAmount = $result['totalAmount'];
                }
                
                // Calculate offer discount if offer is present
                $updatedOfferId = $data['offer_id'] ?? $order->offer_id;
                $currentOffer = $updatedOfferId ? Offer::find($updatedOfferId) : null;
                if ($currentOffer) {
                    // Create offer reward items if reward type is products
                    if ($currentOffer->reward_type === 'products') {
                        $additionalTotal = $this->offerService->createOfferRewardItems($id, $currentOffer);
                        $recalculatedTotalAmount = ($recalculatedTotalAmount ?? $order->total_amount ?? 0) + $additionalTotal;
                        $this->orderRepository->update($id, ['total_amount' => $recalculatedTotalAmount]);
                    }
                    
                    $calculatedOfferDiscount = $this->offerService->calculateOfferDiscount(
                        $currentOffer,
                        $recalculatedTotalAmount ?? $order->total_amount ?? 0
                    );
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

            // Calculate invoice amounts
            $invoiceAmounts = $this->invoiceService->calculateAmounts($currentTotalAmount, $offerDiscount, $pointsDiscount);
            $newAmountDue = $invoiceAmounts['amountDue'];

            // Get payment method from root level or delivery level, or use existing delivery payment method
            // Use the payment method we already determined for the order update
            $paymentMethod = $paymentMethodForUpdate ?? $oldDeliveryPaymentMethod ?? null;
            $isWalletPayment = ($paymentMethod === 'wallet');
            
            // Get old payment method from order or delivery
            $oldOrderPaymentMethod = $currentOrder->payment_method ?? null;
            $oldPaymentMethod = $oldOrderPaymentMethod ?? $oldDeliveryPaymentMethod ?? null;
            
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
                    $offerDiscount,
                    $usedPoints,
                    $pointsDiscount,
                    $invoiceAmounts['totalDiscount'],
                    $isWalletPayment // Pass isPaid flag
                );
            }

            // Update delivery record if it exists
            if (isset($data['delivery']) || $paymentMethod) {
                $this->deliveryService->updateDeliveryRecord($id, $data, $paymentMethod);
            }

            DB::commit();

            // Reload order with relationships
            $order = $this->orderRepository->findById($id);
            $order->load(['customer', 'charity', 'offer', 'items.product', 'items.variant', 'invoice', 'delivery']);

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

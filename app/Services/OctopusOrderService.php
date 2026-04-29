<?php

namespace App\Services;

use App\Jobs\DispatchErpOrderJob;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Offer;
use App\Models\OrderItem;
use App\Repositories\OrderRepositoryInterface;
use App\Repositories\InvoiceRepositoryInterface;
use App\Repositories\OrderItemRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OctopusOrderService
{
    protected OrderRepositoryInterface $orderRepository;
    protected InvoiceRepositoryInterface $invoiceRepository;
    protected OrderItemRepositoryInterface $orderItemRepository;
    protected InvoiceService $invoiceService;
    protected OfferService $offerService;
    protected UpaymentsService $upaymentsService;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        InvoiceRepositoryInterface $invoiceRepository,
        OrderItemRepositoryInterface $orderItemRepository,
        InvoiceService $invoiceService,
        OfferService $offerService,
        UpaymentsService $upaymentsService
    ) {
        $this->orderRepository = $orderRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->orderItemRepository = $orderItemRepository;
        $this->invoiceService = $invoiceService;
        $this->offerService = $offerService;
        $this->upaymentsService = $upaymentsService;
    }

    /**
     * Create order from Octopus API request.
     * - Finds or creates customer by phone
     * - Processes items by short_item
     * - Creates order with OCT- prefix
     * - Syncs to ERP
     */
    public function createOrder(array $data): array
    {
        DB::beginTransaction();

        try {
            // Find or create customer
            $customer = $this->findOrCreateCustomer($data);

            // Process offers
            $offersData = [];
            if (isset($data['offers']) && is_array($data['offers'])) {
                foreach ($data['offers'] as $offerData) {
                    if (isset($offerData['offer_id'])) {
                        $offersData[] = [
                            'offer_id' => $offerData['offer_id'],
                            'quantity' => isset($offerData['quantity']) ? (int) $offerData['quantity'] : 1
                        ];
                    }
                }
            }

            $offersToProcess = [];
            $offersToAttach = [];
            
            foreach ($offersData as $offerData) {
                $offer = $this->offerService->validateOffer($offerData['offer_id'], $customer->id);
                if ($offer) {
                    $quantity = $offerData['quantity'];
                    for ($i = 0; $i < $quantity; $i++) {
                        $offersToProcess[] = $offer;
                    }
                    $offersToAttach[$offer->id] = ['quantity' => $quantity];
                }
            }

            // Process items by short_item
            $items = $data['items'] ?? [];
            $totalAmount = 0;
            $orderItemsData = [];

            if (!empty($items)) {
                $result = $this->processItemsByShortItem($items);
                $orderItemsData = $result['orderItemsData'];
                $totalAmount = $result['totalAmount'];
            }

            $originalTotalAmount = $totalAmount;

            // Handle offer rewards
            $offerDiscount = 0.00;
            foreach ($offersToProcess as $offer) {
                $offerResult = $this->offerService->processOfferRewards($offer, $orderItemsData, $totalAmount);
                $orderItemsData = $offerResult['orderItemsData'];
                $totalAmount = $offerResult['totalAmount'];
                $offerDiscount += $offerResult['offerDiscount'];
            }

            // Apply line tax
            $taxRate = (float) \App\Models\Setting::getValue('tax', 0.15);
            foreach ($orderItemsData as &$row) {
                $total = (float) ($row['total_price'] ?? 0);
                $disc = (float) ($row['discount'] ?? 0);
                $row['tax'] = OrderItem::computeLineTax($total, $disc, $taxRate);
            }
            unset($row);

            // Calculate final amount
            $finalAmountAfterDiscounts = $totalAmount - $offerDiscount;

            // Validate minimum order
            $minimumHomeOrder = (float) \App\Models\Setting::getValue('minimum_home_order', 5);
            if ($finalAmountAfterDiscounts < $minimumHomeOrder) {
                DB::rollBack();
                throw new \Exception("Minimum home order amount is {$minimumHomeOrder}. Current order amount after discounts is {$finalAmountAfterDiscounts}.");
            }

            // Calculate invoice amounts (delivery type is always delivery for octopus)
            $deliveryType = 'delivery';
            $invoiceAmounts = $this->invoiceService->calculateAmounts(
                $totalAmount,
                $offerDiscount,
                0, // coupons discount
                0, // points discount
                $deliveryType
            );
            $amountDue = $invoiceAmounts['amountDue'];

            // Determine payment method
            $paymentMethod = $data['payment_method'] ?? 'cash';
            $paymentGatewaySrc = $data['src'] ?? null;
            $isPaid = false;

            // If payment_info is provided and payment is online, mark as paid
            if ($paymentMethod === 'online_link' && !empty($data['payment_info'])) {
                $isPaid = true;
            }

            // Generate OCT order number
            $orderNumber = $this->generateOctopusOrderNumber();

            // Create order data
            $orderData = [
                'customer_id' => $customer->id,
                'charity_id' => null,
                'customer_address_id' => null,
                'address' => $data['address'] ?? null,
                'order_number' => $orderNumber,
                'status' => 'pending',
                'total_amount' => $totalAmount,
                'delivery_type' => $deliveryType,
                'delivery_date' => now()->toDateString(),
                'delivery_time' => now()->format('H:i:s'),
                'payment_method' => $paymentMethod,
                'payment_gateway_src' => $paymentGatewaySrc,
            ];
            
            // Capture created_by information from authenticated user
            $user = auth()->user();
            if ($user) {
                $orderData['created_by_id'] = $user->id;
                $orderData['created_by_type'] = get_class($user);
            }

            $order = $this->orderRepository->create($orderData);

            // Attach offers
            if (!empty($offersToAttach)) {
                $order->offers()->attach($offersToAttach);
            }

            // Create order items
            $this->createOrderItems($order->id, $orderItemsData);

            // Create invoice
            $invoice = $this->invoiceService->createOrGetInvoice(
                $order->id,
                $order->order_number,
                $invoiceAmounts['amountDue'],
                $invoiceAmounts['taxAmount'],
                $invoiceAmounts['deliveryFee'],
                $offerDiscount,
                0, // coupons discount
                0, // used points
                0, // points discount
                $invoiceAmounts['totalDiscount'],
                $isPaid
            );

            // Create payment record if payment_info is provided
            if ($isPaid && !empty($data['payment_info']) && $invoice) {
                $this->createPaymentRecord($invoice, $order, $customer, $data['payment_info'], $amountDue);
            }

            DB::commit();

            // Reload order with relationships
            $order->load(['customer', 'offers', 'items.product', 'items.variant', 'invoice']);

            // Dispatch ERP after response so Octopus request is not blocked by ERP latency.
            if (($paymentMethod === 'online_link' && $isPaid) || $paymentMethod === 'cash') {
                DispatchErpOrderJob::dispatchAfterResponse($order->id);
            }
            // online_link + unpaid: ERP sends after Upayments callback marks invoice paid (PaymentController)

            $response = [
                'success' => true,
                'order' => $order,
                'customer_created' => $customer->wasRecentlyCreated ?? false,
            ];

            // Generate payment link if online and not paid
            if ($paymentMethod === 'online_link' && !$isPaid) {
                try {
                    $paymentLink = $this->upaymentsService->createPayment($order, $amountDue, 25, $paymentGatewaySrc);
                    if ($paymentLink && $invoice) {
                        $this->invoiceRepository->update($invoice->id, ['payment_link' => $paymentLink]);
                    }
                    $response['payment_link'] = $paymentLink;
                } catch (\Throwable $e) {
                    Log::warning('Payment link generation failed for octopus order ' . $order->id, [
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            return $response;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Find customer by phone or create new one.
     */
    protected function findOrCreateCustomer(array $data): Customer
    {
        $phone = $data['phone'];
        $name = $data['name'] ?? 'Octopus Customer';

        $customer = Customer::where('phone', $phone)->first();

        if (!$customer) {
            $customer = Customer::create([
                'name' => $name,
                'phone' => $phone,
                'is_active' => true,
                'is_completed' => true,
                'points' => 0,
            ]);
        }

        return $customer;
    }

    /**
     * Process items using short_item instead of variant_id.
     */
    protected function processItemsByShortItem(array $items): array
    {
        $shortItems = collect($items)->pluck('short_item')->unique()->values()->all();
        $variants = ProductVariant::with('product')
            ->whereIn('short_item', $shortItems)
            ->where('is_active', true)
            ->get()
            ->keyBy('short_item');

        $totalAmount = 0;
        $orderItemsData = [];

        foreach ($items as $item) {
            $shortItem = $item['short_item'];
            $variant = $variants->get($shortItem);

            if (!$variant) {
                throw new \Exception("Variant not found for short_item: {$shortItem}");
            }

            $quantity = $item['quantity'];

            $availableQuantity = $variant->quantity ?? 0;
            if ($availableQuantity < $quantity) {
                $productName = $variant->product->name_en ?? $variant->product->name_ar ?? 'Product';
                $sizeInfo = $variant->size ? ' - ' . $variant->size : '';
                throw new \Exception(
                    "Insufficient quantity for {$productName}{$sizeInfo}. Available: {$availableQuantity}, Requested: {$quantity}"
                );
            }

            $unitPrice = $variant->price;
            $totalPrice = $unitPrice * $quantity;
            $totalAmount += $totalPrice;

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
                'offer_line_kind' => null,
                'discount' => 0,
                'tax' => 0,
            ];
        }

        return [
            'orderItemsData' => $orderItemsData,
            'totalAmount' => $totalAmount
        ];
    }

    /**
     * Create order items and update variant quantities.
     */
    protected function createOrderItems(int $orderId, array $orderItemsData): void
    {
        if (empty($orderItemsData)) {
            return;
        }

        $variantIds = collect($orderItemsData)->pluck('variant_id')->unique()->values()->all();
        $variants = ProductVariant::whereIn('id', $variantIds)->get()->keyBy('id');
        $quantityByVariant = collect($orderItemsData)->groupBy('variant_id')->map(fn ($rows) => (int) $rows->sum('quantity'));

        foreach ($orderItemsData as $itemData) {
            $itemData['order_id'] = $orderId;
            $this->orderItemRepository->create($itemData);
        }

        foreach ($quantityByVariant as $variantId => $qty) {
            $variant = $variants->get($variantId);
            if ($variant) {
                $newQuantity = max(0, ($variant->quantity ?? 0) - $qty);
                $variant->update(['quantity' => $newQuantity]);
            }
        }
    }

    /**
     * Generate unique OCT order number.
     * Format: OCT-0001, OCT-0002, etc.
     */
    protected function generateOctopusOrderNumber(): string
    {
        $prefix = 'OCT';
        $pattern = $prefix . '-%';

        $lastOrder = Order::where('order_number', 'LIKE', $pattern)
            ->orderByRaw("CAST(SUBSTRING_INDEX(order_number, '-', -1) AS UNSIGNED) DESC")
            ->first();

        $sequence = 1;
        if ($lastOrder) {
            $parts = explode('-', $lastOrder->order_number);
            if (count($parts) === 2 && isset($parts[1])) {
                $sequence = (int) $parts[1] + 1;
            }
        }

        return sprintf('%s-%04d', $prefix, $sequence);
    }

    /**
     * Create payment record from Octopus payment info.
     */
    protected function createPaymentRecord($invoice, Order $order, Customer $customer, array $paymentInfo, float $amount): Payment
    {
        $paidAt = isset($paymentInfo['paid_at']) 
            ? \Carbon\Carbon::parse($paymentInfo['paid_at'])->setTimezone('Asia/Kuwait')
            : now('Asia/Kuwait');

        return Payment::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'reference' => $order->order_number,
            'type' => Payment::TYPE_ORDER,
            'payment_number' => $this->generatePaymentNumber(),
            'gateway' => 'octopus',
            'payment_gateway_src' => $order->payment_gateway_src,
            'track_id' => $paymentInfo['track_id'] ?? null,
            'tran_id' => $paymentInfo['tran_id'] ?? $paymentInfo['transaction_id'] ?? null,
            'payment_id' => $paymentInfo['payment_id'] ?? null,
            'receipt_id' => $paymentInfo['receipt_id'] ?? null,
            'amount' => $amount,
            'method' => 'online',
            'status' => 'completed',
            'paid_at' => $paidAt,
        ]);
    }

    /**
     * Generate unique payment number.
     * Format: PAY-YYYY-000001
     */
    protected function generatePaymentNumber(): string
    {
        $year = date('Y');
        $pattern = 'PAY-' . $year . '-%';

        $lastPayment = Payment::where('payment_number', 'LIKE', $pattern)
            ->orderBy('payment_number', 'desc')
            ->first();

        $sequence = 1;
        if ($lastPayment) {
            $parts = explode('-', $lastPayment->payment_number);
            if (count($parts) === 3 && isset($parts[2])) {
                $sequence = (int) $parts[2] + 1;
            }
        }

        return sprintf('PAY-%s-%06d', $year, $sequence);
    }
}

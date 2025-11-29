<?php

namespace App\Services;

use App\Repositories\InvoiceRepositoryInterface;
use App\Models\Setting;

class InvoiceService
{
    protected $invoiceRepository;

    public function __construct(InvoiceRepositoryInterface $invoiceRepository)
    {
        $this->invoiceRepository = $invoiceRepository;
    }

    /**
     * Calculate invoice amounts
     *
     * @param float $totalAmount
     * @param float $offerDiscount
     * @param float $pointsDiscount
     * @param string|null $deliveryType Optional: 'delivery' or 'pickup' to calculate delivery fee
     * @return array
     */
    public function calculateAmounts(float $totalAmount, float $offerDiscount, float $pointsDiscount, ?string $deliveryType = null): array
    {
        // Calculate final amount after all discounts
        $finalAmount = $totalAmount - $offerDiscount - $pointsDiscount;
        $totalDiscount = $offerDiscount + $pointsDiscount;

        // Calculate delivery fee if delivery type is 'delivery'
        $deliveryFee = 0.00;
        if ($deliveryType === 'delivery') {
            $deliveryFee = (float) Setting::getValue('delivery_price', 0);
        }

        // Calculate tax amount using tax setting (on final amount before delivery fee)
        $taxRate = (float) Setting::getValue('tax', 0.15);
        $taxAmount = $finalAmount * $taxRate;
        
        // Calculate total amount due (final amount + tax + delivery fee)
        $amountDue = $finalAmount + $taxAmount + $deliveryFee;

        return [
            'finalAmount' => $finalAmount,
            'taxAmount' => $taxAmount,
            'deliveryFee' => $deliveryFee,
            'amountDue' => $amountDue,
            'totalDiscount' => $totalDiscount
        ];
    }

    /**
     * Create or get existing invoice for order
     *
     * @param int $orderId
     * @param string $orderNumber
     * @param float $amountDue
     * @param float $taxAmount
     * @param float $deliveryFee
     * @param float $offerDiscount
     * @param int $usedPoints
     * @param float $pointsDiscount
     * @param float $totalDiscount
     * @param bool $isPaid Whether the invoice should be created as paid (for wallet payments)
     * @return mixed
     */
    public function createOrGetInvoice(
        int $orderId,
        string $orderNumber,
        float $amountDue,
        float $taxAmount,
        float $deliveryFee,
        float $offerDiscount,
        int $usedPoints,
        float $pointsDiscount,
        float $totalDiscount,
        bool $isPaid = false
    ) {
        $existingInvoice = $this->invoiceRepository->getByOrder($orderId);
        
        if (!$existingInvoice) {
            $invoiceNumber = 'INV-' . $orderNumber;
            $invoiceData = [
                'order_id' => $orderId,
                'invoice_number' => $invoiceNumber,
                'amount_due' => $amountDue,
                'tax_amount' => $taxAmount,
                'delivery_fee' => $deliveryFee,
                'offer_discount' => $offerDiscount,
                'used_points' => $usedPoints,
                'points_discount' => $pointsDiscount,
                'total_discount' => $totalDiscount,
                'status' => $isPaid ? 'paid' : 'pending',
            ];

            if ($isPaid) {
                $invoiceData['paid_at'] = now();
            }

            return $this->invoiceRepository->create($invoiceData);
        }

        return $existingInvoice;
    }

    /**
     * Update invoice
     *
     * @param int $invoiceId
     * @param float $amountDue
     * @param float $taxAmount
     * @param float $deliveryFee
     * @param float $offerDiscount
     * @param int $usedPoints
     * @param float $pointsDiscount
     * @param float $totalDiscount
     * @param bool $isPaid Whether the invoice should be marked as paid (for wallet payments)
     */
    public function updateInvoice(
        int $invoiceId,
        float $amountDue,
        float $taxAmount,
        float $deliveryFee,
        float $offerDiscount,
        int $usedPoints,
        float $pointsDiscount,
        float $totalDiscount,
        bool $isPaid = false
    ): void {
        $updateData = [
            'amount_due' => $amountDue,
            'tax_amount' => $taxAmount,
            'delivery_fee' => $deliveryFee,
            'offer_discount' => $offerDiscount,
            'used_points' => $usedPoints,
            'points_discount' => $pointsDiscount,
            'total_discount' => $totalDiscount,
        ];

        if ($isPaid) {
            $updateData['status'] = 'paid';
            $updateData['paid_at'] = now();
        } else {
            // Only set to pending if not already paid (preserve existing paid status if not changing)
            // We'll handle unpaid status explicitly when needed
        }

        $this->invoiceRepository->update($invoiceId, $updateData);
    }

    /**
     * Mark invoice as paid
     *
     * @param int $invoiceId
     * @return void
     */
    public function markAsPaid(int $invoiceId): void
    {
        $this->invoiceRepository->update($invoiceId, [
            'paid_at' => now(),
            'status' => 'paid',
        ]);
    }

    /**
     * Mark invoice as unpaid
     *
     * @param int $invoiceId
     * @return void
     */
    public function markAsUnpaid(int $invoiceId): void
    {
        $this->invoiceRepository->update($invoiceId, [
            'paid_at' => null,
            'status' => 'pending',
        ]);
    }
}


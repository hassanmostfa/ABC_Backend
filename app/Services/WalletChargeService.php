<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Setting;
use App\Repositories\CustomerRepositoryInterface;
use App\Repositories\PaymentRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletChargeService
{
    public function __construct(
        protected CustomerRepositoryInterface $customerRepository,
        protected WalletService $walletService,
        protected UpaymentsService $upaymentsService,
        protected PaymentRepositoryInterface $paymentRepository
    ) {}

    /**
     * Create wallet charge and generate payment link (stored as Payment with type=wallet_charge)
     */
    public function createCharge(int $customerId, float $amount): array
    {
        if ($amount <= 0 || !$customerId) {
            return [
                'success' => false,
                'message' => 'Amount must be greater than zero.',
            ];
        }

        $customer = $this->customerRepository->findById($customerId);
        if (!$customer) {
            return [
                'success' => false,
                'message' => 'Customer not found.',
            ];
        }

        $giftAmount = (float) Setting::getValue('wallet_charge_gift', 0);
        $bonusAmount = round($giftAmount, 2);
        $totalAmount = $amount + $bonusAmount;

        try {
            DB::beginTransaction();

            $reference = $this->generateReference();
            $paymentNumber = $this->generatePaymentNumber();

            $payment = $this->paymentRepository->create([
                'invoice_id' => null,
                'customer_id' => $customerId,
                'reference' => $reference,
                'type' => Payment::TYPE_WALLET_CHARGE,
                'payment_number' => $paymentNumber,
                'amount' => $amount,
                'bonus_amount' => $bonusAmount,
                'total_amount' => $totalAmount,
                'method' => 'online',
                'status' => 'pending',
            ]);

            $paymentLink = $this->upaymentsService->createWalletChargePayment($payment, $amount);

            $payment->update(['payment_link' => $paymentLink]);

            DB::commit();

            return [
                'success' => true,
                'payment' => $payment->fresh(),
                'payment_link' => $paymentLink,
                'amount' => $amount,
                'bonus_amount' => $bonusAmount,
                'total_amount' => $totalAmount,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Wallet charge creation failed', [
                'customer_id' => $customerId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => 'Failed to create payment link: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Process successful wallet charge - add balance to wallet
     */
    public function processSuccess(Payment $payment): bool
    {
        if ($payment->status === 'completed') {
            return true;
        }

        if ($payment->type !== Payment::TYPE_WALLET_CHARGE) {
            return false;
        }

        try {
            DB::beginTransaction();

            $this->walletService->addBalance($payment->customer_id, $payment->total_amount);

            $this->paymentRepository->update($payment->id, [
                'status' => 'completed',
                'paid_at' => now(),
            ]);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Wallet charge success processing failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Mark wallet charge payment as cancelled
     */
    public function processCancel(Payment $payment): void
    {
        if ($payment->status === 'pending' && $payment->type === Payment::TYPE_WALLET_CHARGE) {
            $this->paymentRepository->update($payment->id, ['status' => 'failed']);
        }
    }

    /**
     * Generate unique reference (e.g. WCH-2026-000001)
     */
    protected function generateReference(): string
    {
        $year = date('Y');
        $pattern = "WCH-{$year}-%";
        $lastPayment = Payment::walletCharge()
            ->where('reference', 'LIKE', "WCH-{$year}-%")
            ->orderBy('id', 'desc')
            ->first();

        $seq = $lastPayment ? (int) substr($lastPayment->reference, -6) + 1 : 1;
        return sprintf("WCH-%s-%06d", $year, min($seq, 999999));
    }

    /**
     * Generate unique payment number
     */
    protected function generatePaymentNumber(): string
    {
        $year = date('Y');
        $lastPayment = Payment::where('payment_number', 'LIKE', 'PAY-' . $year . '-%')
            ->orderBy('payment_number', 'desc')
            ->first();

        $seq = 1;
        if ($lastPayment) {
            $parts = explode('-', $lastPayment->payment_number);
            if (count($parts) === 3 && isset($parts[2])) {
                $seq = (int) $parts[2] + 1;
            }
        }
        return sprintf('PAY-%s-%06d', $year, $seq);
    }

    /**
     * Find wallet charge payment by reference
     */
    public function findByReference(string $reference): ?Payment
    {
        return Payment::walletCharge()->where('reference', $reference)->first();
    }
}

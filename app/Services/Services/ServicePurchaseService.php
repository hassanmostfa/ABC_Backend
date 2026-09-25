<?php

namespace App\Services\Services;

use App\Mail\ServiceVoucherProviderMail;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\ServiceCheckout;
use App\Models\ServiceVoucher;
use App\Repositories\Services\ServiceRepositoryInterface;
use App\Services\Payment\OttuService;
use App\Services\Wallet\WalletService;
use App\Support\PaymentCreatorResolver;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail; 
use Illuminate\Support\Str;

class ServicePurchaseService
{
    public function __construct(
        protected WalletService $walletService,
        protected OttuService $ottuService,
        protected ServiceRepositoryInterface $serviceRepository
    ) {
    }

    /**
     * Buy a service with wallet (voucher immediately) or start an online payment.
     *
     * @return array{
     *     success: bool,
     *     checkout?: ServiceCheckout,
     *     voucher?: ServiceVoucher,
     *     payment_link: string|null,
     *     is_checkout: bool
     * }
     */
    public function purchase(Customer $customer, array $data): array
    {
        $paymentGatewaySrc = $data['src'] ?? null;
        $isWalletPayment = ($data['payment_method'] ?? null) === 'wallet'
            || $paymentGatewaySrc === 'wallet';

        if ($isWalletPayment) {
            $paymentGatewaySrc = 'wallet';
        } elseif (!$paymentGatewaySrc) {
            throw new \Exception('Payment source (src) is required for online payment.', 400);
        }

        $service = $this->serviceRepository->findById((int) $data['service_id']);
        if (!$service) {
            throw new \Exception('Service not found.', 404);
        }

        if (!$service->is_active) {
            throw new \Exception('Service is not available.', 400);
        }

        $amountDue = round((float) $service->price, 3);

        $checkoutAttributes = [
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'checkout_number' => $this->generateCheckoutNumber(),
            'service_name' => $service->name,
            'service_provider_email' => $service->service_provider_email,
            'payment_gateway_src' => $paymentGatewaySrc,
            'amount_due' => $amountDue,
            'status' => ServiceCheckout::STATUS_PENDING,
            'expires_at' => $isWalletPayment
                ? null
                : now()->addMinutes((int) config('services.ottu.checkout_ttl_minutes', 60)),
        ];

        if ($isWalletPayment) {
            return $this->purchaseWithWallet($customer, $checkoutAttributes, $amountDue);
        }

        return $this->purchaseOnline($checkoutAttributes, $amountDue, $paymentGatewaySrc);
    }

    /**
     * @param  array<string, mixed>  $checkoutAttributes
     * @return array{success: bool, voucher: ServiceVoucher, payment_link: null, is_checkout: false}
     */
    protected function purchaseWithWallet(Customer $customer, array $checkoutAttributes, float $amountDue): array {
        try {
            $this->walletService->validateBalance($customer->id, $amountDue);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), 400);
        }

        DB::beginTransaction();

        try {
            $checkout = ServiceCheckout::create($checkoutAttributes);

            $this->walletService->deductBalance($customer->id, $amountDue);

            $payment = Payment::create(array_merge([
                'invoice_id' => null,
                'service_checkout_id' => $checkout->id,
                'customer_id' => $customer->id,
                'reference' => $checkout->checkout_number,
                'type' => Payment::TYPE_SERVICE,
                'payment_number' => $this->generatePaymentNumber(),
                'gateway' => 'wallet',
                'track_id' => $checkout->checkout_number,
                'payment_gateway_src' => 'wallet',
                'amount' => $amountDue,
                'bonus_amount' => 0,
                'total_amount' => $amountDue,
                'method' => 'wallet',
                'status' => Payment::STATUS_COMPLETED,
                'paid_at' => now('Asia/Kuwait'),
            ], PaymentCreatorResolver::forCustomer($customer->id)));

            $result = $this->fulfillPaidCheckout($checkout, $payment, []);
            if (empty($result['processed']) || empty($result['voucher'])) {
                throw new \Exception('Failed to complete wallet service purchase.');
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();
            if (
                str_contains($message, 'Insufficient wallet balance')
                || str_contains($message, 'Customer wallet not found')
                || str_contains($message, 'Customer ID is required for wallet payment')
            ) {
                throw new \Exception($message, 400);
            }
            throw $e;
        }

        if (!empty($result['voucher']) && empty($result['idempotent'])) {
            $this->notifyServiceProvider($result['voucher'], $customer);
        }

        return [
            'success' => true,
            'voucher' => $result['voucher'],
            'payment_link' => null,
            'is_checkout' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $checkoutAttributes
     * @return array{success: bool, checkout: ServiceCheckout, payment_link: string, is_checkout: true}
     */
    protected function purchaseOnline(array $checkoutAttributes, float $amountDue, string $paymentGatewaySrc): array
    {
        $checkout = ServiceCheckout::create($checkoutAttributes);

        try {
            $checkout->load('customer');
            $paymentLink = $this->ottuService->createServicePayment(
                $checkout,
                $amountDue,
                $paymentGatewaySrc
            );
            $sessionId = $this->ottuService->getLastCheckoutSessionId();

            $checkout->update([
                'payment_link' => $paymentLink,
                'ottu_session_id' => $sessionId,
            ]);

            if ($sessionId) {
                $this->ottuService->ensurePendingServicePayment(
                    $checkout,
                    $sessionId,
                    $amountDue,
                    $paymentGatewaySrc,
                    $paymentLink
                );
            }
        } catch (\Throwable $e) {
            $checkout->update(['status' => ServiceCheckout::STATUS_FAILED]);
            Log::warning('Service checkout payment link generation failed', [
                'checkout_id' => $checkout->id,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }

        return [
            'success' => true,
            'checkout' => $checkout->fresh(['customer', 'service']),
            'payment_link' => $paymentLink,
            'is_checkout' => true,
        ];
    }

    /**
     * Create the voucher after Ottu confirms payment.
     *
     * @return array{processed: bool, idempotent?: bool, voucher?: ServiceVoucher, checkout?: ServiceCheckout, payment_status?: string, reason?: string}
     */
    public function fulfillCheckout(ServiceCheckout $checkout, Payment $payment, array $statusResult): array
    {
        DB::beginTransaction();

        try {
            $result = $this->fulfillPaidCheckout($checkout, $payment, $statusResult);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Service checkout fulfillment failed', [
                'checkout_id' => $checkout->id,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }

        if (
            !empty($result['processed'])
            && empty($result['idempotent'])
            && !empty($result['voucher'])
        ) {
            $customer = $result['voucher']->customer ?? Customer::query()->find($result['voucher']->customer_id);
            if ($customer) {
                $this->notifyServiceProvider($result['voucher'], $customer);
            }
        }

        return $result;
    }

    /**
     * @return array{processed: bool, idempotent?: bool, voucher?: ServiceVoucher, checkout?: ServiceCheckout, payment_status?: string, reason?: string}
     */
    protected function fulfillPaidCheckout(ServiceCheckout $checkout, Payment $payment, array $statusResult): array
    {
        $locked = ServiceCheckout::query()->whereKey($checkout->id)->lockForUpdate()->first();
        if (!$locked) {
            return ['processed' => false, 'reason' => 'checkout_not_found'];
        }

        if ($locked->service_voucher_id) {
            $voucher = ServiceVoucher::query()
                ->with(['service', 'customer'])
                ->find($locked->service_voucher_id);

            return [
                'processed' => true,
                'idempotent' => true,
                'voucher' => $voucher,
                'checkout' => $locked,
                'payment_status' => Payment::STATUS_COMPLETED,
            ];
        }

        if (!$locked->isPending()) {
            if ($locked->status === ServiceCheckout::STATUS_FAILED) {
                $locked->update(['status' => ServiceCheckout::STATUS_PENDING]);
                $locked->refresh();
            } else {
                return ['processed' => false, 'reason' => 'checkout_not_pending', 'checkout' => $locked];
            }
        }

        $paymentMethod = $payment->method === 'wallet'
            ? ServiceVoucher::METHOD_WALLET
            : ServiceVoucher::METHOD_ONLINE;

        $voucher = ServiceVoucher::create([
            'customer_id' => $locked->customer_id,
            'service_id' => $locked->service_id,
            'service_checkout_id' => $locked->id,
            'payment_id' => $payment->id,
            'service_name' => $locked->service_name,
            'code' => $this->generateVoucherCode(),
            'amount' => $locked->amount_due,
            'payment_method' => $paymentMethod,
            'status' => ServiceVoucher::STATUS_ACTIVE,
        ]);

        $referenceNumber = $statusResult['reference_number'] ?? null;
        $storedTrackId = (is_string($referenceNumber) && trim($referenceNumber) !== '')
            ? trim($referenceNumber)
            : (string) ($payment->track_id ?? '');

        $payment->update([
            'service_checkout_id' => $locked->id,
            'type' => Payment::TYPE_SERVICE,
            'reference' => $locked->checkout_number,
            'status' => Payment::STATUS_COMPLETED,
            'paid_at' => $payment->paid_at ?? now('Asia/Kuwait'),
            'track_id' => $storedTrackId !== '' ? $storedTrackId : $payment->track_id,
            'tran_id' => $statusResult['tran_id'] ?? $payment->tran_id,
            'payment_id' => $statusResult['payment_id'] ?? $payment->payment_id,
            'receipt_id' => $statusResult['receipt_id'] ?? $payment->receipt_id,
        ]);

        $locked->update([
            'status' => ServiceCheckout::STATUS_PAID,
            'service_voucher_id' => $voucher->id,
        ]);

        $voucher->load(['service', 'customer']);

        return [
            'processed' => true,
            'voucher' => $voucher,
            'checkout' => $locked->fresh(),
            'payment_status' => Payment::STATUS_COMPLETED,
        ];
    }

    public function vouchersForCustomer(int $customerId, int $perPage = 15): LengthAwarePaginator
    {
        return ServiceVoucher::query()
            ->with('service')
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    protected function notifyServiceProvider(ServiceVoucher $voucher, Customer $customer): void
    {
        $checkout = $voucher->checkout ?? ServiceCheckout::query()->find($voucher->service_checkout_id);
        $email = $checkout?->service_provider_email;

        if (!is_string($email) || trim($email) === '') {
            Log::warning('Service voucher created without a provider email', [
                'voucher_id' => $voucher->id,
            ]);

            return;
        }

        try {
            Mail::to($email)->send(new ServiceVoucherProviderMail($voucher, $customer));
        } catch (\Throwable $e) {
            Log::warning('Failed to email service provider about voucher', [
                'voucher_id' => $voucher->id,
                'email' => $email,
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function generateCheckoutNumber(): string
    {
        $timestamp = now()->format('YmdHis');
        $random = strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 4));

        return "SRV-{$timestamp}-{$random}";
    }

    protected function generateVoucherCode(): string
    {
        do {
            $code = 'VCH-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (ServiceVoucher::query()->where('code', $code)->exists());

        return $code;
    }

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

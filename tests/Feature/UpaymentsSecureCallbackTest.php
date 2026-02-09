<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Services\UpaymentsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpaymentsSecureCallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Success endpoint must NEVER mark invoice paid (UI-only).
     */
    public function test_success_endpoint_with_captured_result_does_not_mark_invoice_paid(): void
    {
        $customer = Customer::create([
            'name' => 'Test Customer',
            'phone' => '96512345678',
            'email' => 'test@example.com',
            'is_active' => true,
            'is_completed' => true,
            'points' => 0,
        ]);
        $order = Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-TEST-001',
            'status' => 'pending',
            'total_amount' => 50.00,
            'delivery_type' => 'delivery',
            'payment_method' => 'online_link',
        ]);
        $invoice = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'INV-001',
            'amount_due' => 50.00,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'offer_discount' => 0,
            'used_points' => 0,
            'points_discount' => 0,
            'total_discount' => 0,
            'status' => 'pending',
            'paid_at' => null,
        ]);

        $response = $this->getJson('/api/payments/callback/success?' . http_build_query([
            'result' => 'CAPTURED',
            'requested_order_id' => $order->order_number,
            'track_id' => 'fake-track-123',
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.invoice_status', 'pending');

        $invoice->refresh();
        $this->assertSame('pending', $invoice->status);
        $this->assertNull($invoice->paid_at);
    }

    /**
     * Forged webhook without valid getPaymentStatus confirmation must NOT mark invoice paid.
     */
    public function test_forged_webhook_without_getpaymentstatus_confirmation_does_not_mark_invoice_paid(): void
    {
        $this->mock(UpaymentsService::class, function ($mock) {
            $mock->shouldReceive('getPaymentStatus')
                ->once()
                ->with('forged-track-456')
                ->andReturn([
                    'gateway_status_raw' => 'failed',
                    'is_success' => false,
                    'is_failed' => true,
                    'amount' => 50.0,
                    'currency' => 'KWD',
                    'track_id' => 'forged-track-456',
                    'receipt_id' => null,
                    'payment_id' => null,
                    'tran_id' => null,
                    'requested_order_id' => 'ORD-TEST-002',
                ]);
        });

        $customer = Customer::create([
            'name' => 'Test Customer',
            'phone' => '96512345678',
            'email' => 'test@example.com',
            'is_active' => true,
            'is_completed' => true,
            'points' => 0,
        ]);
        $order = Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-TEST-002',
            'status' => 'pending',
            'total_amount' => 50.00,
            'delivery_type' => 'delivery',
            'payment_method' => 'online_link',
        ]);
        Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'INV-002',
            'amount_due' => 50.00,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'offer_discount' => 0,
            'used_points' => 0,
            'points_discount' => 0,
            'total_discount' => 0,
            'status' => 'pending',
            'paid_at' => null,
        ]);

        $response = $this->postJson('/api/payments/callback/notification', [
            'track_id' => 'forged-track-456',
            'result' => 'CAPTURED',
            'requested_order_id' => 'ORD-TEST-002',
            'payment_id' => 'fake-payment-id',
            'tran_id' => 'fake-tran-id',
        ]);

        $response->assertSuccessful();

        $invoice = Invoice::where('order_id', $order->id)->first();
        $this->assertSame('pending', $invoice->status);
        $this->assertNull($invoice->paid_at);
    }

    /**
     * Duplicate webhook calls with same track_id must not create duplicate payments (idempotent).
     */
    public function test_duplicate_webhook_with_same_track_id_does_not_create_duplicate_payments(): void
    {
        $trackId = 'idempotent-track-789';
        $this->mock(UpaymentsService::class, function ($mock) use ($trackId) {
            $mock->shouldReceive('getPaymentStatus')
                ->times(2)
                ->with($trackId)
                ->andReturn([
                    'gateway_status_raw' => 'CAPTURED',
                    'is_success' => true,
                    'is_failed' => false,
                    'amount' => 30.0,
                    'currency' => 'KWD',
                    'track_id' => $trackId,
                    'receipt_id' => 'RCP-001',
                    'payment_id' => 'PAY-001',
                    'tran_id' => 'TRN-001',
                    'requested_order_id' => 'ORD-TEST-003',
                ]);
        });

        $customer = Customer::create([
            'name' => 'Test Customer',
            'phone' => '96512345678',
            'email' => 'test@example.com',
            'is_active' => true,
            'is_completed' => true,
            'points' => 0,
        ]);
        $order = Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-TEST-003',
            'status' => 'pending',
            'total_amount' => 30.00,
            'delivery_type' => 'delivery',
            'payment_method' => 'online_link',
        ]);
        $invoice = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'INV-003',
            'amount_due' => 30.00,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'offer_discount' => 0,
            'used_points' => 0,
            'points_discount' => 0,
            'total_discount' => 0,
            'status' => 'pending',
            'paid_at' => null,
        ]);

        $payload = [
            'track_id' => $trackId,
            'result' => 'CAPTURED',
            'requested_order_id' => 'ORD-TEST-003',
            'receipt_id' => 'RCP-001',
            'payment_id' => 'PAY-001',
            'tran_id' => 'TRN-001',
        ];

        $this->postJson('/api/payments/callback/notification', $payload)->assertSuccessful();
        $this->postJson('/api/payments/callback/notification', $payload)->assertSuccessful();

        $paymentsWithTrackId = Payment::where('gateway', 'upayments')->where('track_id', $trackId)->get();
        $this->assertCount(1, $paymentsWithTrackId);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);
    }
}

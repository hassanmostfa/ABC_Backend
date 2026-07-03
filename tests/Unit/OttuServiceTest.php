<?php

namespace Tests\Unit;

use App\Services\OttuService;
use Tests\TestCase;

class OttuServiceTest extends TestCase
{
    public function test_resolve_customer_email_uses_valid_email_when_present(): void
    {
        $service = new OttuService();
        $customer = (object) [
            'id' => 1,
            'email' => 'user@example.com',
            'phone' => '96550001001',
        ];

        $this->assertSame('user@example.com', $this->invokeResolveCustomerEmail($service, $customer));
    }

    public function test_resolve_customer_email_falls_back_when_email_missing_or_invalid(): void
    {
        $service = new OttuService();
        $customer = (object) [
            'id' => 2,
            'email' => '',
            'phone' => '96550001002',
        ];

        $this->assertSame('96550001002@example.com', $this->invokeResolveCustomerEmail($service, $customer));

        $customer->email = null;
        $this->assertSame('96550001002@example.com', $this->invokeResolveCustomerEmail($service, $customer));

        $customer->email = 'not-an-email';
        $this->assertSame('96550001002@example.com', $this->invokeResolveCustomerEmail($service, $customer));
    }

    public function test_resolve_customer_email_falls_back_to_customer_id_without_phone(): void
    {
        $service = new OttuService();
        $customer = (object) [
            'id' => 99,
            'email' => null,
            'phone' => null,
        ];

        $this->assertSame('customer-99@example.com', $this->invokeResolveCustomerEmail($service, $customer));
    }

    /**
     * @param  object{id?: int|string|null, email?: string|null, phone?: string|null}  $customer
     */
    private function invokeResolveCustomerEmail(OttuService $service, object $customer): string
    {
        $method = new \ReflectionMethod(OttuService::class, 'resolveCustomerEmail');
        $method->setAccessible(true);

        return $method->invoke($service, $customer);
    }

    public function test_is_successful_payment_accepts_ottu_success_and_paid(): void
    {
        $service = new OttuService();

        $this->assertTrue($service->isSuccessfulPayment('success', 'paid'));
        $this->assertTrue($service->isSuccessfulPayment('success', 'created'));
        $this->assertTrue($service->isSuccessfulPayment('pending', 'paid'));
    }

    public function test_is_successful_payment_accepts_pg_params_captured(): void
    {
        $service = new OttuService();

        $this->assertTrue($service->isSuccessfulPayment('pending', 'created', [
            'pg_params' => ['result' => 'CAPTURED'],
        ]));
    }

    public function test_build_status_result_from_webhook(): void
    {
        $service = new OttuService();

        $result = $service->buildStatusResultFromWebhook([
            'result' => 'success',
            'state' => 'paid',
            'amount' => '15.000',
            'currency_code' => 'KWD',
            'order_no' => 'CALS-2026-000007',
            'pg_params' => [
                'receipt_no' => 'R123',
                'payment_id' => 'P456',
            ],
        ], 'session-abc');

        $this->assertTrue($result['is_success']);
        $this->assertFalse($result['is_failed']);
        $this->assertSame('CALS-2026-000007', $result['requested_order_id']);
        $this->assertSame(15.0, $result['amount']);
    }

    public function test_verify_signature_matches_ottu_go_example_order(): void
    {
        config(['services.ottu.skip_signature_verify' => false]);
        config(['services.ottu.hmac_key' => 'pu9MpX3yPR']);

        $service = new OttuService();
        $payload = [
            'amount' => '86.000',
            'currency_code' => 'KWD',
            'customer_first_name' => 'example-customer',
            'signature' => '6143b8ad4bd283540721ab000f6de746e722231aaaa90bc38f639081d3ff9f67',
        ];

        $this->assertTrue($service->verifySignature($payload));
    }

    public function test_verify_redirect_params_without_signature_allows_api_follow_up(): void
    {
        $service = new OttuService();

        $this->assertTrue($service->verifyRedirectParams([
            'session_id' => 'sess-1',
            'order_no' => 'APP-2026-000001',
        ]));
    }

    public function test_verify_redirect_params_rejects_tampered_signature(): void
    {
        config(['services.ottu.skip_signature_verify' => false]);
        config(['services.ottu.hmac_key' => 'pu9MpX3yPR']);

        $service = new OttuService();

        $this->assertFalse($service->verifyRedirectParams([
            'amount' => '86.000',
            'currency_code' => 'KWD',
            'signature' => 'invalid',
        ]));
    }
}

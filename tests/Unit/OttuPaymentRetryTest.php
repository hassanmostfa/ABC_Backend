<?php

namespace Tests\Unit;

use App\Repositories\OrderRepositoryInterface;
use App\Services\OttuPaymentProcessor;
use App\Services\OttuService;
use App\Models\Payment;
use Tests\TestCase;

class OttuPaymentRetryTest extends TestCase
{
    public function test_resolve_payment_status_stays_pending_on_failed_attempt_when_retry_enabled(): void
    {
        config(['services.ottu.enable_pending_status' => true]);

        $processor = new OttuPaymentProcessor(
            $this->createMock(OrderRepositoryInterface::class),
            $this->createMock(OttuService::class),
        );

        $status = $processor->resolvePaymentStatus([
            'is_success' => false,
            'is_failed' => true,
        ]);

        $this->assertSame(Payment::STATUS_PENDING, $status);
    }

    public function test_resolve_payment_status_marks_completed_on_success(): void
    {
        config(['services.ottu.enable_pending_status' => true]);

        $processor = new OttuPaymentProcessor(
            $this->createMock(OrderRepositoryInterface::class),
            $this->createMock(OttuService::class),
        );

        $status = $processor->resolvePaymentStatus([
            'is_success' => true,
            'is_failed' => false,
        ]);

        $this->assertSame(Payment::STATUS_COMPLETED, $status);
    }

    public function test_resolve_payment_status_marks_failed_when_retry_disabled(): void
    {
        config(['services.ottu.enable_pending_status' => false]);

        $processor = new OttuPaymentProcessor(
            $this->createMock(OrderRepositoryInterface::class),
            $this->createMock(OttuService::class),
        );

        $status = $processor->resolvePaymentStatus([
            'is_success' => false,
            'is_failed' => true,
        ]);

        $this->assertSame(Payment::STATUS_FAILED, $status);
    }
}

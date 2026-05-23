<?php

namespace Tests\Unit;

use App\Services\OrderDraft;
use Tests\TestCase;

class OrderDraftTest extends TestCase
{
    public function test_payload_round_trip_preserves_amounts(): void
    {
        $draft = new OrderDraft(
            requestData: ['customer_id' => 1, 'payment_method' => 'online_link'],
            offersData: [],
            offersToProcess: [],
            offersToAttach: [],
            orderItemsData: [
                ['variant_id' => 5, 'name' => 'Item', 'quantity' => 2, 'unit_price' => 4.5, 'total_price' => 9.0],
            ],
            totalAmount: 9.0,
            offerDiscount: 0.0,
            couponsDiscount: 1.0,
            appliedCouponCode: 'SAVE1',
            usedPoints: 0,
            pointsDiscount: 0.0,
            deliveryType: 'delivery',
            invoiceAmounts: [
                'amountDue' => 10.5,
                'taxAmount' => 0.5,
                'deliveryFee' => 2.0,
                'totalDiscount' => 1.0,
            ],
            orderData: ['customer_id' => 1, 'payment_method' => 'online_link'],
            source: 'app',
            paymentMethod: 'online_link',
            paymentGatewaySrc: 'knet',
        );

        $restored = OrderDraft::fromPayloadArray($draft->toPayloadArray());

        $this->assertSame(10.5, $restored->amountDue());
        $this->assertSame('SAVE1', $restored->appliedCouponCode);
        $this->assertSame('knet', $restored->paymentGatewaySrc);
        $this->assertCount(1, $restored->orderItemsData);
    }
}

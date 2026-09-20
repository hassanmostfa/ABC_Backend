<?php

namespace Tests\Unit;

use App\Models\Subscription;
use Tests\TestCase;

class SubscriptionFreeMonthsTest extends TestCase
{
    public function test_delivery_period_adds_free_months_to_subscription_period(): void
    {
        $subscription = new Subscription([
            'period' => '6',
            'discount_type' => 'free_months',
            'discount_free_months' => 1,
        ]);

        $this->assertSame(1, $subscription->freeMonths());
        $this->assertSame(6, $subscription->getEffectivePeriodForPricing());
        $this->assertSame(7, $subscription->getDeliveryPeriodInMonths());
    }

    public function test_delivery_period_matches_plan_when_there_are_no_free_months(): void
    {
        $subscription = new Subscription([
            'period' => '6',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'discount_free_months' => 1,
        ]);

        $this->assertSame(0, $subscription->freeMonths());
        $this->assertSame(6, $subscription->getEffectivePeriodForPricing());
        $this->assertSame(6, $subscription->getDeliveryPeriodInMonths());
    }
}

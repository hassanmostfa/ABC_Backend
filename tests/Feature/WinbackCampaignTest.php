<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WinbackCampaignTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        Setting::query()->updateOrCreate(['key' => 'winback_coupon_enabled'], ['value' => '1']);
        Setting::query()->updateOrCreate(['key' => 'winback_coupon_discount_value'], ['value' => '15']);
        Setting::query()->updateOrCreate(['key' => 'winback_coupon_valid_days'], ['value' => '14']);
        Setting::query()->updateOrCreate(['key' => 'winback_inactive_days'], ['value' => '30']);
    }

    public function test_quiet_customer_gets_one_coupon_and_another_only_after_the_next_quiet_period(): void
    {
        $customer = $this->createCustomer('96550002001', 'winback@example.com', now()->subDays(70));

        $this->artisan('customers:send-winback-coupons')->assertSuccessful();

        $this->assertSame(1, Campaign::query()->where('customer_id', $customer->id)->count());
        $first = Coupon::query()->where('customer_id', $customer->id)->where('type', Coupon::TYPE_WINBACK)->first();
        $this->assertNotNull($first);
        $this->assertTrue($first->is_active);
        $this->assertSame('15.000', (string) $first->discount_value);
        $this->assertTrue($first->expires_at->isSameDay(now()->addDays(14)));

        $this->artisan('customers:send-winback-coupons')->assertSuccessful();
        $this->assertSame(1, Campaign::query()->where('customer_id', $customer->id)->count());

        Carbon::setTestNow(now()->addDays(30));

        $this->artisan('customers:send-winback-coupons')->assertSuccessful();

        $this->assertSame(2, Campaign::query()->where('customer_id', $customer->id)->count());
        $first->refresh();
        $this->assertFalse($first->is_active);

        $active = Coupon::query()
            ->where('customer_id', $customer->id)
            ->where('type', Coupon::TYPE_WINBACK)
            ->where('is_active', true)
            ->get();
        $this->assertCount(1, $active);
        $this->assertTrue($active->first()->expires_at->isSameDay(now()->addDays(14)));
    }

    public function test_customer_who_ordered_inside_the_quiet_window_is_skipped(): void
    {
        $customer = $this->createCustomer('96550002002', 'recent-order@example.com', now()->subDays(70));
        Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'APP-WINBACK-1',
            'status' => 'completed',
            'total_amount' => 10,
            'delivery_type' => 'pickup',
        ]);

        $this->artisan('customers:send-winback-coupons')->assertSuccessful();

        $this->assertSame(0, Campaign::query()->where('customer_id', $customer->id)->count());
    }

    public function test_cancelled_order_does_not_count_as_a_purchase(): void
    {
        $customer = $this->createCustomer('96550002003', 'cancelled-order@example.com', now()->subDays(70));
        Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'APP-WINBACK-2',
            'status' => 'cancelled',
            'total_amount' => 10,
            'delivery_type' => 'pickup',
        ]);

        $this->artisan('customers:send-winback-coupons')->assertSuccessful();

        $this->assertSame(1, Campaign::query()->where('customer_id', $customer->id)->count());
    }

    private function createCustomer(string $phone, string $email, Carbon $createdAt): Customer
    {
        $customer = Customer::create([
            'name' => 'Winback Customer',
            'phone' => $phone,
            'email' => $email,
            'is_active' => true,
            'is_completed' => true,
            'points' => 0,
        ]);
        $customer->created_at = $createdAt;
        $customer->save();

        return $customer;
    }
}

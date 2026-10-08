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

class TopCustomerCampaignTest extends TestCase
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

        Setting::query()->updateOrCreate(['key' => 'top_customer_coupon_enabled'], ['value' => '1']);
        Setting::query()->updateOrCreate(['key' => 'top_customer_coupon_count'], ['value' => '2']);
        Setting::query()->updateOrCreate(['key' => 'top_customer_coupon_discount_value'], ['value' => '20']);
        Setting::query()->updateOrCreate(['key' => 'top_customer_coupon_valid_days'], ['value' => '14']);
    }

    public function test_only_the_top_customers_receive_a_coupon_once_in_the_same_month(): void
    {
        $first = $this->createCustomer('96550003001', 'top-1@example.com');
        $second = $this->createCustomer('96550003002', 'top-2@example.com');
        $third = $this->createCustomer('96550003003', 'top-3@example.com');

        $this->placeOrders($first, 3);
        $this->placeOrders($second, 2);
        $this->placeOrders($third, 1);

        $this->artisan('customers:send-top-customer-coupons')->assertSuccessful();

        $this->assertSame(1, Campaign::query()->where('customer_id', $first->id)->where('type', Campaign::TYPE_TOP_CUSTOMER)->count());
        $this->assertSame(1, Campaign::query()->where('customer_id', $second->id)->where('type', Campaign::TYPE_TOP_CUSTOMER)->count());
        $this->assertSame(0, Campaign::query()->where('customer_id', $third->id)->count());

        $coupon = Coupon::query()->where('customer_id', $first->id)->where('type', Coupon::TYPE_TOP_CUSTOMER)->first();
        $this->assertNotNull($coupon);
        $this->assertSame('20.000', (string) $coupon->discount_value);
        $this->assertSame('percentage', $coupon->discount_type);
        $this->assertTrue($coupon->expires_at->isSameDay(now()->addDays(14)));

        $this->artisan('customers:send-top-customer-coupons')->assertSuccessful();
        $this->assertSame(1, Campaign::query()->where('customer_id', $first->id)->count());
        $this->assertSame(1, Campaign::query()->where('customer_id', $second->id)->count());
    }

    public function test_a_new_coupon_is_sent_the_next_month_and_the_old_code_is_turned_off(): void
    {
        $customer = $this->createCustomer('96550003004', 'top-renew@example.com');
        $this->placeOrders($customer, 2);

        $this->artisan('customers:send-top-customer-coupons')->assertSuccessful();
        $first = Coupon::query()->where('customer_id', $customer->id)->first();

        Carbon::setTestNow(now()->addMonth());

        $this->artisan('customers:send-top-customer-coupons')->assertSuccessful();

        $first->refresh();
        $this->assertFalse($first->is_active);
        $this->assertSame(2, Campaign::query()->where('customer_id', $customer->id)->count());
        $this->assertSame(1, Coupon::query()->where('customer_id', $customer->id)->where('is_active', true)->count());
    }

    private function placeOrders(Customer $customer, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Order::create([
                'customer_id' => $customer->id,
                'order_number' => 'TOP-'.$customer->id.'-'.$i,
                'status' => 'completed',
                'total_amount' => 10,
                'delivery_type' => 'pickup',
            ]);
        }
    }

    private function createCustomer(string $phone, string $email): Customer
    {
        return Customer::create([
            'name' => 'Top Customer',
            'phone' => $phone,
            'email' => $email,
            'is_active' => true,
            'is_completed' => true,
            'points' => 0,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\CouponDeviceUsage;
use App\Models\Customer;
use App\Services\Orders\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CouponDeviceUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_rejects_a_coupon_already_used_on_the_same_device(): void
    {
        $customer = $this->createCustomer('96550001992', 'coupon-device@example.com');
        Sanctum::actingAs($customer, [], 'sanctum');

        $coupon = Coupon::create([
            'code' => 'SUMMER10',
            'type' => Coupon::TYPE_GENERAL,
            'name' => 'Summer',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'minimum_order_amount' => 0,
            'usage_limit' => 100,
            'used_count' => 0,
            'is_active' => true,
        ]);

        $this->postJson('/api/mobile/coupons/apply', [
            'code' => 'SUMMER10',
            'order_amount' => 20,
        ])->assertStatus(400)
            ->assertJsonPath('message', 'A valid device id is required to use this coupon.');

        $this->postJson('/api/mobile/coupons/apply', [
            'code' => 'SUMMER10',
            'order_amount' => 20,
        ], [
            'X-Device-Id' => 'device-aaa-1111',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.coupon_code', 'SUMMER10');

        app(CouponService::class)->incrementCouponUsage($coupon->code, 'device-aaa-1111', $customer->id);

        $this->postJson('/api/mobile/coupons/apply', [
            'code' => 'SUMMER10',
            'order_amount' => 20,
        ], [
            'X-Device-Id' => 'device-aaa-1111',
        ])->assertStatus(400)
            ->assertJsonPath('message', 'This coupon has already been used on this device.');

        $this->postJson('/api/mobile/coupons/apply', [
            'code' => 'SUMMER10',
            'order_amount' => 20,
        ], [
            'X-Device-Id' => 'device-bbb-2222',
        ])->assertOk()
            ->assertJsonPath('data.coupon_code', 'SUMMER10');

        $this->assertDatabaseHas('coupon_device_usages', [
            'coupon_id' => $coupon->id,
            'device_id' => 'device-aaa-1111',
            'device_scope' => 'coupon:'.$coupon->id,
        ]);
    }

    public function test_welcome_coupon_can_be_redeemed_only_once_per_device(): void
    {
        $first = $this->createCustomer('96550001993', 'welcome-a@example.com');
        $second = $this->createCustomer('96550001994', 'welcome-b@example.com');

        $firstCoupon = $this->welcomeCoupon($first, 'WELCOMEAAAAAA');
        $secondCoupon = $this->welcomeCoupon($second, 'WELCOMEBBBBBB');
        $service = app(CouponService::class);

        $firstUse = $service->validateForApplyCode($firstCoupon->code, $first->id, 20, [], 'phone-device-1', true);
        $this->assertTrue($firstUse['success']);

        $service->incrementCouponUsage($firstCoupon->code, 'phone-device-1', $first->id);

        $secondUse = $service->validateForApplyCode($secondCoupon->code, $second->id, 20, [], 'phone-device-1', true);
        $this->assertFalse($secondUse['success']);
        $this->assertSame('A welcome coupon has already been used on this device.', $secondUse['message']);

        $otherDevice = $service->validateForApplyCode($secondCoupon->code, $second->id, 20, [], 'phone-device-2', true);
        $this->assertTrue($otherDevice['success']);

        $this->assertSame(1, CouponDeviceUsage::query()->where('device_scope', 'welcome')->count());
    }

    private function welcomeCoupon(Customer $customer, string $code): Coupon
    {
        return Coupon::create([
            'code' => $code,
            'type' => Coupon::TYPE_WELCOME,
            'name' => 'Welcome',
            'discount_type' => 'fixed',
            'discount_value' => 1,
            'minimum_order_amount' => 0,
            'usage_limit' => 1,
            'used_count' => 0,
            'is_active' => true,
            'customer_id' => $customer->id,
            'starts_at' => now()->subMinute(),
            'expires_at' => now()->addMonth(),
        ]);
    }

    private function createCustomer(string $phone, string $email): Customer
    {
        return Customer::create([
            'name' => 'Coupon Customer',
            'phone' => $phone,
            'email' => $email,
            'is_active' => true,
            'is_completed' => true,
            'points' => 0,
        ]);
    }
}

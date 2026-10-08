<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerAppInfoTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_stores_device_platform_and_app_version_from_headers(): void
    {
        $customer = $this->createCustomer();
        Sanctum::actingAs($customer, [], 'sanctum');

        $this->getJson('/api/mobile/profile', [
            'X-Device-Platform' => 'iOS',
            'X-App-Version' => '1.4.2',
        ])->assertOk()
            ->assertJsonPath('success', true);

        $customer->refresh();
        $this->assertSame('ios', $customer->device_platform);
        $this->assertSame('1.4.2', $customer->app_version);
    }

    public function test_profile_without_headers_leaves_app_info_unchanged(): void
    {
        $customer = $this->createCustomer();
        $customer->update([
            'device_platform' => 'android',
            'app_version' => '1.0.0',
        ]);
        Sanctum::actingAs($customer, [], 'sanctum');

        $this->getJson('/api/mobile/profile')->assertOk();

        $customer->refresh();
        $this->assertSame('android', $customer->device_platform);
        $this->assertSame('1.0.0', $customer->app_version);
    }

    public function test_invalid_platform_is_ignored_and_valid_version_is_stored(): void
    {
        $customer = $this->createCustomer();
        Sanctum::actingAs($customer, [], 'sanctum');

        $this->getJson('/api/mobile/profile', [
            'X-Device-Platform' => 'web',
            'X-App-Version' => '2.0.1',
        ])->assertOk();

        $customer->refresh();
        $this->assertNull($customer->device_platform);
        $this->assertSame('2.0.1', $customer->app_version);
    }

    private function createCustomer(): Customer
    {
        return Customer::create([
            'name' => 'App Customer',
            'phone' => '96550001991',
            'email' => 'app-info@example.com',
            'is_active' => true,
            'is_completed' => true,
            'points' => 0,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\WalletChargeOffer;
use App\Services\Payment\OttuService;
use App\Services\Wallet\WalletChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class WalletChargeOfferTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_charge_settings_returns_gift_percentage_without_offers(): void
    {
        $this->seedChargeSettings();
        $this->createOffer(10, 15);
        Sanctum::actingAs($this->createCustomer(), [], 'sanctum');

        $this->getJson('/api/mobile/wallet/charge-settings')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.wallet_charge_gift', 5)
            ->assertJsonPath('data.minimum_wallet_charge', 1)
            ->assertJsonMissingPath('data.offers');
    }

    public function test_charge_offers_returns_active_offers_only(): void
    {
        $this->seedChargeSettings();
        $active = $this->createOffer(10, 15);
        $this->createOffer(50, 70, [
            'sort_order' => 2,
        ]);
        $this->createOffer(100, 150, [
            'is_active' => false,
            'sort_order' => 3,
        ]);

        Sanctum::actingAs($this->createCustomer(), [], 'sanctum');

        $response = $this->getJson('/api/mobile/wallet/charge-offers');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonPath('data.0.charge_amount', 10)
            ->assertJsonPath('data.0.get_amount', 15)
            ->assertJsonPath('data.0.bonus_amount', 5);
    }

    public function test_charge_without_offer_applies_wallet_charge_gift_as_percentage(): void
    {
        $this->seedChargeSettings(5);
        $this->mockOttuWalletCharge();
        $customer = $this->createCustomer();
        Sanctum::actingAs($customer, [], 'sanctum');

        $response = $this->postJson('/api/mobile/wallet/charge', [
            'amount' => 50,
            'src' => 'knet',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.amount', 50)
            ->assertJsonPath('data.bonus_amount', 2.5)
            ->assertJsonPath('data.total_amount', 52.5)
            ->assertJsonPath('data.payment.offer_id', null);

        $this->assertDatabaseHas('payments', [
            'customer_id' => $customer->id,
            'type' => Payment::TYPE_WALLET_CHARGE,
            'amount' => 50,
            'bonus_amount' => 2.5,
            'total_amount' => 52.5,
            'wallet_charge_offer_id' => null,
        ]);
    }

    public function test_charge_with_offer_credits_get_amount_and_charges_offer_amount(): void
    {
        $this->seedChargeSettings();
        $this->mockOttuWalletCharge();
        $offer = $this->createOffer(10, 15);
        $customer = $this->createCustomer('wallet-offer@example.com', '96550001002');
        Sanctum::actingAs($customer, [], 'sanctum');

        $response = $this->postJson('/api/mobile/wallet/charge', [
            'offer_id' => $offer->id,
            'src' => 'cc',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.amount', 10)
            ->assertJsonPath('data.bonus_amount', 5)
            ->assertJsonPath('data.total_amount', 15)
            ->assertJsonPath('data.payment.offer_id', $offer->id)
            ->assertJsonPath('data.payment.offer.charge_amount', 10)
            ->assertJsonPath('data.payment.offer.get_amount', 15);

        $this->assertDatabaseHas('payments', [
            'customer_id' => $customer->id,
            'type' => Payment::TYPE_WALLET_CHARGE,
            'amount' => 10,
            'bonus_amount' => 5,
            'total_amount' => 15,
            'wallet_charge_offer_id' => $offer->id,
        ]);
    }

    public function test_selected_offer_wins_over_custom_amount(): void
    {
        $this->seedChargeSettings();
        $this->mockOttuWalletCharge();
        $offer = $this->createOffer(10, 15);
        Sanctum::actingAs($this->createCustomer('wallet-override@example.com', '96550001003'), [], 'sanctum');

        $this->postJson('/api/mobile/wallet/charge', [
            'amount' => 50,
            'offer_id' => $offer->id,
            'src' => 'knet',
        ])->assertOk()
            ->assertJsonPath('data.amount', 10)
            ->assertJsonPath('data.total_amount', 15);
    }

    public function test_inactive_offer_is_rejected(): void
    {
        $this->seedChargeSettings();
        $this->mockOttuWalletCharge();
        $offer = $this->createOffer(10, 15, ['is_active' => false]);
        Sanctum::actingAs($this->createCustomer('wallet-inactive@example.com', '96550001004'), [], 'sanctum');

        $this->postJson('/api/mobile/wallet/charge', [
            'offer_id' => $offer->id,
            'src' => 'knet',
        ])->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_charge_requires_amount_when_no_offer_is_selected(): void
    {
        $this->seedChargeSettings();
        Sanctum::actingAs($this->createCustomer('wallet-amount@example.com', '96550001005'), [], 'sanctum');

        $this->postJson('/api/mobile/wallet/charge', [
            'src' => 'knet',
        ])->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_process_success_credits_offer_get_amount_to_wallet(): void
    {
        $this->seedChargeSettings();
        $this->mockOttuWalletCharge();
        $offer = $this->createOffer(50, 70);
        $customer = $this->createCustomer('wallet-success@example.com', '96550001006');

        $result = app(WalletChargeService::class)->createCharge($customer->id, 0, 'knet', $offer->id);
        $this->assertTrue($result['success']);

        $credited = app(WalletChargeService::class)->processSuccess($result['payment']);

        $this->assertTrue($credited);
        $this->assertEquals(70, (float) $customer->fresh()->wallet->balance);
        $this->assertSame('completed', $result['payment']->fresh()->status);
    }

    private function seedChargeSettings(float $giftPercent = 5, float $minimum = 1): void
    {
        Setting::create(['key' => 'wallet_charge_gift', 'value' => (string) $giftPercent]);
        Setting::create(['key' => 'minimum_wallet_charge', 'value' => (string) $minimum]);
    }

    private function mockOttuWalletCharge(): void
    {
        $ottu = Mockery::mock(OttuService::class);
        $ottu->shouldReceive('createWalletChargePayment')->andReturn('https://pay.example/wallet');
        $this->app->instance(OttuService::class, $ottu);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createOffer(float $chargeAmount, float $getAmount, array $overrides = []): WalletChargeOffer
    {
        return WalletChargeOffer::create(array_merge([
            'charge_amount' => $chargeAmount,
            'get_amount' => $getAmount,
            'sort_order' => 1,
            'is_active' => true,
        ], $overrides));
    }

    private function createCustomer(string $email = 'wallet-charge@example.com', string $phone = '96550001001'): Customer
    {
        return Customer::create([
            'name' => 'Wallet Customer',
            'phone' => $phone,
            'email' => $email,
            'is_active' => true,
            'is_completed' => true,
            'points' => 0,
        ]);
    }
}

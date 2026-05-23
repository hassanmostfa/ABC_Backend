<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Category;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\OrderCheckout;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Services\OrderCheckoutService;
use App\Services\OrderService;
use App\Services\OttuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class PayFirstOnlineOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_online_checkout_does_not_create_order_until_payment(): void
    {
        [$customer, $address, $variant] = $this->seedOrderPrerequisites();

        $partial = Mockery::mock(OttuService::class)->makePartial();
        $partial->shouldReceive('createCheckoutPayment')->once()->andReturn('https://pay.example/checkout?session_id=test-session-001');
        $partial->shouldReceive('getLastCheckoutSessionId')->andReturn('test-session-001');
        $partial->shouldReceive('ensurePendingCheckoutPayment')->once()->andReturnUsing(function ($checkout, $sessionId, $amount) {
            return Payment::create([
                'invoice_id' => null,
                'order_checkout_id' => $checkout->id,
                'customer_id' => $checkout->customer_id,
                'reference' => $checkout->order_number . '-test-session',
                'type' => Payment::TYPE_ORDER_CHECKOUT,
                'payment_number' => 'PAY-TEST-001',
                'gateway' => 'ottu',
                'track_id' => $sessionId,
                'amount' => $amount,
                'total_amount' => $amount,
                'method' => 'online',
                'status' => 'pending',
            ]);
        });
        $this->instance(OttuService::class, $partial);

        $result = app(OrderService::class)->createOrder([
            'customer_id' => $customer->id,
            'customer_address_id' => $address->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'delivery_time' => '10:00',
            'payment_method' => 'online_link',
            'src' => 'knet',
            'source' => 'app',
            'items' => [
                ['variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['is_checkout']);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_checkouts', 1);
        $this->assertSame('pending', $result['checkout']->status);
    }

    public function test_cash_order_still_creates_order_immediately(): void
    {
        [$customer, $address, $variant] = $this->seedOrderPrerequisites();

        $result = app(OrderService::class)->createOrder([
            'customer_id' => $customer->id,
            'customer_address_id' => $address->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'delivery_time' => '10:00',
            'payment_method' => 'cash',
            'source' => 'app',
            'items' => [
                ['variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertArrayNotHasKey('is_checkout', $result);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_checkouts', 0);
        $this->assertSame('cash', $result['order']->payment_method);
    }

    public function test_fulfill_checkout_creates_paid_order(): void
    {
        [$customer, $address, $variant] = $this->seedOrderPrerequisites();

        $draft = app(OrderService::class)->prepareOrderDraft([
            'customer_id' => $customer->id,
            'customer_address_id' => $address->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'delivery_time' => '10:00',
            'payment_method' => 'online_link',
            'src' => 'knet',
            'source' => 'app',
            'items' => [
                ['variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]);

        $checkout = OrderCheckout::create([
            'customer_id' => $customer->id,
            'source' => 'app',
            'order_number' => app(OrderService::class)->generateOrderNumber('app'),
            'payload' => $draft->toPayloadArray(),
            'payment_gateway_src' => 'knet',
            'amount_due' => $draft->amountDue(),
            'status' => OrderCheckout::STATUS_PENDING,
            'ottu_session_id' => 'fulfill-session-001',
            'payment_link' => 'https://pay.example/checkout',
            'expires_at' => now()->addHour(),
        ]);

        $payment = Payment::create([
            'invoice_id' => null,
            'order_checkout_id' => $checkout->id,
            'customer_id' => $customer->id,
            'reference' => $checkout->order_number . '-fulfill',
            'type' => Payment::TYPE_ORDER_CHECKOUT,
            'payment_number' => 'PAY-TEST-002',
            'gateway' => 'ottu',
            'track_id' => 'fulfill-session-001',
            'amount' => $checkout->amount_due,
            'total_amount' => $checkout->amount_due,
            'method' => 'online',
            'status' => 'pending',
        ]);

        $result = app(OrderCheckoutService::class)->fulfillCheckout($checkout, $payment, [
            'is_success' => true,
            'tran_id' => 'T1',
            'payment_id' => 'P1',
            'receipt_id' => 'R1',
        ]);

        $this->assertTrue($result['processed']);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('order_checkouts', [
            'id' => $checkout->id,
            'status' => OrderCheckout::STATUS_PAID,
        ]);

        $order = Order::query()->first();
        $this->assertNotNull($order);
        $this->assertSame($checkout->order_number, $order->order_number);
        $this->assertSame('online_link', $order->payment_method);
        $this->assertSame('paid', $order->invoice->status);
        $this->assertSame('completed', $payment->fresh()->status);
    }

    public function test_mobile_store_online_checkout_api_shape(): void
    {
        [$customer, $address, $variant] = $this->seedOrderPrerequisites();
        Sanctum::actingAs($customer, [], 'sanctum');

        $partial = Mockery::mock(OttuService::class)->makePartial();
        $partial->shouldReceive('createCheckoutPayment')->once()->andReturn('https://pay.example/checkout?session_id=api-session-001');
        $partial->shouldReceive('getLastCheckoutSessionId')->andReturn('api-session-001');
        $partial->shouldReceive('ensurePendingCheckoutPayment')->once()->andReturnUsing(function ($checkout, $sessionId, $amount) {
            return Payment::create([
                'invoice_id' => null,
                'order_checkout_id' => $checkout->id,
                'customer_id' => $checkout->customer_id,
                'reference' => $checkout->order_number . '-api',
                'type' => Payment::TYPE_ORDER_CHECKOUT,
                'payment_number' => 'PAY-TEST-003',
                'gateway' => 'ottu',
                'track_id' => $sessionId,
                'amount' => $amount,
                'total_amount' => $amount,
                'method' => 'online',
                'status' => 'pending',
            ]);
        });
        $this->instance(OttuService::class, $partial);

        $response = $this->postJson('/api/mobile/orders', [
            'customer_address_id' => $address->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'delivery_time' => '11:00',
            'payment_method' => 'online_link',
            'src' => 'knet',
            'items' => [
                ['variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.payment_method', 'online_link');
        $response->assertJsonPath('data.invoice.status', 'pending');
        $response->assertJsonPath('data.invoice.payment_link', 'https://pay.example/checkout?session_id=api-session-001');
        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * @return array{0: Customer, 1: CustomerAddress, 2: ProductVariant}
     */
    protected function seedOrderPrerequisites(): array
    {
        Setting::query()->updateOrCreate(['key' => 'minimum_home_order'], ['value' => '1']);
        Setting::query()->updateOrCreate(['key' => 'app_ordering_enabled'], ['value' => '1']);

        $country = Country::create([
            'name_en' => 'Kuwait',
            'name_ar' => 'الكويت',
            'is_active' => true,
        ]);
        $governorate = Governorate::create([
            'country_id' => $country->id,
            'name_en' => 'Capital',
            'name_ar' => 'العاصمة',
            'is_active' => true,
        ]);
        $area = Area::create([
            'governorate_id' => $governorate->id,
            'name_en' => 'Salmiya',
            'name_ar' => 'السالمية',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'Test Customer',
            'phone' => '96550001001',
            'email' => 'payfirst@example.com',
            'is_active' => true,
            'is_completed' => true,
            'points' => 0,
        ]);

        $address = CustomerAddress::create([
            'customer_id' => $customer->id,
            'country_id' => $country->id,
            'governorate_id' => $governorate->id,
            'area_id' => $area->id,
            'street' => 'Main',
            'house' => '1',
            'block' => '2',
        ]);

        $category = Category::create([
            'name_en' => 'Food',
            'name_ar' => 'طعام',
            'is_active' => true,
        ]);

        $product = Product::create([
            'name_en' => 'Test Product',
            'name_ar' => 'منتج',
            'sku' => 'SKU-TEST-001',
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'size' => 'M',
            'sku' => 'SKU-TEST-001-M',
            'price' => 10.00,
            'quantity' => 100,
            'is_active' => true,
        ]);

        return [$customer, $address, $variant];
    }
}

<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Category;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Services\ERP\ErpOrderService;
use App\Services\Orders\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncErpOrderStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.erp.url' => 'https://erp.test',
            'services.erp.username' => 'erp-user',
            'services.erp.password' => 'erp-pass',
            'services.erp.driver' => 'http',
            'services.erp.status_sync_limit' => 1,
        ]);
    }

    public function test_sync_updates_pending_order_when_erp_reports_delivered(): void
    {
        $order = $this->createSentOrder(['status' => 'pending']);

        Http::fake([
            'https://erp.test/API/Order/GetOrderStatus*' => Http::response([
                'data' => [
                    'status' => 'Delivered',
                    'invoiceNo' => 'I84809050',
                    'scheduleDate' => '2026-08-24T12:34:00',
                ],
                'message' => 'Success',
                'status' => 0,
            ], 200),
        ]);

        $result = app(ErpOrderService::class)->syncOrderStatusFromErp($order);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['updated']);
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('I84809050', $order->fresh()->erp_invoice_no);
        $this->assertNotNull($order->fresh()->erp_status_synced_at);
    }

    public function test_batch_sync_prioritizes_never_synced_orders_over_recently_checked_ones(): void
    {
        $recentlySynced = $this->createSentOrder([
            'status' => 'pending',
            'erp_status_synced_at' => now(),
        ]);

        $neverSynced = $this->createSentOrder([
            'status' => 'pending',
            'erp_status_synced_at' => null,
        ]);

        Http::fake([
            'https://erp.test/API/Order/GetOrderStatus*' => function ($request) use ($neverSynced) {
                if (!str_contains($request->url(), 'OrderNumber=' . urlencode($neverSynced->order_number))) {
                    return Http::response([
                        'data' => ['status' => 'Pending'],
                        'message' => 'Success',
                        'status' => 0,
                    ], 200);
                }

                return Http::response([
                    'data' => [
                        'status' => 'Delivered',
                        'invoiceNo' => 'I999',
                        'scheduleDate' => '2026-08-24T12:34:00',
                    ],
                    'message' => 'Success',
                    'status' => 0,
                ], 200);
            },
        ]);

        $summary = app(ErpOrderService::class)->syncPendingAndProcessingOrderStatuses(1);

        $this->assertSame(1, $summary['orders']['checked']);
        $this->assertSame(1, $summary['orders']['updated']);
        $this->assertSame('completed', $neverSynced->fresh()->status);
        $this->assertSame('pending', $recentlySynced->fresh()->status);
    }

    public function test_batch_sync_skips_cancelled_orders(): void
    {
        $cancelled = $this->createSentOrder(['status' => 'cancelled']);
        $pending = $this->createSentOrder(['status' => 'pending']);

        Http::fake([
            'https://erp.test/API/Order/GetOrderStatus*' => Http::response([
                'data' => [
                    'status' => 'Delivered',
                    'invoiceNo' => 'I100',
                    'scheduleDate' => '2026-08-24T12:34:00',
                ],
                'message' => 'Success',
                'status' => 0,
            ], 200),
        ]);

        $summary = app(ErpOrderService::class)->syncPendingAndProcessingOrderStatuses(10);

        $this->assertSame(1, $summary['orders']['checked']);
        $this->assertSame(1, $summary['orders']['eligible_total']);
        $this->assertSame('cancelled', $cancelled->fresh()->status);
        $this->assertSame('completed', $pending->fresh()->status);
        $this->assertNull($cancelled->fresh()->erp_status_synced_at);
    }

    public function test_batch_sync_includes_rejected_orders(): void
    {
        $rejected = $this->createSentOrder(['status' => 'rejected']);
        $pending = $this->createSentOrder(['status' => 'pending']);

        Http::fake([
            'https://erp.test/API/Order/GetOrderStatus*' => Http::response([
                'data' => [
                    'status' => 'Delivered',
                    'invoiceNo' => 'I200',
                    'scheduleDate' => '2026-08-24T12:34:00',
                ],
                'message' => 'Success',
                'status' => 0,
            ], 200),
        ]);

        $summary = app(ErpOrderService::class)->syncPendingAndProcessingOrderStatuses(10);

        $this->assertSame(2, $summary['orders']['checked']);
        $this->assertSame(2, $summary['orders']['eligible_total']);
        $this->assertSame('completed', $rejected->fresh()->status);
        $this->assertSame('completed', $pending->fresh()->status);
        $this->assertNotNull($rejected->fresh()->erp_status_synced_at);
    }

    protected function createSentOrder(array $overrides = []): Order
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

        $order = $result['order'];
        $order->update(array_merge([
            'is_sent_to_erp' => true,
            'status' => 'pending',
        ], $overrides));

        return $order->fresh();
    }

    /**
     * @return array{0: Customer, 1: CustomerAddress, 2: ProductVariant}
     */
    protected function seedOrderPrerequisites(): array
    {
        Setting::query()->updateOrCreate(['key' => 'minimum_home_order'], ['value' => '1']);
        Setting::query()->updateOrCreate(['key' => 'app_ordering_enabled'], ['value' => '1']);
        Setting::query()->updateOrCreate(['key' => 'cash_customer_code'], ['value' => '50001001']);

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
            'phone' => '96550001002',
            'email' => 'erp-sync@example.com',
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
            'block' => '1',
            'avenue' => 'A',
            'building' => '10',
            'floor' => '1',
            'apartment' => '1',
            'is_default' => true,
        ]);

        $category = Category::create([
            'name_en' => 'Water',
            'name_ar' => 'ماء',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name_en' => 'Bottle',
            'name_ar' => 'زجاجة',
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'name_en' => '19L',
            'name_ar' => '19 لتر',
            'price' => 1.500,
            'is_active' => true,
            'sku' => 'SKU-ERP-SYNC',
        ]);

        return [$customer, $address, $variant];
    }
}

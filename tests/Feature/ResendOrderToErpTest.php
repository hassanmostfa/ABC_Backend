<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Area;
use App\Models\Category;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\PermissionCategory;
use App\Models\PermissionItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Setting;
use App\Services\ErpOrderService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResendOrderToErpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.erp.url' => 'https://erp.test',
            'services.erp.username' => 'erp-user',
            'services.erp.password' => 'erp-pass',
        ]);
    }

    public function test_resend_to_erp_succeeds_for_failed_cash_order(): void
    {
        $order = $this->createCashOrder();

        Http::fake([
            'https://erp.test/API/Order/SendOrder' => Http::response(['status' => 1, 'message' => 'OK'], 200),
        ]);

        $result = app(OrderService::class)->resendOrderToErp($order->id);

        $this->assertTrue($result['success']);
        $this->assertTrue($order->fresh()->is_sent_to_erp);
        Http::assertSentCount(1);
    }

    public function test_resend_to_erp_rejects_already_sent_order(): void
    {
        $order = $this->createCashOrder(['is_sent_to_erp' => true]);

        $result = app(ErpOrderService::class)->resendOrder($order);

        $this->assertFalse($result['success']);
        $this->assertSame('Order was already sent to ERP.', $result['message']);
        Http::assertNothingSent();
    }

    public function test_resend_to_erp_keeps_flag_false_when_erp_fails(): void
    {
        $order = $this->createCashOrder();

        Http::fake([
            'https://erp.test/API/Order/SendOrder' => Http::response(['status' => -1, 'message' => 'Duplicate'], 200),
        ]);

        $result = app(OrderService::class)->resendOrderToErp($order->id);

        $this->assertFalse($result['success']);
        $this->assertFalse($order->fresh()->is_sent_to_erp);
    }

    public function test_admin_api_resend_to_erp_endpoint(): void
    {
        $admin = $this->createAdminWithOrdersEditPermission();
        Sanctum::actingAs($admin, [], 'sanctum');

        $order = $this->createCashOrder();

        Http::fake([
            'https://erp.test/API/Order/SendOrder' => Http::response(['status' => 1, 'message' => 'OK'], 200),
        ]);

        $response = $this->postJson("/api/admin/orders/{$order->id}/resend-to-erp");

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.order.is_sent_to_erp', true);
        $this->assertTrue($order->fresh()->is_sent_to_erp);
    }

    protected function createCashOrder(array $overrides = []): Order
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
        $order->update(array_merge(['is_sent_to_erp' => false], $overrides));

        return $order->fresh();
    }

    protected function createAdminWithOrdersEditPermission(): Admin
    {
        $category = PermissionCategory::create([
            'name' => 'Orders Managment',
            'slug' => 'orders_management',
            'sort_order' => 0,
        ]);

        $permissionItem = PermissionItem::create([
            'permission_category_id' => $category->id,
            'name' => 'Orders',
            'slug' => 'orders',
            'sort_order' => 0,
        ]);

        $role = Role::create([
            'name' => 'Test Admin',
            'description' => 'Test role',
            'is_active' => true,
        ]);

        $role->assignPermissions([
            $permissionItem->id => [
                'view' => true,
                'add' => true,
                'edit' => true,
                'delete' => false,
            ],
        ]);

        return Admin::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
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
            'phone' => '96550001001',
            'email' => 'erp-resend@example.com',
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
            'sku' => 'SKU-ERP-001',
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'size' => 'M',
            'sku' => 'SKU-ERP-001-M',
            'short_item' => 'EA',
            'price' => 10.00,
            'quantity' => 100,
            'is_active' => true,
        ]);

        return [$customer, $address, $variant];
    }
}

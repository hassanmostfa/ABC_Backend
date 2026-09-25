<?php

namespace Tests\Feature;

use App\Jobs\DispatchGeneralNotificationJob;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\DeviceToken;
use App\Models\GeneralNotification;
use App\Models\Notification;
use App\Models\Offer;
use App\Models\PermissionCategory;
use App\Models\PermissionItem;
use App\Models\Role;
use App\Services\Notification\FirebaseService;
use App\Services\Notification\GeneralNotificationService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class GeneralNotificationTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, array{token: string, title: string, body: string, data: array<string, mixed>}> */
    private array $pushes = [];

    /** @var array<int, string> tokens the fake FCM reports as unregistered */
    private array $deadTokens = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['notifications.send_to_firebase' => true]);

        $firebase = Mockery::mock(FirebaseService::class);
        $firebase->shouldReceive('sendMessagesConcurrently')
            ->andReturnUsing(function (array $messages) {
                array_push($this->pushes, ...array_values($messages));

                return array_map(fn ($message) => in_array($message['token'], $this->deadTokens, true)
                    ? ['success' => false, 'invalid_token' => true, 'error' => 'UNREGISTERED']
                    : ['success' => true, 'invalid_token' => false, 'error' => null], $messages);
            });
        $this->app->instance(FirebaseService::class, $firebase);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_admin_sends_general_notification_to_all_active_customers(): void
    {
        $english = $this->createCustomer('en@example.com', '96550002001', 'en');
        $arabic = $this->createCustomer('ar@example.com', '96550002002', 'ar');
        $inactive = $this->createCustomer('off@example.com', '96550002003', 'en', false);
        DeviceToken::create(['customer_id' => $english->id, 'token' => 'token-en']);
        DeviceToken::create(['customer_id' => $arabic->id, 'token' => 'token-ar']);
        DeviceToken::create(['customer_id' => $inactive->id, 'token' => 'token-off']);

        Sanctum::actingAs($this->createAdmin(), [], 'sanctum');

        $response = $this->postJson('/api/admin/general-notifications', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'general')
            ->assertJsonPath('data.status', GeneralNotification::STATUS_COMPLETED)
            ->assertJsonPath('data.recipients_count', 2)
            ->assertJsonPath('data.push_sent_count', 2)
            ->assertJsonPath('data.push_failed_count', 0);

        $generalNotificationId = $response->json('data.id');

        $this->assertSame(2, Notification::query()->where('general_notification_id', $generalNotificationId)->count());
        $this->assertDatabaseMissing('notifications', [
            'notifiable_type' => Customer::class,
            'notifiable_id' => $inactive->id,
        ]);

        $pushesByToken = collect($this->pushes)->keyBy('token');
        $this->assertCount(2, $pushesByToken);
        $this->assertSame('Big news', $pushesByToken['token-en']['title']);
        $this->assertSame('خبر كبير', $pushesByToken['token-ar']['title']);
        $this->assertSame('general', $pushesByToken['token-en']['data']['type']);
        $this->assertArrayNotHasKey('offer_id', $pushesByToken['token-en']['data']);
    }

    public function test_offer_notification_carries_offer_id_for_app_navigation(): void
    {
        $customer = $this->createCustomer('offer@example.com', '96550002010', 'ar');
        DeviceToken::create(['customer_id' => $customer->id, 'token' => 'token-offer']);
        $offer = $this->createOffer();

        Sanctum::actingAs($this->createAdmin(), [], 'sanctum');

        $this->postJson('/api/admin/general-notifications', $this->payload([
            'type' => 'offer',
            'offer_id' => $offer->id,
        ]))->assertCreated()
            ->assertJsonPath('data.type', 'offer')
            ->assertJsonPath('data.offer_id', $offer->id)
            ->assertJsonPath('data.offer.title_en', 'Summer offer');

        $this->assertCount(1, $this->pushes);
        $this->assertSame('offer', $this->pushes[0]['data']['type']);
        $this->assertSame($offer->id, $this->pushes[0]['data']['offer_id']);
        $this->assertNotEmpty($this->pushes[0]['data']['notification_id']);

        Sanctum::actingAs($customer, [], 'sanctum');

        $this->getJson('/api/mobile/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'offer')
            ->assertJsonPath('data.0.title', 'خبر كبير')
            ->assertJsonPath('data.0.data.offer_id', $offer->id)
            ->assertJsonPath('unread_count', 1);
    }

    public function test_broadcast_spanning_multiple_chunks_completes_once_all_are_processed(): void
    {
        $total = GeneralNotificationService::CHUNK_SIZE * 2 + 50;
        for ($i = 1; $i <= $total; $i++) {
            $customer = $this->createCustomer("bulk{$i}@example.com", (string) (96560000000 + $i), $i % 2 ? 'en' : 'ar');
            DeviceToken::create(['customer_id' => $customer->id, 'token' => "bulk-token-{$i}"]);
        }

        Sanctum::actingAs($this->createAdmin(), [], 'sanctum');

        $this->postJson('/api/admin/general-notifications', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.status', GeneralNotification::STATUS_COMPLETED)
            ->assertJsonPath('data.total_chunks', 3)
            ->assertJsonPath('data.processed_chunks', 3)
            ->assertJsonPath('data.recipients_count', $total)
            ->assertJsonPath('data.push_sent_count', $total);

        $this->assertSame($total * 2, \App\Models\NotificationTranslation::query()->count());
        $this->assertCount($total, $this->pushes);
    }

    public function test_unregistered_tokens_are_removed_and_counted_as_failed(): void
    {
        $customer = $this->createCustomer('dead@example.com', '96550002040', 'en');
        DeviceToken::create(['customer_id' => $customer->id, 'token' => 'token-alive']);
        DeviceToken::create(['customer_id' => $customer->id, 'token' => 'token-dead']);
        $this->deadTokens = ['token-dead'];

        Sanctum::actingAs($this->createAdmin(), [], 'sanctum');

        $this->postJson('/api/admin/general-notifications', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.recipients_count', 1)
            ->assertJsonPath('data.push_sent_count', 1)
            ->assertJsonPath('data.push_failed_count', 1);

        $this->assertDatabaseHas('device_tokens', ['token' => 'token-alive']);
        $this->assertDatabaseMissing('device_tokens', ['token' => 'token-dead']);
    }

    public function test_jobs_run_on_the_notifications_queue(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->createAdmin(), [], 'sanctum');

        $this->postJson('/api/admin/general-notifications', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.status', GeneralNotification::STATUS_PENDING);

        Queue::assertPushedOn('notifications', DispatchGeneralNotificationJob::class);
    }

    public function test_firebase_concurrent_sender_maps_results_and_detects_dead_tokens(): void
    {
        $history = [];
        $handler = HandlerStack::create(new MockHandler([
            new Response(200, [], '{"name":"projects/test/messages/1"}'),
            new Response(404, [], json_encode(['error' => [
                'status' => 'NOT_FOUND',
                'message' => 'Requested entity was not found.',
                'details' => [['errorCode' => 'UNREGISTERED']],
            ]])),
            new Response(400, [], json_encode(['error' => [
                'status' => 'INVALID_ARGUMENT',
                'message' => 'The registration token is not a valid FCM registration token',
                'details' => [['errorCode' => 'INVALID_ARGUMENT']],
            ]])),
            new Response(500, [], '{"error":{"status":"INTERNAL"}}'),
        ]));
        $handler->push(Middleware::history($history));

        $service = new class(new Client(['handler' => $handler, 'base_uri' => 'https://fcm.test/send'])) extends FirebaseService {
            public function __construct(Client $client)
            {
                $this->client = $client;
            }

            protected function getAccessToken(): string
            {
                return 'test-token';
            }
        };

        $message = fn (string $token) => ['token' => $token, 'title' => 'T', 'body' => 'B', 'data' => ['offer_id' => 5]];
        $results = $service->sendMessagesConcurrently([
            'ok' => $message('t-ok'),
            'gone' => $message('t-gone'),
            'bad' => $message('t-bad'),
            'server' => $message('t-server'),
        ], 1);

        $this->assertCount(4, $history);
        $sentBody = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame('t-ok', $sentBody['message']['token']);
        $this->assertSame('5', $sentBody['message']['data']['offer_id']);

        $this->assertTrue($results['ok']['success']);
        $this->assertTrue($results['gone']['invalid_token']);
        $this->assertTrue($results['bad']['invalid_token']);
        $this->assertFalse($results['server']['success']);
        $this->assertFalse($results['server']['invalid_token']);
    }

    public function test_offer_type_requires_an_active_mobile_offer(): void
    {
        Sanctum::actingAs($this->createAdmin(), [], 'sanctum');

        $this->postJson('/api/admin/general-notifications', $this->payload(['type' => 'offer']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('offer_id');

        $expired = $this->createOffer([
            'offer_start_date' => now()->subDays(10),
            'offer_end_date' => now()->subDay(),
        ]);
        $this->postJson('/api/admin/general-notifications', $this->payload(['type' => 'offer', 'offer_id' => $expired->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('offer_id');

        $subscription = $this->createOffer(['is_subscription' => true]);
        $this->postJson('/api/admin/general-notifications', $this->payload(['type' => 'offer', 'offer_id' => $subscription->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('offer_id');

        $active = $this->createOffer();
        $this->postJson('/api/admin/general-notifications', $this->payload(['type' => 'general', 'offer_id' => $active->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('offer_id');

        $this->assertSame(0, GeneralNotification::query()->count());
    }

    public function test_admin_without_permission_cannot_send(): void
    {
        Sanctum::actingAs($this->createAdmin(canAdd: false), [], 'sanctum');

        $this->postJson('/api/admin/general-notifications', $this->payload())->assertForbidden();
        $this->getJson('/api/admin/general-notifications')->assertOk();
    }

    public function test_redelivering_a_chunk_does_not_duplicate_notifications(): void
    {
        $customer = $this->createCustomer('retry@example.com', '96550002020', 'en');
        DeviceToken::create(['customer_id' => $customer->id, 'token' => 'token-retry']);
        Sanctum::actingAs($this->createAdmin(), [], 'sanctum');

        $id = $this->postJson('/api/admin/general-notifications', $this->payload())->json('data.id');
        $generalNotification = GeneralNotification::findOrFail($id);

        app(GeneralNotificationService::class)->deliverToCustomers($generalNotification, [$customer->id]);

        $this->assertSame(1, Notification::query()->where('general_notification_id', $id)->count());
        $this->assertSame(1, $generalNotification->fresh()->recipients_count);
        $this->assertCount(1, $this->pushes);
    }

    public function test_index_lists_history_with_read_count(): void
    {
        $customer = $this->createCustomer('read@example.com', '96550002030', 'en');
        Sanctum::actingAs($this->createAdmin(), [], 'sanctum');

        $id = $this->postJson('/api/admin/general-notifications', $this->payload())->json('data.id');
        Notification::query()->where('general_notification_id', $id)->first()->markAsRead();

        $this->getJson('/api/admin/general-notifications?type=general')
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.recipients_count', 1)
            ->assertJsonPath('data.0.read_count', 1);

        $this->getJson("/api/admin/general-notifications/{$id}")
            ->assertOk()
            ->assertJsonPath('data.title_ar', 'خبر كبير');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'general',
            'title_en' => 'Big news',
            'title_ar' => 'خبر كبير',
            'message_en' => 'Check out what is new in the app.',
            'message_ar' => 'اكتشف الجديد في التطبيق.',
        ], $overrides);
    }

    private function createCustomer(string $email, string $phone, string $language, bool $active = true): Customer
    {
        return Customer::create([
            'name' => 'Customer ' . $phone,
            'phone' => $phone,
            'email' => $email,
            'is_active' => $active,
            'is_completed' => true,
            'current_language' => $language,
            'points' => 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createOffer(array $overrides = []): Offer
    {
        return Offer::create(array_merge([
            'title_en' => 'Summer offer',
            'title_ar' => 'عرض الصيف',
            'offer_start_date' => now()->subDay(),
            'offer_end_date' => now()->addWeek(),
            'is_active' => true,
            'is_subscription' => false,
            'type' => 'normal',
            'reward_type' => 'products',
        ], $overrides));
    }

    private function createAdmin(bool $canAdd = true): Admin
    {
        $category = PermissionCategory::firstOrCreate(
            ['slug' => 'settings'],
            ['name' => 'Settings Managment', 'sort_order' => 4]
        );

        $item = PermissionItem::firstOrCreate(
            ['slug' => 'general_notifications'],
            ['permission_category_id' => $category->id, 'name' => 'General Notifications', 'sort_order' => 0]
        );

        $role = Role::create([
            'name' => 'Notifier ' . uniqid(),
            'description' => 'Test role',
            'is_active' => true,
        ]);

        $role->assignPermissions([
            $item->id => [
                'view' => true,
                'add' => $canAdd,
                'edit' => false,
                'delete' => false,
            ],
        ]);

        return Admin::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}

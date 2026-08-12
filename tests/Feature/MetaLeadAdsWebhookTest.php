<?php

namespace Tests\Feature;

use App\Jobs\SyncMetaLeadsJob;
use App\Jobs\SyncMetaPageLeadsJob;
use App\Models\MetaWebhookEvent;
use App\Services\Meta\MetaWebhookStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class MetaLeadAdsWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'services.meta.verify_token' => 'valid-token',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $migration = require database_path('migrations/2026_07_27_000000_create_meta_webhook_events_table.php');
        $migration->up();

        Queue::fake();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('meta_webhook_events');

        parent::tearDown();
    }

    public function test_it_returns_the_challenge_when_the_verify_token_is_correct(): void
    {
        $response = $this->get('/api/webhooks/meta/lead-ads?hub.mode=subscribe&hub.verify_token=valid-token&hub.challenge=test-challenge');

        $response->assertOk();
        $response->assertContent('test-challenge');
    }

    public function test_it_keeps_the_forbidden_response_when_the_verify_token_is_incorrect(): void
    {
        $response = $this->get('/api/webhooks/meta/lead-ads?hub.mode=subscribe&hub.verify_token=wrong-token&hub.challenge=test-challenge');

        $response->assertForbidden();
        $response->assertContent('');
    }

    public function test_it_stores_a_webhook_post_without_changing_the_existing_response(): void
    {
        $payload = $this->leadgenPayload();

        $response = $this->postJson('/api/webhooks/meta/lead-ads', $payload, [
            'Authorization' => 'Bearer secret-token',
            'Content-Type' => 'application/json',
            'Cookie' => 'session=secret',
            'User-Agent' => 'Meta-Test-Agent',
            'X-Hub-Signature-256' => 'sha256=fake',
            'X-Request-Id' => 'request-123',
        ]);

        /**
         * el response de Meta Webhook debe ser siempre 200 OK con un JSON {"received": true} para que Meta considere que el webhook fue recibido correctamente, independientemente de si hubo errores internos al almacenar el evento en la base de datos.
         */
        $response->assertOk();
        $response->assertExactJson(['received' => true]);
        Queue::assertPushed(SyncMetaPageLeadsJob::class, function (SyncMetaPageLeadsJob $job): bool {
            return $job->metaPageId === 'page-123'
                && $job->metaEventTime === '1710000000';
        });
        Queue::assertNotPushed(SyncMetaLeadsJob::class);

        $event = MetaWebhookEvent::query()->firstOrFail();

        $this->assertSame('page', $event->product);
        $this->assertSame('page', $event->object);
        $this->assertSame('leadgen', $event->field);
        $this->assertSame('page-entry-123', $event->entry_id);
        $this->assertSame('page-123', $event->page_id);
        $this->assertSame('lead-123', $event->leadgen_id);
        $this->assertSame('form-123', $event->form_id);
        $this->assertSame('ad-123', $event->ad_id);
        $this->assertSame($payload['entry'][0]['changes'][0]['value'], $event->value);
        $this->assertSame($payload, $event->payload);
        $this->assertNotNull($event->event_uuid);
        $this->assertNotNull($event->event_hash);
        $this->assertNotNull($event->received_at);
        $this->assertSame('received', $event->processing_status);
        $this->assertArrayHasKey('content-type', $event->request_headers);
        $this->assertArrayHasKey('user-agent', $event->request_headers);
        $this->assertArrayHasKey('x-hub-signature-256', $event->request_headers);
        $this->assertArrayHasKey('x-request-id', $event->request_headers);
        $this->assertArrayNotHasKey('authorization', $event->request_headers);
        $this->assertArrayNotHasKey('cookie', $event->request_headers);
    }

    public function test_it_creates_one_record_for_every_change_in_every_entry(): void
    {
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => 'waba-1',
                    'time' => 1710000001,
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'sender' => ['id' => 'sender-1'],
                                'recipient' => ['id' => 'recipient-1'],
                            ],
                        ],
                        [
                            'field' => 'message_template_status_update',
                            'value' => ['message_template_id' => 'template-1'],
                        ],
                    ],
                ],
                [
                    'id' => 'waba-2',
                    'time' => 1710000002,
                    'changes' => [
                        [
                            'field' => 'account_update',
                            'value' => ['account_id' => 'account-1'],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson('/api/webhooks/meta/lead-ads', $payload)->assertOk();

        Queue::assertPushed(SyncMetaLeadsJob::class);
        Queue::assertNotPushed(SyncMetaPageLeadsJob::class);

        $this->assertSame(3, MetaWebhookEvent::query()->count());
        $this->assertSame(2, MetaWebhookEvent::query()->where('entry_id', 'waba-1')->count());
        $this->assertSame(1, MetaWebhookEvent::query()->where('entry_id', 'waba-2')->count());

        $messageEvent = MetaWebhookEvent::query()->where('field', 'messages')->firstOrFail();

        $this->assertSame('sender-1', $messageEvent->sender_id);
        $this->assertSame('recipient-1', $messageEvent->recipient_id);
    }

    public function test_it_stores_a_payload_without_entry_or_changes(): void
    {
        $payload = [
            'product' => 'application',
            'object' => 'app',
            'app_id' => 'app-123',
            'unexpected' => ['nested' => true],
        ];

        $this->postJson('/api/webhooks/meta/lead-ads', $payload)->assertOk();

        $event = MetaWebhookEvent::query()->firstOrFail();

        $this->assertSame('application', $event->product);
        $this->assertSame('app', $event->object);
        $this->assertSame('app-123', $event->app_id);
        $this->assertNull($event->field);
        $this->assertNull($event->value);
        $this->assertSame($payload, $event->payload);
    }

    public function test_it_stores_a_payload_with_an_unknown_structure(): void
    {
        $payload = [
            'entry' => [
                [
                    'id' => 'unknown-entry',
                    'unknown_changes' => [
                        'anything' => true,
                    ],
                ],
            ],
        ];

        $this->postJson('/api/webhooks/meta/lead-ads', $payload)->assertOk();

        $event = MetaWebhookEvent::query()->firstOrFail();

        $this->assertSame('unknown', $event->product);
        $this->assertSame('unknown-entry', $event->entry_id);
        $this->assertNull($event->field);
        $this->assertSame($payload, $event->payload);
    }

    public function test_it_does_not_duplicate_a_repeated_payload(): void
    {
        $payload = $this->leadgenPayload();

        $this->postJson('/api/webhooks/meta/lead-ads', $payload)->assertOk();
        $this->postJson('/api/webhooks/meta/lead-ads', $payload)->assertOk();

        $this->assertSame(1, MetaWebhookEvent::query()->count());
    }

    public function test_a_database_error_does_not_affect_the_expected_meta_response(): void
    {
        $this->app->instance(MetaWebhookStorageService::class, new class extends MetaWebhookStorageService
        {
            public function storeFromRequest(Request $request): void
            {
                throw new RuntimeException('database unavailable');
            }
        });

        $response = $this->postJson('/api/webhooks/meta/lead-ads', $this->leadgenPayload());

        $response->assertOk();
        $response->assertExactJson(['received' => true]);
        Queue::assertPushed(SyncMetaPageLeadsJob::class);
        Queue::assertNotPushed(SyncMetaLeadsJob::class);
    }

    private function leadgenPayload(): array
    {
        return [
            'object' => 'page',
            'entry' => [
                [
                    'id' => 'page-entry-123',
                    'time' => 1710000000,
                    'changes' => [
                        [
                            'field' => 'leadgen',
                            'value' => [
                                'leadgen_id' => 'lead-123',
                                'form_id' => 'form-123',
                                'page_id' => 'page-123',
                                'ad_id' => 'ad-123',
                                'adset_id' => 'adset-123',
                                'campaign_id' => 'campaign-123',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}

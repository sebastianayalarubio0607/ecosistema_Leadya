<?php

namespace Tests\Feature;

use App\Http\Services\Meta\MetaLeadAdsSyncService;
use App\Http\Services\Meta\MetaWhatsappReferralLeadService;
use App\Jobs\ProcessLeadIntegrationsJob;
use App\Jobs\ProcessMetaWhatsappReferralLeadJob;
use App\Jobs\SendLeadToFacebook;
use App\Jobs\SyncMetaAssetStatusesForCustomerJob;
use App\Jobs\SyncMetaLeadsJob;
use App\Jobs\SyncMetaPageLeadsJob;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\MetaAd;
use App\Models\MetaAdAccount;
use App\Models\MetaAdInsight;
use App\Models\MetaAdSet;
use App\Models\MetaCampaign;
use App\Models\MetaPage;
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

        foreach ([
            '2025_04_25_210705_create_customers_table.php',
            '2026_03_18_090100_create_meta_pages_table.php',
            '2026_01_27_000001_create_meta_ad_accounts_table.php',
            '2026_01_27_000006_add_customer_id_to_meta_ad_accounts_table.php',
            '2026_09_01_000000_create_customer_meta_ad_account_table.php',
            '2026_07_27_000000_create_meta_webhook_events_table.php',
            '2026_08_21_000002_ensure_referral_on_meta_webhook_events_table.php',
        ] as $migrationPath) {
            $migration = require database_path('migrations/'.$migrationPath);
            $migration->up();
        }

        Queue::fake();
    }

    protected function tearDown(): void
    {
        foreach ([
            'lead_funnel_histories',
            'funnels',
            'leads',
            'meta_ad_insights',
            'meta_ads',
            'meta_ad_sets',
            'meta_campaigns',
            'meta_forms',
            'meta_pages',
            'integrations',
            'integrationtypes',
            'crm_state',
            'qualification',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::dropIfExists('meta_webhook_events');
        Schema::dropIfExists('customer_meta_ad_account');
        Schema::dropIfExists('meta_ad_accounts');
        Schema::dropIfExists('customers');

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

    public function test_page_leadgen_job_syncs_forms_before_syncing_page_leads(): void
    {
        $page = MetaPage::withoutEvents(fn () => MetaPage::query()->create([
            'customer_id' => null,
            'meta_page_id' => 'page-123',
            'name' => 'Lead Page',
            'page_access_token' => 'page-token',
            'status' => true,
        ]));

        $service = \Mockery::mock(MetaLeadAdsSyncService::class);
        $service->shouldReceive('syncForms')
            ->once()
            ->ordered()
            ->with(\Mockery::on(fn ($givenPage): bool => $givenPage instanceof MetaPage && $givenPage->is($page)))
            ->andReturn(['pages_processed' => 1, 'forms_created' => 0, 'forms_updated' => 1]);
        $service->shouldReceive('syncLeadsForPage')
            ->once()
            ->ordered()
            ->withArgs(function ($givenPage, $from, $to) use ($page): bool {
                return $givenPage instanceof MetaPage
                    && $givenPage->is($page)
                    && $from instanceof \Carbon\Carbon
                    && $to instanceof \Carbon\Carbon
                    && $from->timestamp === 1710000000 - (15 * 60);
            })
            ->andReturn([
                'forms_processed' => 1,
                'leads_created' => 1,
                'leads_updated' => 0,
                'from' => '2024-03-09 15:45:00',
                'to' => '2024-03-09 16:00:00',
            ]);

        (new SyncMetaPageLeadsJob('page-123', '1710000000'))->handle($service);
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

        Queue::assertNotPushed(SyncMetaLeadsJob::class);
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

    public function test_it_dispatches_customer_scoped_asset_status_job_for_ad_account_status_webhook(): void
    {
        $customer = Customer::query()->create([
            'name' => 'Cliente Meta Estado',
            'status' => true,
        ]);

        MetaAdAccount::withoutEvents(function () use ($customer): void {
            MetaAdAccount::query()->create([
                'customer_id' => $customer->id,
                'meta_account_id' => 'act_123456789',
                'name' => 'Cuenta Meta',
                'status' => 'active',
            ]);
        });

        $payload = [
            'object' => 'ad_account',
            'entry' => [
                [
                    'id' => 'act_123456789',
                    'time' => 1710000000,
                    'changes' => [
                        [
                            'field' => 'with_issues_ad_objects',
                            'value' => [
                                'account_id' => '123456789',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson('/api/webhooks/meta/lead-ad', $payload)->assertOk();

        Queue::assertPushed(SyncMetaAssetStatusesForCustomerJob::class, function (SyncMetaAssetStatusesForCustomerJob $job) use ($customer): bool {
            return $job->customerId === $customer->id
                && $job->queryType === 'webhook'
                && $job->metaWebhookEventId !== null;
        });
        Queue::assertNotPushed(SyncMetaLeadsJob::class);

        $event = MetaWebhookEvent::query()->firstOrFail();

        $this->assertSame('ad_account', $event->object);
        $this->assertSame('with_issues_ad_objects', $event->field);
        $this->assertSame('123456789', $event->account_id);
    }

    public function test_it_uses_ad_account_entry_id_when_asset_status_payload_has_no_account_id(): void
    {
        $customer = Customer::query()->create([
            'name' => 'Cliente Meta Entry',
            'status' => true,
        ]);

        MetaAdAccount::withoutEvents(function () use ($customer): void {
            MetaAdAccount::query()->create([
                'customer_id' => $customer->id,
                'meta_account_id' => '893247768830003',
                'name' => 'Cuenta Meta Entry',
                'status' => 'active',
            ]);
        });

        $payload = [
            'object' => 'ad_account',
            'entry' => [
                [
                    'id' => '893247768830003',
                    'time' => 1787245925,
                    'changes' => [
                        [
                            'field' => 'in_process_ad_objects',
                            'value' => [
                                'id' => '120252436486500589',
                                'level' => 'AD',
                                'status_name' => 'PENDING_REVIEW',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson('/api/webhooks/meta/lead-ad', $payload)->assertOk();

        Queue::assertPushed(SyncMetaAssetStatusesForCustomerJob::class, function (SyncMetaAssetStatusesForCustomerJob $job) use ($customer): bool {
            return $job->customerId === $customer->id
                && $job->queryType === 'webhook'
                && $job->metaWebhookEventId !== null;
        });
        Queue::assertNotPushed(SyncMetaLeadsJob::class);

        $event = MetaWebhookEvent::query()->firstOrFail();

        $this->assertSame('ad_account', $event->object);
        $this->assertSame('in_process_ad_objects', $event->field);
        $this->assertSame('893247768830003', $event->entry_id);
        $this->assertSame('893247768830003', $event->account_id);
        $this->assertSame('120252436486500589', data_get($event->value, 'id'));
    }

    public function test_it_dispatches_a_meta_queue_job_for_whatsapp_referral_messages(): void
    {
        $payload = $this->whatsappReferralPayloadWithPhone();

        $response = $this->postJson('/api/webhooks/meta/lead-ad', $payload);

        $response->assertOk();
        $response->assertExactJson(['received' => true]);

        Queue::assertPushed(ProcessMetaWhatsappReferralLeadJob::class, function (ProcessMetaWhatsappReferralLeadJob $job) use ($payload): bool {
            return $job->queue === 'meta'
                && data_get($job->payload, 'object') === 'whatsapp_business_account'
                && data_get($job->payload, 'entry.0.changes.0.value.messages.0.referral.ctwa_clid') === data_get($payload, 'entry.0.changes.0.value.messages.0.referral.ctwa_clid');
        });
        Queue::assertNotPushed(SyncMetaLeadsJob::class);

        $event = MetaWebhookEvent::query()->firstOrFail();

        $this->assertSame('whatsapp_business_account', $event->object);
        $this->assertSame('messages', $event->field);
        $this->assertSame(data_get($payload, 'entry.0.changes.0.value.messages.0.referral'), $event->referral);
    }

    public function test_meta_whatsapp_referral_job_creates_a_lead_and_only_dispatches_meta_conversion(): void
    {
        $this->migrateLeadCreationTables();
        $customer = $this->createMetaAdInsightCustomer('120249117232250350', '893247768830003');
        $payload = $this->whatsappReferralPayloadWithPhone();

        $job = new ProcessMetaWhatsappReferralLeadJob($payload);

        $this->assertSame('meta', $job->queue);

        $job->handle(app(MetaWhatsappReferralLeadService::class));

        $lead = Lead::query()->firstOrFail();

        $this->assertSame($customer->id, $lead->customer_id);
        $this->assertNull($lead->integration_id);
        $this->assertSame('Gomelo', $lead->name);
        $this->assertSame('Whastaapp', $lead->last_name);
        $this->assertSame('573504230377', $lead->phone);
        $this->assertTrue((bool) $lead->tc);
        $this->assertSame("whatsapp - campa\u{00F1}a", $lead->effective_lead);
        $this->assertSame('https://www.facebook.com/story.php?story_fbid=1690191008936685&id=100064511387170', $lead->page_url);
        $this->assertSame('whatsapp', $lead->campaign_origin);
        $this->assertSame('video', $lead->plataforma);
        $this->assertSame('120249117232250350', $lead->meta_id_ad);
        $this->assertSame('meta', $lead->gad_source);
        $this->assertSame(data_get($payload, 'entry.0.changes.0.value.messages.0.referral'), $lead->meta_payload);
        $this->assertSame('CO.3992682174198619', $lead->whasapp_user_id);
        $this->assertSame('ctwa-phone-123', $lead->ctwa_clid);
        $this->assertSame('623611940536083', $lead->whatsapp_business_account_id);
        $this->assertSame('573206374059', $lead->number_whatsApp_companies);
        $this->assertNull($lead->WhatsApp_username);

        Queue::assertPushed(SendLeadToFacebook::class, function (SendLeadToFacebook $conversionJob) use ($lead, $customer): bool {
            return $conversionJob->leadId === $lead->id
                && $conversionJob->customerId === $customer->id
                && $conversionJob->eventNameOverride === 'Lead';
        });
        Queue::assertNotPushed(ProcessLeadIntegrationsJob::class);

        $job->handle(app(MetaWhatsappReferralLeadService::class));
        $this->assertSame(1, Lead::query()->count());
    }

    public function test_meta_whatsapp_referral_job_uses_default_customer_when_ad_account_is_shared(): void
    {
        $this->migrateLeadCreationTables();

        $legacyCustomer = Customer::query()->create([
            'name' => 'Cliente Legacy',
            'status' => true,
        ]);
        $defaultCustomer = Customer::query()->create([
            'name' => 'Cliente Default WhatsApp',
            'status' => true,
        ]);

        $account = MetaAdAccount::withoutEvents(fn () => MetaAdAccount::query()->create([
            'customer_id' => $legacyCustomer->id,
            'meta_account_id' => '893247768830003',
            'name' => 'Cuenta Compartida WhatsApp',
            'status' => 'active',
        ]));
        $account->syncCustomersWithWhatsappDefault([$legacyCustomer->id, $defaultCustomer->id], $defaultCustomer->id);

        $this->createMetaAdInsightForAccount($account, '120249117232250350', '893247768830003');

        app(MetaWhatsappReferralLeadService::class)->processPayload($this->whatsappReferralPayloadWithPhone());

        $lead = Lead::query()->firstOrFail();

        $this->assertSame($defaultCustomer->id, $lead->customer_id);
        $this->assertSame(
            $defaultCustomer->id,
            DB::table('customer_meta_ad_account')
                ->where('meta_ad_account_id', $account->id)
                ->where('is_default_for_whatsapp_leads', true)
                ->value('customer_id')
        );
    }

    public function test_meta_whatsapp_referral_job_falls_back_to_oldest_related_customer_without_default(): void
    {
        $this->migrateLeadCreationTables();

        $oldestCustomer = Customer::query()->create([
            'name' => 'Cliente Relacion Mas Antigua',
            'status' => true,
        ]);
        $newestCustomer = Customer::query()->create([
            'name' => 'Cliente Relacion Nueva',
            'status' => true,
        ]);

        $account = MetaAdAccount::withoutEvents(fn () => MetaAdAccount::query()->create([
            'customer_id' => null,
            'meta_account_id' => '123456789',
            'name' => 'Cuenta Sin Default',
            'status' => 'active',
        ]));

        DB::table('customer_meta_ad_account')->insert([
            [
                'customer_id' => $oldestCustomer->id,
                'meta_ad_account_id' => $account->id,
                'is_default_for_whatsapp_leads' => false,
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ],
            [
                'customer_id' => $newestCustomer->id,
                'meta_ad_account_id' => $account->id,
                'is_default_for_whatsapp_leads' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->createMetaAdInsightForAccount($account, '120252403258930589', '123456789');

        app(MetaWhatsappReferralLeadService::class)->processPayload($this->whatsappReferralPayloadWithoutContactPhone());

        $lead = Lead::query()->firstOrFail();

        $this->assertSame($oldestCustomer->id, $lead->customer_id);
        $this->assertSame(
            $oldestCustomer->id,
            DB::table('customer_meta_ad_account')
                ->where('meta_ad_account_id', $account->id)
                ->where('is_default_for_whatsapp_leads', true)
                ->value('customer_id')
        );
    }

    public function test_meta_whatsapp_referral_job_extracts_phone_from_text_when_contact_has_no_wa_id(): void
    {
        $this->migrateLeadCreationTables();
        $customer = $this->createMetaAdInsightCustomer('120252403258930589', '123456789');
        $payload = $this->whatsappReferralPayloadWithoutContactPhone();

        app(MetaWhatsappReferralLeadService::class)->processPayload($payload);

        $lead = Lead::query()->firstOrFail();

        $this->assertSame($customer->id, $lead->customer_id);
        $this->assertSame('Diana', $lead->name);
        $this->assertSame('+573148126114', $lead->phone);
        $this->assertSame('CO.28095355356773302', $lead->whasapp_user_id);
        $this->assertSame('ctwa-name-456', $lead->ctwa_clid);
        $this->assertSame('546588055206122', $lead->whatsapp_business_account_id);
        $this->assertSame('573216388040', $lead->number_whatsApp_companies);
        $this->assertSame('mozura20', $lead->WhatsApp_username);
        Queue::assertNotPushed(ProcessLeadIntegrationsJob::class);
    }

    public function test_meta_whatsapp_referral_job_can_resolve_customer_from_existing_lead_when_insight_is_missing(): void
    {
        $this->migrateLeadCreationTables();
        $customer = Customer::query()->create([
            'name' => 'Cliente Lead Existente',
            'status' => true,
        ]);
        Lead::query()->create([
            'customer_id' => $customer->id,
            'name' => 'Lead anterior',
            'phone' => '+570000000000',
            'meta_id_ad' => '120252403258930589',
            'campaign_origin' => 'meta',
        ]);
        $payload = $this->whatsappReferralPayloadWithoutContactPhone();

        app(MetaWhatsappReferralLeadService::class)->processPayload($payload);

        $lead = Lead::query()->whereNotNull('ctwa_clid')->firstOrFail();

        $this->assertSame($customer->id, $lead->customer_id);
        $this->assertSame('120252403258930589', $lead->meta_id_ad);
        $this->assertSame('ctwa-name-456', $lead->ctwa_clid);
        $this->assertSame('whatsapp', $lead->campaign_origin);
        $this->assertSame('meta', $lead->gad_source);
        $this->assertSame(2, Lead::query()->count());
        Queue::assertPushed(SendLeadToFacebook::class);
        Queue::assertNotPushed(ProcessLeadIntegrationsJob::class);
    }

    public function test_meta_whatsapp_referral_job_can_resolve_customer_from_existing_whatsapp_lead_when_source_is_new(): void
    {
        $this->migrateLeadCreationTables();
        $customer = Customer::query()->create([
            'name' => 'Cliente WhatsApp Existente',
            'status' => true,
        ]);
        Lead::query()->create([
            'customer_id' => $customer->id,
            'name' => 'Lead WhatsApp anterior',
            'phone' => '+573148126114',
            'meta_id_ad' => 'old-source-id',
            'whatsapp_business_account_id' => '546588055206122',
            'number_whatsApp_companies' => '573216388040',
            'campaign_origin' => 'whatsapp',
        ]);
        $payload = $this->whatsappReferralPayloadWithoutContactPhone();
        data_set($payload, 'entry.0.changes.0.value.messages.0.referral.source_id', '120252402180310589');
        data_set($payload, 'entry.0.changes.0.value.messages.0.referral.ctwa_clid', 'ctwa-new-source-789');

        app(MetaWhatsappReferralLeadService::class)->processPayload($payload);

        $lead = Lead::query()->where('ctwa_clid', 'ctwa-new-source-789')->firstOrFail();

        $this->assertSame($customer->id, $lead->customer_id);
        $this->assertSame('120252402180310589', $lead->meta_id_ad);
        $this->assertSame('546588055206122', $lead->whatsapp_business_account_id);
        $this->assertSame('573216388040', $lead->number_whatsApp_companies);
        $this->assertSame('whatsapp', $lead->campaign_origin);
        $this->assertSame('meta', $lead->gad_source);
        Queue::assertPushed(SendLeadToFacebook::class);
        Queue::assertNotPushed(ProcessLeadIntegrationsJob::class);
    }

    public function test_meta_whatsapp_referral_job_creates_phone_contact_lead_from_existing_whatsapp_customer(): void
    {
        $this->migrateLeadCreationTables();
        $customer = Customer::query()->create([
            'name' => 'Cliente WhatsApp Telefono',
            'status' => true,
        ]);
        Lead::query()->create([
            'customer_id' => $customer->id,
            'name' => 'Lead WhatsApp anterior',
            'phone' => '+573148126114',
            'meta_id_ad' => 'old-source-id',
            'whatsapp_business_account_id' => '546588055206122',
            'number_whatsApp_companies' => '573216388040',
            'campaign_origin' => 'whatsapp',
        ]);
        $payload = $this->whatsappReferralPayloadWithPhoneContactNewSource();

        app(MetaWhatsappReferralLeadService::class)->processPayload($payload);

        $lead = Lead::query()->where('ctwa_clid', 'ctwa-brandon-789')->firstOrFail();

        $this->assertSame($customer->id, $lead->customer_id);
        $this->assertSame('Brandon Gonzalez', $lead->name);
        $this->assertSame('573217825312', $lead->phone);
        $this->assertSame('CO.2292534954483843', $lead->whasapp_user_id);
        $this->assertSame('120252320050420589', $lead->meta_id_ad);
        $this->assertSame('546588055206122', $lead->whatsapp_business_account_id);
        $this->assertSame('573216388040', $lead->number_whatsApp_companies);
        $this->assertSame('https://www.instagram.com/p/DcCMs6gs3rT/', $lead->page_url);
        $this->assertSame('image', $lead->plataforma);
        $this->assertSame('whatsapp', $lead->campaign_origin);
        $this->assertSame('meta', $lead->gad_source);
        Queue::assertPushed(SendLeadToFacebook::class);
        Queue::assertNotPushed(ProcessLeadIntegrationsJob::class);
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

    private function migrateLeadCreationTables(): void
    {
        foreach ([
            '2025_04_25_205118_create_integrationtypes_table.php',
            '2025_04_25_210715_create_integrations_table.php',
            '2025_04_25_210757_create_leads_table.php',
            '2026_01_09_090804_create_qualification_table.php',
            '2026_01_09_090958_create_crm_state_table.php',
            '2026_01_09_091030_add_crm_fields_to_leads_table.php',
            '2026_01_30_000000_create_funnels_table.php',
            '2026_01_30_000001_add_funnel_id_to_qualification_table.php',
            '2026_02_23_131726_create_lead_funnel_histories_table.php.php',
            '2026_02_10_094014_add_meta_fields_and_resize_page_url_agent_in_leads_table.php',
            '2026_02_10_094954_add_meta_fields_and_resize_page_url_agent_in_leads_table2.php',
            '2026_03_18_090100_create_meta_pages_table.php',
            '2026_03_18_090200_create_meta_forms_table.php',
            '2026_03_18_090400_add_meta_lead_tracking_to_leads_table.php',
            '2026_04_27_170000_add_google_tracking_fields_to_leads_table.php',
            '2026_05_04_000200_add_google_click_tracking_fields_to_leads_table.php',
            '2026_08_21_000000_add_whatsapp_referral_fields_to_leads_table.php',
            '2026_01_27_000002_create_meta_campaigns_table.php',
            '2026_01_27_000003_create_meta_ad_sets_table.php',
            '2026_01_27_000004_create_meta_ads_table.php',
            '2026_01_27_000005_create_meta_ad_insights_table.php',
        ] as $migrationPath) {
            if ($this->migrationAlreadyApplied($migrationPath)) {
                continue;
            }

            $migration = require database_path('migrations/'.$migrationPath);
            $migration->up();
        }
    }

    private function migrationAlreadyApplied(string $migrationPath): bool
    {
        return match ($migrationPath) {
            '2025_04_25_205118_create_integrationtypes_table.php' => Schema::hasTable('integrationtypes'),
            '2025_04_25_210715_create_integrations_table.php' => Schema::hasTable('integrations'),
            '2025_04_25_210757_create_leads_table.php' => Schema::hasTable('leads'),
            '2026_01_09_090804_create_qualification_table.php' => Schema::hasTable('qualification'),
            '2026_01_09_090958_create_crm_state_table.php' => Schema::hasTable('crm_state'),
            '2026_01_30_000000_create_funnels_table.php' => Schema::hasTable('funnels'),
            '2026_02_23_131726_create_lead_funnel_histories_table.php.php' => Schema::hasTable('lead_funnel_histories'),
            '2026_03_18_090100_create_meta_pages_table.php' => Schema::hasTable('meta_pages'),
            '2026_03_18_090200_create_meta_forms_table.php' => Schema::hasTable('meta_forms'),
            '2026_01_27_000002_create_meta_campaigns_table.php' => Schema::hasTable('meta_campaigns'),
            '2026_01_27_000003_create_meta_ad_sets_table.php' => Schema::hasTable('meta_ad_sets'),
            '2026_01_27_000004_create_meta_ads_table.php' => Schema::hasTable('meta_ads'),
            '2026_01_27_000005_create_meta_ad_insights_table.php' => Schema::hasTable('meta_ad_insights'),
            default => false,
        };
    }

    private function createMetaAdInsightCustomer(string $sourceId, string $accountId): Customer
    {
        $customer = Customer::query()->create([
            'name' => 'Cliente WhatsApp '.$sourceId,
            'status' => true,
        ]);

        $account = MetaAdAccount::withoutEvents(fn () => MetaAdAccount::query()->create([
            'customer_id' => $customer->id,
            'meta_account_id' => 'act_'.$accountId,
            'name' => 'Cuenta WhatsApp',
            'status' => 'active',
        ]));

        $account->syncCustomersWithWhatsappDefault([$customer->id], $customer->id);
        $account->forceFill(['customer_id' => $customer->id])->saveQuietly();

        $this->createMetaAdInsightForAccount($account, $sourceId, $accountId);

        return $customer;
    }

    private function createMetaAdInsightForAccount(MetaAdAccount $account, string $sourceId, string $accountId): void
    {
        $campaign = MetaCampaign::query()->create([
            'meta_ad_account_id' => $account->id,
            'meta_campaign_id' => 'campaign-'.$sourceId,
            'name' => 'Campaign '.$sourceId,
            'status' => 'active',
        ]);

        $adSet = MetaAdSet::query()->create([
            'meta_campaign_id' => $campaign->id,
            'meta_ad_set_id' => 'adset-'.$sourceId,
            'name' => 'Ad Set '.$sourceId,
            'status' => 'active',
        ]);

        $ad = MetaAd::query()->create([
            'meta_ad_set_id' => $adSet->id,
            'meta_ad_id' => $sourceId,
            'name' => 'Ad '.$sourceId,
            'status' => 'active',
        ]);

        MetaAdInsight::query()->create([
            'meta_ad_id' => $ad->id,
            'account_id' => $accountId,
            'campaign_id' => 'campaign-'.$sourceId,
            'adset_id' => 'adset-'.$sourceId,
            'ad_id' => $sourceId,
            'date_start' => '2026-08-21',
            'date_stop' => '2026-08-21',
            'status' => 'active',
        ]);
    }

    private function whatsappReferralPayloadWithPhone(): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '623611940536083',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'contacts' => [
                                    [
                                        'wa_id' => '573504230377',
                                        'profile' => ['name' => 'Gomelo'],
                                        'user_id' => 'CO.3992682174198619',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'id' => 'wamid.phone',
                                        'from' => '573504230377',
                                        'text' => ['body' => 'Hola, me interesa el Volvo EC40.'],
                                        'type' => 'text',
                                        'referral' => [
                                            'body' => 'Texto del anuncio',
                                            'headline' => 'Agenda tu Test Drive',
                                            'ctwa_clid' => 'ctwa-phone-123',
                                            'source_id' => '120249117232250350',
                                            'video_url' => 'https://www.facebook.com/story.php?story_fbid=1690191008936685&id=100064511387170',
                                            'media_type' => 'video',
                                            'source_url' => 'https://fb.me/4Sn6jNKb5',
                                            'source_type' => 'ad',
                                        ],
                                        'timestamp' => '1787323317',
                                        'from_user_id' => 'CO.3992682174198619',
                                    ],
                                ],
                                'metadata' => [
                                    'phone_number_id' => '564661453400018',
                                    'display_phone_number' => '573206374059',
                                ],
                                'messaging_product' => 'whatsapp',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function whatsappReferralPayloadWithoutContactPhone(): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '546588055206122',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'contacts' => [
                                    [
                                        'profile' => [
                                            'name' => 'Diana',
                                            'username' => 'mozura20',
                                        ],
                                        'user_id' => 'CO.28095355356773302',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'id' => 'wamid.name',
                                        'text' => [
                                            'body' => "Hola\n\nEmail: mozura20@gmail.com\nFull name: Diana Maria\nPhone number: +573148126114\nReferencia: T-Cross",
                                        ],
                                        'type' => 'text',
                                        'referral' => [
                                            'body' => 'Texto del anuncio Volkswagen',
                                            'headline' => 'Massy Motors Volkswagen',
                                            'ctwa_clid' => 'ctwa-name-456',
                                            'source_id' => '120252403258930589',
                                            'video_url' => 'https://www.facebook.com/reel/1500202618533538/',
                                            'media_type' => 'video',
                                            'source_url' => 'https://www.instagram.com/p/DcMkqNus7XH/',
                                            'source_type' => 'ad',
                                        ],
                                        'timestamp' => '1787323544',
                                        'from_user_id' => 'CO.28095355356773302',
                                    ],
                                ],
                                'metadata' => [
                                    'phone_number_id' => '543802255490035',
                                    'display_phone_number' => '573216388040',
                                ],
                                'messaging_product' => 'whatsapp',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function whatsappReferralPayloadWithPhoneContactNewSource(): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '546588055206122',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'contacts' => [
                                    [
                                        'wa_id' => '573217825312',
                                        'profile' => ['name' => 'Brandon Gonzalez'],
                                        'user_id' => 'CO.2292534954483843',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'id' => 'wamid.brandon',
                                        'from' => '573217825312',
                                        'text' => [
                                            'body' => 'Quiero cotizar el Polo Track y conocer las opciones de financiacion.',
                                        ],
                                        'type' => 'text',
                                        'referral' => [
                                            'body' => 'Estrena con poliza por un ano.',
                                            'headline' => 'Estrena con poliza por un ano.',
                                            'ctwa_clid' => 'ctwa-brandon-789',
                                            'image_url' => 'https://instagram.feoh3-1.fna.fbcdn.net/v/t45.1600-4/example.jpg',
                                            'source_id' => '120252320050420589',
                                            'media_type' => 'image',
                                            'source_url' => 'https://www.instagram.com/p/DcCMs6gs3rT/',
                                            'source_type' => 'ad',
                                        ],
                                        'timestamp' => '1787309895',
                                        'from_user_id' => 'CO.2292534954483843',
                                    ],
                                ],
                                'metadata' => [
                                    'phone_number_id' => '543802255490035',
                                    'display_phone_number' => '573216388040',
                                ],
                                'messaging_product' => 'whatsapp',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}

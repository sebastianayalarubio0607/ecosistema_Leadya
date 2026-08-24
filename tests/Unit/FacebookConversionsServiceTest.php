<?php

use App\Http\Services\Convention\FacebookConversionsService;
use App\Models\CrmState;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\WhatsAppEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

it('uses whatsapp dataset credentials for whatsapp leads when enabled', function () {
    $customer = new Customer([
        'fb_pixel_id' => '111111111111111',
        'fb_access_token' => 'base-token',
        'Meta_whatsapp_dataset' => true,
        'Meta_whatsapp_dataset_id' => '222222222222222',
        'Meta_whatsapp_dataset_token' => 'whatsapp-token',
    ]);

    $lead = new Lead([
        'campaign_origin' => 'whatsapp',
        'ctwa_clid' => 'ctwa-test-123',
    ]);

    $method = new ReflectionMethod(FacebookConversionsService::class, 'resolveMetaCredentials');
    $method->setAccessible(true);

    expect($method->invoke(app(FacebookConversionsService::class), $lead, $customer))
        ->toBe(['222222222222222', 'whatsapp-token']);
});

it('builds a business messaging payload for whatsapp dataset conversions', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['events_received' => 1], 200),
    ]);

    $customer = null;
    $lead = null;

    try {
        $customer = Customer::factory()->create([
            'fb_pixel_id' => '111111111111111',
            'fb_access_token' => 'base-token',
            'Meta_whatsapp_dataset' => true,
            'Meta_whatsapp_dataset_id' => '222222222222222',
            'Meta_whatsapp_dataset_token' => 'whatsapp-token',
        ]);

        $lead = Lead::create([
            'customer_id' => $customer->id,
            'name' => 'Juancho',
            'last_name' => 'Whatsapp',
            'phone' => '573001112233',
            'campaign_origin' => 'whatsapp',
            'ctwa_clid' => 'ctwa-test-123',
            'whatsapp_business_account_id' => '546588055206122',
            'value' => 250000,
        ]);

        $result = app(FacebookConversionsService::class)->sendLeadEvent($lead, $customer->id, 'Purchase');
        $event = data_get($result, 'request.data.0');

        expect($result['ok'])->toBeTrue()
            ->and($result['pixel_id'])->toBe('222222222222222')
            ->and($event['action_source'])->toBe('business_messaging')
            ->and($event['messaging_channel'])->toBe('whatsapp')
            ->and($event['user_data'])->toBe([
                'whatsapp_business_account_id' => '546588055206122',
                'ctwa_clid' => 'ctwa-test-123',
            ])
            ->and($event['custom_data']['value'])->toBe(250000.0);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/222222222222222/events?access_token=whatsapp-token'));
    } finally {
        $lead?->delete();
        $customer?->delete();
    }
});

it('maps whatsapp Lead events to LeadSubmitted', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['events_received' => 1], 200),
    ]);

    $customer = null;
    $lead = null;

    try {
        $customer = Customer::factory()->create([
            'Meta_whatsapp_dataset' => true,
            'Meta_whatsapp_dataset_id' => '222222222222222',
            'Meta_whatsapp_dataset_token' => 'whatsapp-token',
        ]);

        $lead = Lead::create([
            'customer_id' => $customer->id,
            'name' => 'Juancho',
            'last_name' => 'Whatsapp',
            'phone' => '573001112233',
            'campaign_origin' => 'whatsapp',
            'ctwa_clid' => 'ctwa-lead-123',
            'whatsapp_business_account_id' => '546588055206122',
        ]);

        $result = app(FacebookConversionsService::class)->sendLeadEvent($lead, $customer->id, 'Lead');

        expect(data_get($result, 'request.data.0.event_name'))->toBe('LeadSubmitted')
            ->and(data_get($result, 'request.data.0.custom_data'))->toBeNull();
    } finally {
        $lead?->delete();
        $customer?->delete();
    }
});

it('uses the crm state whatsapp event for whatsapp dataset conversions', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['events_received' => 1], 200),
    ]);

    $customer = null;
    $lead = null;
    $crmState = null;
    $qualificationId = null;

    try {
        $qualificationId = DB::table('qualification')->insertGetId([
            'name' => 'Qualified',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $whatsappEvent = WhatsAppEvent::query()->firstOrCreate(
            ['event_name' => 'QualifiedLead'],
            [
                'description' => 'Lead calificado',
                'funnel_usefulness' => 'useful',
                'active' => true,
                'sort_order' => 20,
            ]
        );

        $crmState = CrmState::create([
            'id' => 'test-qualified-whatsapp',
            'name' => 'Qualified WhatsApp',
            'qualification' => $qualificationId,
            'whatsapp_event_id' => $whatsappEvent->id,
        ]);

        $customer = Customer::factory()->create([
            'Meta_whatsapp_dataset' => true,
            'Meta_whatsapp_dataset_id' => '222222222222222',
            'Meta_whatsapp_dataset_token' => 'whatsapp-token',
        ]);

        $lead = Lead::create([
            'customer_id' => $customer->id,
            'name' => 'Juancho',
            'last_name' => 'Whatsapp',
            'phone' => '573001112233',
            'campaign_origin' => 'whatsapp',
            'crm_state' => $crmState->id,
            'ctwa_clid' => 'ctwa-qualified-123',
            'whatsapp_business_account_id' => '546588055206122',
        ]);

        $result = app(FacebookConversionsService::class)->sendLeadEvent($lead, $customer->id);

        expect(data_get($result, 'request.data.0.event_name'))->toBe('QualifiedLead');
    } finally {
        $lead?->delete();
        $crmState?->delete();
        if ($qualificationId) {
            DB::table('qualification')->where('id', $qualificationId)->delete();
        }
        $customer?->delete();
    }
});

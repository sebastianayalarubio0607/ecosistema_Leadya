<?php

use App\Http\Services\Integration\GohighlevelService;
use App\Models\Integration;
use App\Models\Integrationtype;
use App\Models\Lead;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'database.connections.sqlite.foreign_key_constraints' => true,
    ]);

    DB::purge('sqlite');
    DB::reconnect('sqlite');

    Schema::create('integrationtypes', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('description')->nullable();
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('integrations', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->foreignId('integrationtype_id')->nullable();
        $table->boolean('status')->nullable()->default(true);
        $table->foreignId('customer_id')->nullable();
        $table->string('url')->nullable();
        $table->mediumText('tokent')->nullable();
        $table->text('body')->nullable();
        $table->text('body_oportunidad')->nullable();
        $table->boolean('disable_integration_id_crm_prefix')->default(false);
        $table->string('crm_id_prefix')->nullable();
        $table->timestamps();
    });

    Schema::create('leads', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email')->nullable();
        $table->string('phone')->nullable();
        $table->string('crm_id')->nullable();
        $table->string('crm_id_oportunidad')->nullable();
        $table->timestamps();
    });

    Schema::create('integration_variable_mappings', function (Blueprint $table) {
        $table->id();
        $table->foreignId('integration_id');
        $table->string('target_variable');
        $table->string('lead_field');
        $table->string('expected_value');
        $table->text('mapped_value')->nullable();
        $table->unsignedInteger('order')->nullable();
        $table->boolean('active')->default(true);
        $table->timestamps();
    });
});

function gohighlevelOpportunityIntegration(): Integration
{
    $type = Integrationtype::create([
        'name' => 'gohighlevel-oportunidad',
        'description' => 'GoHighLevel oportunidad',
        'status' => 1,
    ]);

    return Integration::create([
        'name' => 'GHL oportunidad',
        'integrationtype_id' => $type->id,
        'status' => 1,
        'url' => 'https://services.leadconnectorhq.com/contacts/upsert',
        'tokent' => 'secret-token',
        'body' => '{"locationId":"loc-1","firstName":"{{ lead->name }}","lastName":"{{ lead->last_name }}","email":"{{ lead->email }}","phone":"{{ lead->phone }}"}',
        'body_oportunidad' => '{"locationId":"loc-1","pipelineId":"pipe-1","pipelineStageId":"stage-1","contactId":"{{contactId}}","name":"{{ lead->name }}","status":"open"}',
    ]);
}

function gohighlevelLead(array $overrides = []): Lead
{
    return Lead::create(array_merge([
        'name' => 'Ana',
        'last_name' => 'Perez',
        'email' => 'ana@example.test',
        'phone' => '300 123 4567',
    ], $overrides));
}

function ghlQuery(Request $request): array
{
    parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

    return $query;
}

it('creates a contact and then an opportunity when no contact exists', function () {
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/contacts/search/duplicate')) {
            return Http::response(['contact' => null], 200);
        }

        if ($request->url() === 'https://services.leadconnectorhq.com/contacts/upsert') {
            return Http::response(['contact' => ['id' => 'contact-new']], 200);
        }

        if ($request->url() === 'https://services.leadconnectorhq.com/opportunities/') {
            return Http::response(['opportunity' => ['id' => 'opp-new']], 201);
        }

        return Http::response([], 404);
    });

    $lead = gohighlevelLead();
    $response = app(GohighlevelService::class)->sendToGohighlevelOportunidad($lead, gohighlevelOpportunityIntegration());

    expect($response->successful())->toBeTrue();
    expect($lead->refresh()->crm_id)->toBe('1-contact-new')
        ->and($lead->crm_id_oportunidad)->toBe('1-opp-new');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://services.leadconnectorhq.com/contacts/upsert');
    Http::assertSent(fn (Request $request) => $request->url() === 'https://services.leadconnectorhq.com/opportunities/'
        && data_get($request->data(), 'contactId') === 'contact-new');
});

it('uses an existing phone contact and creates a new opportunity without upserting the contact', function () {
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/contacts/search/duplicate')) {
            return Http::response(['contact' => ['id' => 'contact-existing']], 200);
        }

        if ($request->url() === 'https://services.leadconnectorhq.com/opportunities/') {
            return Http::response(['id' => 'opp-existing-phone'], 201);
        }

        return Http::response([], 404);
    });

    $lead = gohighlevelLead();
    $response = app(GohighlevelService::class)->sendToGohighlevelOportunidad($lead, gohighlevelOpportunityIntegration());

    expect($response->successful())->toBeTrue();
    expect($lead->refresh()->crm_id)->toBe('1-contact-existing')
        ->and($lead->crm_id_oportunidad)->toBe('1-opp-existing-phone');

    Http::assertNotSent(fn (Request $request) => $request->url() === 'https://services.leadconnectorhq.com/contacts/upsert');
});

it('uses an existing email contact when phone is empty', function () {
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/contacts/search/duplicate')) {
            $query = ghlQuery($request);

            return isset($query['email'])
                ? Http::response(['contact' => ['id' => 'contact-by-email']], 200)
                : Http::response(['contact' => null], 200);
        }

        if ($request->url() === 'https://services.leadconnectorhq.com/opportunities/') {
            return Http::response(['opportunityId' => 'opp-email'], 201);
        }

        return Http::response([], 404);
    });

    $lead = gohighlevelLead(['phone' => null, 'email' => '  ANA@EXAMPLE.TEST  ']);
    $response = app(GohighlevelService::class)->sendToGohighlevelOportunidad($lead, gohighlevelOpportunityIntegration());

    expect($response->successful())->toBeTrue();
    expect($lead->refresh()->crm_id)->toBe('1-contact-by-email')
        ->and($lead->crm_id_oportunidad)->toBe('1-opp-email');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'email=ana%40example.test'));
    Http::assertNotSent(fn (Request $request) => $request->url() === 'https://services.leadconnectorhq.com/contacts/upsert');
});

it('does not create an opportunity when contact creation fails', function () {
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/contacts/search/duplicate')) {
            return Http::response(['contact' => null], 200);
        }

        if ($request->url() === 'https://services.leadconnectorhq.com/contacts/upsert') {
            return Http::response(['message' => 'Invalid contact'], 422);
        }

        return Http::response([], 404);
    });

    $response = app(GohighlevelService::class)->sendToGohighlevelOportunidad(gohighlevelLead(), gohighlevelOpportunityIntegration());

    expect($response->status())->toBe(422);
    Http::assertNotSent(fn (Request $request) => $request->url() === 'https://services.leadconnectorhq.com/opportunities/');
});

it('returns the opportunity error without recreating the existing contact', function () {
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/contacts/search/duplicate')) {
            return Http::response(['contact' => ['id' => 'contact-existing']], 200);
        }

        if ($request->url() === 'https://services.leadconnectorhq.com/opportunities/') {
            return Http::response(['message' => 'Invalid opportunity'], 400);
        }

        return Http::response([], 404);
    });

    $response = app(GohighlevelService::class)->sendToGohighlevelOportunidad(gohighlevelLead(), gohighlevelOpportunityIntegration());

    expect($response->status())->toBe(400);
    Http::assertNotSent(fn (Request $request) => $request->url() === 'https://services.leadconnectorhq.com/contacts/upsert');
});

it('stops when phone and email resolve to different contacts', function () {
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/contacts/search/duplicate')) {
            $query = ghlQuery($request);

            return isset($query['number'])
                ? Http::response(['contact' => ['id' => 'contact-phone']], 200)
                : Http::response(['contact' => ['id' => 'contact-email']], 200);
        }

        return Http::response([], 404);
    });

    expect(fn () => app(GohighlevelService::class)->sendToGohighlevelOportunidad(gohighlevelLead(), gohighlevelOpportunityIntegration()))
        ->toThrow(RuntimeException::class);

    Http::assertNotSent(fn (Request $request) => $request->url() === 'https://services.leadconnectorhq.com/contacts/upsert');
    Http::assertNotSent(fn (Request $request) => $request->url() === 'https://services.leadconnectorhq.com/opportunities/');
});

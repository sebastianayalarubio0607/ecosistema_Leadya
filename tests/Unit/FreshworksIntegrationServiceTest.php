<?php

use App\Http\Services\Integration\FreshworksIntegrationService;
use App\Models\FreshworksVariableMapping;
use App\Models\Integration;
use App\Models\Integrationtype;
use App\Models\Lead;
use Illuminate\Database\Schema\Blueprint;
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
        $table->string('territory_id')->nullable();
        $table->string('owner_id')->nullable();
        $table->string('city')->nullable();
        $table->string('lead_source_id')->nullable();
        $table->text('custom_field')->nullable();
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
        $table->string('city')->nullable();
        $table->string('service')->nullable();
        $table->string('crm_id')->nullable();
        $table->string('campaign_origin')->nullable();
        $table->timestamps();
    });

    Schema::create('freshworks_variable_mappings', function (Blueprint $table) {
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

it('normalizes a Freshworks custom field variable when a mapping matches', function () {
    Http::fake([
        'https://freshworks.test/contacts' => Http::response(['contact' => ['id' => 123]], 200),
    ]);

    $type = Integrationtype::create([
        'name' => 'Freshworks',
        'description' => 'Freshworks',
        'status' => 1,
    ]);

    $integration = Integration::create([
        'name' => 'Freshworks test',
        'integrationtype_id' => $type->id,
        'status' => 1,
        'url' => 'https://freshworks.test/contacts',
        'tokent' => 'secret-token',
        'territory_id' => '10',
        'owner_id' => '20',
        'city' => null,
        'lead_source_id' => '30',
        'custom_field' => '{"variable1":"{{ lead->city }}","service":"{{ lead->service }}"}',
    ]);

    FreshworksVariableMapping::create([
        'integration_id' => $integration->id,
        'target_variable' => 'variable1',
        'lead_field' => 'city',
        'expected_value' => 'medellin',
        'mapped_value' => 'Medellín',
        'order' => 0,
        'active' => true,
    ]);

    $lead = Lead::create([
        'name' => 'Ana',
        'last_name' => 'Perez',
        'email' => 'ana@example.test',
        'phone' => '3001234567',
        'city' => 'medellin',
        'service' => 'Demo',
    ]);

    $response = app(FreshworksIntegrationService::class)->sendTofreshworks($lead, $integration);

    expect($response->successful())->toBeTrue();

    Http::assertSent(function ($request) {
        $payload = $request->data();

        return $request->url() === 'https://freshworks.test/contacts'
            && $request->hasHeader('Authorization', 'Token token=secret-token')
            && data_get($payload, 'contact.custom_field.variable1') === 'Medellín'
            && data_get($payload, 'contact.custom_field.service') === 'Demo'
            && data_get($payload, 'contact.City') === 'medellin';
    });
});

it('uses the original lead value when a Freshworks mapping has no mapped value', function () {
    Http::fake([
        'https://freshworks.test/contacts' => Http::response(['contact' => ['id' => 456]], 200),
    ]);

    $type = Integrationtype::create([
        'name' => 'Freshworks',
        'description' => 'Freshworks',
        'status' => 1,
    ]);

    $integration = Integration::create([
        'name' => 'Freshworks test',
        'integrationtype_id' => $type->id,
        'status' => 1,
        'url' => 'https://freshworks.test/contacts',
        'tokent' => 'secret-token',
        'territory_id' => '10',
        'owner_id' => '20',
        'city' => null,
        'lead_source_id' => '30',
        'custom_field' => '{"variable1":"{{ lead->city }}"}',
    ]);

    FreshworksVariableMapping::create([
        'integration_id' => $integration->id,
        'target_variable' => 'variable1',
        'lead_field' => 'city',
        'expected_value' => 'bogota',
        'mapped_value' => null,
        'order' => 0,
        'active' => true,
    ]);

    $lead = Lead::create([
        'name' => 'Ana',
        'email' => 'ana@example.test',
        'phone' => '3001234567',
        'city' => 'bogota',
    ]);

    $response = app(FreshworksIntegrationService::class)->sendTofreshworks($lead, $integration);

    expect($response->successful())->toBeTrue();

    Http::assertSent(function ($request) {
        return data_get($request->data(), 'contact.custom_field.variable1') === 'bogota';
    });
});

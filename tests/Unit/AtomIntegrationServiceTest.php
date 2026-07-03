<?php

use App\Http\Services\Integration\AtomIntegrationService;
use App\Models\AtomCondition;
use App\Models\AtomWebhook;
use App\Models\Integration;
use App\Models\IntegrationVariableMapping;
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
        $table->string('description')->nullable();
        $table->foreignId('integrationtype_id')->nullable();
        $table->boolean('status')->nullable()->default(true);
        $table->foreignId('customer_id')->nullable();
        $table->string('url')->nullable();
        $table->mediumText('tokent')->nullable();
        $table->mediumText('body')->nullable();
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
        $table->timestamps();
    });

    Schema::create('atom_webhooks', function (Blueprint $table) {
        $table->id();
        $table->foreignId('integration_id');
        $table->string('name');
        $table->string('url');
        $table->unsignedInteger('order')->nullable();
        $table->boolean('active')->default(true);
        $table->boolean('is_default')->default(false);
        $table->timestamps();
    });

    Schema::create('atom_conditions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('integration_id');
        $table->foreignId('atom_webhook_id');
        $table->string('lead_field');
        $table->string('expected_value');
        $table->unsignedInteger('order')->nullable();
        $table->boolean('active')->default(true);
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

it('sends every Atom webhook whose condition matches the lead', function () {
    Http::fake([
        'https://atom.test/first' => Http::response(['ok' => true], 200),
        'https://atom.test/second' => Http::response(['ok' => true], 201),
    ]);

    $type = Integrationtype::create([
        'name' => 'Atom',
        'description' => 'Atom',
        'status' => 1,
    ]);

    $integration = Integration::create([
        'name' => 'Atom test',
        'integrationtype_id' => $type->id,
        'status' => 1,
        'tokent' => 'Bearer secret-token',
        'body' => '{"name":"{{$lead->name}}","email":"{{$lead.email}}","summary":"Lead {{$lead.name}}"}',
    ]);

    $firstWebhook = AtomWebhook::create([
        'integration_id' => $integration->id,
        'name' => 'First',
        'url' => 'https://atom.test/first',
        'order' => 0,
        'active' => true,
        'is_default' => true,
    ]);

    $secondWebhook = AtomWebhook::create([
        'integration_id' => $integration->id,
        'name' => 'Second',
        'url' => 'https://atom.test/second',
        'order' => 1,
        'active' => true,
        'is_default' => false,
    ]);

    AtomCondition::create([
        'integration_id' => $integration->id,
        'atom_webhook_id' => $firstWebhook->id,
        'lead_field' => 'city',
        'expected_value' => 'Bogota',
        'order' => 0,
        'active' => true,
    ]);

    AtomCondition::create([
        'integration_id' => $integration->id,
        'atom_webhook_id' => $secondWebhook->id,
        'lead_field' => 'service',
        'expected_value' => 'Demo',
        'order' => 1,
        'active' => true,
    ]);

    $lead = Lead::create([
        'name' => 'Ana',
        'email' => 'ana@example.test',
        'phone' => '3001234567',
        'city' => 'Bogota',
        'service' => 'Demo',
    ]);

    $result = app(AtomIntegrationService::class)->sendToAtom($lead, $integration);

    expect($result->successful())->toBeTrue()
        ->and($result->status())->toBe(200);

    Http::assertSentCount(2);
    Http::assertSent(function ($request) {
        return $request->url() === 'https://atom.test/first'
            && $request->hasHeader('Authorization', 'Bearer secret-token')
            && $request->data() === [
                'name' => 'Ana',
                'email' => 'ana@example.test',
                'summary' => 'Lead Ana',
            ];
    });
    Http::assertSent(function ($request) {
        return $request->url() === 'https://atom.test/second'
            && $request->data() === [
                'name' => 'Ana',
                'email' => 'ana@example.test',
                'summary' => 'Lead Ana',
            ];
    });
});

it('sends the default Atom webhook when no condition matches', function () {
    Http::fake([
        'https://atom.test/default' => Http::response(['ok' => true], 200),
        'https://atom.test/conditional' => Http::response(['ok' => true], 200),
    ]);

    $type = Integrationtype::create([
        'name' => 'Atom',
        'description' => 'Atom',
        'status' => 1,
    ]);

    $integration = Integration::create([
        'name' => 'Atom test',
        'integrationtype_id' => $type->id,
        'status' => 1,
        'tokent' => 'secret-token',
        'body' => '{"name":"{{$lead->name}}","city":"{{$lead.city}}"}',
    ]);

    $defaultWebhook = AtomWebhook::create([
        'integration_id' => $integration->id,
        'name' => 'Default',
        'url' => 'https://atom.test/default',
        'order' => 0,
        'active' => true,
        'is_default' => true,
    ]);

    $conditionalWebhook = AtomWebhook::create([
        'integration_id' => $integration->id,
        'name' => 'Conditional',
        'url' => 'https://atom.test/conditional',
        'order' => 1,
        'active' => true,
        'is_default' => false,
    ]);

    AtomCondition::create([
        'integration_id' => $integration->id,
        'atom_webhook_id' => $conditionalWebhook->id,
        'lead_field' => 'city',
        'expected_value' => 'Medellin',
        'order' => 0,
        'active' => true,
    ]);

    $lead = Lead::create([
        'name' => 'Ana',
        'city' => 'Bogota',
    ]);

    $result = app(AtomIntegrationService::class)->sendToAtom($lead, $integration);

    expect($result->successful())->toBeTrue();
    Http::assertSentCount(1);
    Http::assertSent(function ($request) use ($defaultWebhook) {
        return $request->url() === $defaultWebhook->url
            && $request->data() === [
                'name' => 'Ana',
                'city' => 'Bogota',
            ];
    });
});

it('normalizes Atom payload variables with integration mappings', function () {
    Http::fake([
        'https://atom.test/default' => Http::response(['ok' => true], 200),
    ]);

    $type = Integrationtype::create([
        'name' => 'Atom',
        'description' => 'Atom',
        'status' => 1,
    ]);

    $integration = Integration::create([
        'name' => 'Atom test',
        'integrationtype_id' => $type->id,
        'status' => 1,
        'tokent' => 'secret-token',
        'body' => '{"city":"{{$lead->city}}"}',
    ]);

    AtomWebhook::create([
        'integration_id' => $integration->id,
        'name' => 'Default',
        'url' => 'https://atom.test/default',
        'order' => 0,
        'active' => true,
        'is_default' => true,
    ]);

    IntegrationVariableMapping::create([
        'integration_id' => $integration->id,
        'target_variable' => 'city',
        'lead_field' => 'city',
        'expected_value' => 'medellin',
        'mapped_value' => 'Medellín',
        'order' => 0,
        'active' => true,
    ]);

    $lead = Lead::create([
        'name' => 'Ana',
        'city' => 'medellin',
    ]);

    $result = app(AtomIntegrationService::class)->sendToAtom($lead, $integration);

    expect($result->successful())->toBeTrue();
    Http::assertSent(function ($request) {
        return $request->url() === 'https://atom.test/default'
            && $request->data() === [
                'city' => 'Medellín',
            ];
    });
});

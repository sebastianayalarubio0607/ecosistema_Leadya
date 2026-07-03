<?php

use App\Http\Services\Integration\LetyIntegrationService;
use App\Models\Integration;
use App\Models\Integrationtype;
use App\Models\Lead;
use App\Models\LetyCondition;
use App\Models\LetyWebhook;
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

    Schema::create('lety_webhooks', function (Blueprint $table) {
        $table->id();
        $table->foreignId('integration_id');
        $table->string('name');
        $table->string('url');
        $table->mediumText('body');
        $table->unsignedInteger('order')->nullable();
        $table->boolean('active')->default(true);
        $table->timestamps();
    });

    Schema::create('lety_conditions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('integration_id');
        $table->foreignId('lety_webhook_id');
        $table->string('lead_field');
        $table->string('expected_value');
        $table->unsignedInteger('order')->nullable();
        $table->boolean('active')->default(true);
        $table->timestamps();
    });
});

it('sends every Lety webhook whose condition matches the lead as form data without auth', function () {
    Http::fake([
        'https://lety.test/first' => Http::response('ok', 200),
        'https://lety.test/second' => Http::response('ok', 201),
    ]);

    $type = Integrationtype::create([
        'name' => 'Lety',
        'description' => 'Lety',
        'status' => 1,
    ]);

    $integration = Integration::create([
        'name' => 'Lety test',
        'integrationtype_id' => $type->id,
        'status' => 1,
    ]);

    $firstWebhook = LetyWebhook::create([
        'integration_id' => $integration->id,
        'name' => 'First',
        'url' => 'https://lety.test/first',
        'body' => "name={{\$lead->name}}\nemail={{\$lead.email}}\nsummary=Lead {{\$lead.name}}",
        'order' => 0,
        'active' => true,
    ]);

    $secondWebhook = LetyWebhook::create([
        'integration_id' => $integration->id,
        'name' => 'Second',
        'url' => 'https://lety.test/second',
        'body' => "phone={{\$lead->phone}}\nservice={{\$lead.service}}",
        'order' => 1,
        'active' => true,
    ]);

    LetyCondition::create([
        'integration_id' => $integration->id,
        'lety_webhook_id' => $firstWebhook->id,
        'lead_field' => 'city',
        'expected_value' => 'Bogota',
        'order' => 0,
        'active' => true,
    ]);

    LetyCondition::create([
        'integration_id' => $integration->id,
        'lety_webhook_id' => $secondWebhook->id,
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

    $result = app(LetyIntegrationService::class)->sendToLety($lead, $integration);

    expect($result->successful())->toBeTrue()
        ->and($result->status())->toBe(200);

    Http::assertSentCount(2);
    Http::assertSent(function ($request) {
        $contentType = implode(';', $request->header('Content-Type'));

        return $request->url() === 'https://lety.test/first'
            && str_contains($contentType, 'application/x-www-form-urlencoded')
            && ! $request->hasHeader('Authorization')
            && $request->data() === [
                'name' => 'Ana',
                'email' => 'ana@example.test',
                'summary' => 'Lead Ana',
            ];
    });
    Http::assertSent(function ($request) {
        $contentType = implode(';', $request->header('Content-Type'));

        return $request->url() === 'https://lety.test/second'
            && str_contains($contentType, 'application/x-www-form-urlencoded')
            && ! $request->hasHeader('Authorization')
            && $request->data() === [
                'phone' => '3001234567',
                'service' => 'Demo',
            ];
    });
});

it('does not send Lety webhooks when no condition matches', function () {
    Http::fake();

    $type = Integrationtype::create([
        'name' => 'Lety',
        'description' => 'Lety',
        'status' => 1,
    ]);

    $integration = Integration::create([
        'name' => 'Lety test',
        'integrationtype_id' => $type->id,
        'status' => 1,
    ]);

    $webhook = LetyWebhook::create([
        'integration_id' => $integration->id,
        'name' => 'Only',
        'url' => 'https://lety.test/only',
        'body' => 'name={{$lead->name}}',
        'order' => 0,
        'active' => true,
    ]);

    LetyCondition::create([
        'integration_id' => $integration->id,
        'lety_webhook_id' => $webhook->id,
        'lead_field' => 'city',
        'expected_value' => 'Medellin',
        'order' => 0,
        'active' => true,
    ]);

    $lead = Lead::create([
        'name' => 'Ana',
        'city' => 'Bogota',
    ]);

    $result = app(LetyIntegrationService::class)->sendToLety($lead, $integration);

    expect($result->successful())->toBeTrue()
        ->and($result->status())->toBe(204);

    Http::assertNothingSent();
});

<?php

use App\Http\Services\Integration\ZapnitoInvitationIntegrationService;
use App\Models\Integration;
use App\Models\Integrationtype;
use App\Models\IntegrationVariableMapping;
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
        $table->text('body')->nullable();
        $table->timestamps();
    });

    Schema::create('leads', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email')->nullable();
        $table->string('phone')->nullable();
        $table->string('city')->nullable();
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

it('posts a Zapnito invitation to the invitations endpoint with json headers and mapped JSON body', function () {
    Http::fake([
        'https://comunidad.flar.com/api/v1/invitations' => Http::response(['id' => 123], 201),
    ]);

    $type = Integrationtype::create([
        'name' => 'zapnito invitacion',
        'description' => 'Zapnito',
        'status' => 1,
    ]);

    $integration = Integration::create([
        'name' => 'Zapnito test',
        'integrationtype_id' => $type->id,
        'status' => 1,
        'url' => 'https://comunidad.flar.com',
        'tokent' => 'secret-token',
        'body' => '{"user":{"name":"{{ lead->name }}","email":"{{ lead->email }}","city":"{{ lead->city }}","invited_by_email":"admin@your-community.com"}}',
    ]);

    IntegrationVariableMapping::create([
        'integration_id' => $integration->id,
        'target_variable' => 'city',
        'lead_field' => 'city',
        'expected_value' => 'medellin',
        'mapped_value' => 'Medellin',
        'order' => 0,
        'active' => true,
    ]);

    $lead = Lead::create([
        'name' => 'Ana',
        'last_name' => 'Perez',
        'email' => 'ana@example.test',
        'phone' => '3001234567',
        'city' => 'medellin',
    ]);

    $response = app(ZapnitoInvitationIntegrationService::class)->sendToZapnitoInvitation($lead, $integration);

    expect($response->successful())->toBeTrue();

    Http::assertSent(function ($request) {
        $payload = $request->data();

        return $request->url() === 'https://comunidad.flar.com/api/v1/invitations'
            && $request->hasHeader('Authorization', 'Token token=secret-token')
            && $request->hasHeader('Accept', 'application/json')
            && $request->hasHeader('Content-Type', 'application/json')
            && $request->hasHeader('charset', 'utf-8')
            && data_get($payload, 'user.name') === 'Ana'
            && data_get($payload, 'user.email') === 'ana@example.test'
            && data_get($payload, 'user.city') === 'Medellin'
            && data_get($payload, 'user.invited_by_email') === 'admin@your-community.com';
    });
});

<?php

use App\Http\Services\Integration\MondayIntegrationService;
use App\Models\Integration;
use App\Models\Integrationtype;
use App\Models\Lead;
use App\Models\MondayBoard;
use App\Models\MondayBoardColumn;
use App\Models\MondayBoardColumnMapping;
use App\Models\MondayBoardGroup;
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
        $table->timestamps();
    });

    Schema::create('monday_boards', function (Blueprint $table) {
        $table->id();
        $table->foreignId('integration_id');
        $table->string('monday_board_id', 50);
        $table->string('name');
        $table->boolean('status')->default(false);
        $table->boolean('is_default')->default(false);
        $table->string('condition_lead_field')->nullable();
        $table->string('condition_expected_value')->nullable();
        $table->string('monday_group_id', 100)->nullable();
        $table->timestamp('boards_synced_at')->nullable();
        $table->timestamp('details_synced_at')->nullable();
        $table->timestamps();
    });

    Schema::create('monday_board_groups', function (Blueprint $table) {
        $table->id();
        $table->foreignId('monday_board_id');
        $table->string('monday_group_id', 100);
        $table->string('title');
        $table->timestamps();
    });

    Schema::create('monday_board_columns', function (Blueprint $table) {
        $table->id();
        $table->foreignId('monday_board_id');
        $table->string('monday_column_id', 100);
        $table->string('title');
        $table->string('type', 100)->nullable();
        $table->longText('settings_json')->nullable();
        $table->timestamps();
    });

    Schema::create('monday_board_column_mappings', function (Blueprint $table) {
        $table->id();
        $table->foreignId('monday_board_id');
        $table->foreignId('monday_board_column_id');
        $table->string('lead_field_name')->nullable();
        $table->string('source_type', 30)->nullable();
        $table->text('static_value')->nullable();
        $table->timestamps();
    });
});

it('sends the default Monday board when no conditional board matches the lead', function () {
    Http::fake(fn ($request) => mondayCreateItemResponse($request));

    $integration = mondayIntegration();
    mondayBoard($integration, [
        'monday_board_id' => 'conditional_board',
        'name' => 'Conditional board',
        'condition_lead_field' => 'city',
        'condition_expected_value' => 'Medellin',
    ]);
    $defaultBoard = mondayBoard($integration, [
        'monday_board_id' => 'default_board',
        'name' => 'Default board',
        'is_default' => true,
        'condition_lead_field' => null,
        'condition_expected_value' => null,
    ]);

    $lead = Lead::create([
        'name' => 'Ana',
        'city' => 'Bogota',
    ]);

    $response = app(MondayIntegrationService::class)->sendToMonday($lead, $integration);

    expect($response->successful())->toBeTrue()
        ->and($lead->fresh()->crm_id)->toBe($integration->id.'-item-default_board');

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => mondaySentBoardId($request) === $defaultBoard->monday_board_id);
});

it('prefers a matching conditional Monday board over the default board', function () {
    Http::fake(fn ($request) => mondayCreateItemResponse($request));

    $integration = mondayIntegration();
    $conditionalBoard = mondayBoard($integration, [
        'monday_board_id' => 'conditional_board',
        'name' => 'Conditional board',
        'condition_lead_field' => 'city',
        'condition_expected_value' => 'Bogota',
    ]);
    mondayBoard($integration, [
        'monday_board_id' => 'default_board',
        'name' => 'Default board',
        'is_default' => true,
        'condition_lead_field' => null,
        'condition_expected_value' => null,
    ]);

    $lead = Lead::create([
        'name' => 'Ana',
        'city' => 'Bogota',
    ]);

    $response = app(MondayIntegrationService::class)->sendToMonday($lead, $integration);

    expect($response->successful())->toBeTrue()
        ->and($lead->fresh()->crm_id)->toBe($integration->id.'-item-conditional_board');

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => mondaySentBoardId($request) === $conditionalBoard->monday_board_id);
});

it('keeps only the latest configured Monday board as default', function () {
    $integration = mondayIntegration();
    $oldDefault = mondayBoard($integration, [
        'monday_board_id' => 'old_default',
        'name' => 'Old default',
        'is_default' => true,
    ]);
    $newDefault = mondayBoard($integration, [
        'monday_board_id' => 'new_default',
        'name' => 'New default',
    ]);

    app(MondayIntegrationService::class)->updateBoardConfiguration($newDefault, [
        'status' => true,
        'is_default' => true,
        'monday_group_id' => $newDefault->monday_group_id,
        'condition_lead_field' => 'city',
        'condition_expected_value' => 'Bogota',
        'mappings' => [],
    ]);

    expect($newDefault->fresh()->is_default)->toBeTrue()
        ->and($oldDefault->fresh()->is_default)->toBeFalse()
        ->and($newDefault->fresh()->condition_lead_field)->toBeNull()
        ->and($newDefault->fresh()->condition_expected_value)->toBeNull();
});

function mondayIntegration(): Integration
{
    $type = Integrationtype::create([
        'name' => 'Monday',
        'description' => 'Monday',
        'status' => 1,
    ]);

    return Integration::create([
        'name' => 'Monday test',
        'integrationtype_id' => $type->id,
        'status' => 1,
        'url' => 'https://monday.test/v2',
        'tokent' => 'secret-token',
    ]);
}

function mondayBoard(Integration $integration, array $attributes = []): MondayBoard
{
    $board = MondayBoard::create(array_merge([
        'integration_id' => $integration->id,
        'monday_board_id' => 'board_'.uniqid(),
        'name' => 'Board',
        'status' => true,
        'is_default' => false,
        'condition_lead_field' => 'city',
        'condition_expected_value' => 'Bogota',
        'monday_group_id' => 'topics',
        'details_synced_at' => now(),
    ], $attributes));

    MondayBoardGroup::create([
        'monday_board_id' => $board->id,
        'monday_group_id' => $board->monday_group_id,
        'title' => 'Topics',
    ]);

    $nameColumn = MondayBoardColumn::create([
        'monday_board_id' => $board->id,
        'monday_column_id' => 'name',
        'title' => 'Name',
        'type' => 'name',
    ]);

    MondayBoardColumnMapping::create([
        'monday_board_id' => $board->id,
        'monday_board_column_id' => $nameColumn->id,
        'lead_field_name' => 'name',
        'source_type' => 'lead_field',
    ]);

    return $board->fresh(['groups', 'columns.mapping']);
}

function mondayCreateItemResponse($request)
{
    $boardId = mondaySentBoardId($request);

    return Http::response([
        'data' => [
            'create_item' => [
                'id' => 'item-'.$boardId,
                'name' => 'Ana',
            ],
        ],
    ], 200);
}

function mondaySentBoardId($request): ?string
{
    $payload = json_decode($request->body(), true);

    return data_get($payload, 'variables.boardId');
}

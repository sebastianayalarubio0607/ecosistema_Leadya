<?php

use App\Http\Services\Meta\MetaGraphService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('paginated get reuses the original access token when next url omits it', function () {
    config(['services.meta.graph_version' => 'v24.0']);

    $requests = [];

    Http::fake(function (Request $request) use (&$requests) {
        $requests[] = $request;

        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $query);
        $query = array_merge($query, $request->data());

        if (count($requests) === 1) {
            expect($query['access_token'] ?? null)->toBe('db-page-token');

            return Http::response([
                'data' => [['id' => 'lead_1']],
                'paging' => [
                    'next' => 'https://graph.facebook.com/v24.0/form_123/leads?after=cursor_1',
                ],
            ]);
        }

        expect($query['after'] ?? null)->toBe('cursor_1');
        expect($query['access_token'] ?? null)->toBe('db-page-token');

        return Http::response([
            'data' => [['id' => 'lead_2']],
        ]);
    });

    $items = app(MetaGraphService::class)->paginatedGet('form_123/leads', [
        'fields' => 'id',
        'access_token' => 'db-page-token',
        'limit' => 1,
    ]);

    expect($items)->toBe([
        ['id' => 'lead_1'],
        ['id' => 'lead_2'],
    ]);
    expect($requests)->toHaveCount(2);
});

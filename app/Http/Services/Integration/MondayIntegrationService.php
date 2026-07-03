<?php

namespace App\Http\Services\Integration;

use App\Http\Services\Integration\Concerns\ResolvesIntegrationVariableMappings;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\MondayBoard;
use App\Models\MondayBoardColumn;
use App\Models\MondayBoardColumnMapping;
use App\Models\MondayBoardGroup;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MondayIntegrationService
{
    use ResolvesIntegrationVariableMappings;

    public function sendToMonday(Lead $lead, Integration $integration): Response
    {
        $boards = $integration->mondayBoards()
            ->where('status', true)
            ->with(['groups', 'columns.mapping'])
            ->orderBy('id')
            ->get();

        if ($boards->isEmpty()) {
            Log::info('MONDAY SKIP WITHOUT ACTIVE BOARDS', [
                'integration_id' => $integration->id,
                'lead_id' => $lead->id,
            ]);

            return $this->makeSyntheticResponse([
                'message' => 'No hay boards activas de Monday configuradas para esta integracion.',
            ], 202);
        }

        $lastResponse = null;

        foreach ($boards as $board) {
            if (!$this->boardIsReadyForDispatch($board)) {
                Log::info('MONDAY BOARD NOT READY', [
                    'integration_id' => $integration->id,
                    'lead_id' => $lead->id,
                    'monday_board_id' => $board->monday_board_id,
                    'board_id' => $board->id,
                ]);
                continue;
            }

            if (!$this->evaluateCondition($board, $lead)) {
                Log::info('MONDAY BOARD CONDITION NOT MATCHED', [
                    'integration_id' => $integration->id,
                    'lead_id' => $lead->id,
                    'board_id' => $board->id,
                    'condition_lead_field' => $board->condition_lead_field,
                    'condition_expected_value' => $board->condition_expected_value,
                ]);
                continue;
            }

            $lastResponse = $this->createItem($integration, $board, $lead);

            if ($lastResponse->successful()) {
                return $lastResponse;
            }
        }

        return $lastResponse ?? $this->makeSyntheticResponse([
            'message' => 'Ningun board de Monday cumplio las condiciones para este lead.',
        ], 202);
    }

    public function syncBoards(Integration $integration): array
    {
        $result = $this->sendGraphQLRequest(
            $integration,
            'query { boards(limit: 180) { id name } }',
            [],
            null,
            'syncBoards'
        );

        $boards = $result['response']['data']['boards'] ?? null;
        if (!is_array($boards)) {
            throw new RuntimeException('Monday devolvio una respuesta invalida para syncBoards: ' . $result['body']);
        }

        $summary = [
            'created' => 0,
            'updated' => 0,
            'total' => count($boards),
        ];

        foreach ($boards as $payload) {
            $board = MondayBoard::query()->firstOrNew([
                'integration_id' => $integration->id,
                'monday_board_id' => (string) ($payload['id'] ?? ''),
            ]);

            $exists = $board->exists;
            $board->name = (string) ($payload['name'] ?? 'Board sin nombre');
            $board->boards_synced_at = now();

            if (!$exists) {
                $board->status = false;
            }

            $board->save();
            $summary[$exists ? 'updated' : 'created']++;
        }

        Log::info('MONDAY BOARDS SYNCED', [
            'integration_id' => $integration->id,
            'log_label' => $result['log_label'],
            'request_id' => $result['request_id'],
            'summary' => $summary,
        ]);

        return $summary;
    }

    public function syncBoardDetails(MondayBoard $board): MondayBoard
    {
        $board->loadMissing('integration');

        if (!$board->status) {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden sincronizar grupos y columnas en boards activas.',
            ]);
        }

        $result = $this->sendGraphQLRequest(
            $board->integration,
            'query ($boardIds: [ID!]!) { boards(ids: $boardIds) { id name groups { id title } columns { id title type } } }',
            ['boardIds' => [(string) $board->monday_board_id]],
            null,
            'syncBoardDetails'
        );

        $boardPayload = $result['response']['data']['boards'][0] ?? null;
        if (!is_array($boardPayload)) {
            throw new RuntimeException('Monday devolvio una respuesta invalida para syncBoardDetails: ' . $result['body']);
        }

        $board->name = (string) ($boardPayload['name'] ?? $board->name);
        $board->details_synced_at = now();
        $board->save();

        foreach (($boardPayload['groups'] ?? []) as $groupPayload) {
            MondayBoardGroup::query()->updateOrCreate(
                [
                    'monday_board_id' => $board->id,
                    'monday_group_id' => (string) ($groupPayload['id'] ?? ''),
                ],
                [
                    'title' => (string) ($groupPayload['title'] ?? 'Grupo sin nombre'),
                ]
            );
        }

        foreach (($boardPayload['columns'] ?? []) as $columnPayload) {
            $column = MondayBoardColumn::query()->updateOrCreate(
                [
                    'monday_board_id' => $board->id,
                    'monday_column_id' => (string) ($columnPayload['id'] ?? ''),
                ],
                [
                    'title' => (string) ($columnPayload['title'] ?? 'Columna sin nombre'),
                    'type' => (string) ($columnPayload['type'] ?? ''),
                ]
            );

            MondayBoardColumnMapping::query()->firstOrCreate(
                [
                    'monday_board_id' => $board->id,
                    'monday_board_column_id' => $column->id,
                ],
                [
                    'source_type' => 'lead_field',
                ]
            );
        }

        Log::info('MONDAY BOARD DETAILS SYNCED', [
            'integration_id' => $board->integration_id,
            'board_id' => $board->id,
            'monday_board_id' => $board->monday_board_id,
            'log_label' => $result['log_label'],
            'request_id' => $result['request_id'],
        ]);

        return $board->fresh(['groups', 'columns.mapping']);
    }

    public function updateBoardConfiguration(MondayBoard $board, array $payload): MondayBoard
    {
        $board->loadMissing('integration');

        $board->fill([
            'status' => (bool) ($payload['status'] ?? false),
            'condition_lead_field' => $payload['condition_lead_field'] ?? null,
            'condition_expected_value' => $payload['condition_expected_value'] ?? null,
            'monday_group_id' => $payload['monday_group_id'] ?? null,
        ]);
        $board->save();

        if ($board->status && ($board->details_synced_at === null || $board->groups()->count() === 0 || $board->columns()->count() === 0)) {
            $board = $this->syncBoardDetails($board);
        } else {
            $board->load(['groups', 'columns.mapping']);
        }

        $mappings = collect($payload['mappings'] ?? [])
            ->keyBy(fn ($mapping) => (int) ($mapping['column_id'] ?? 0));

        foreach ($board->columns as $column) {
            $mappingPayload = $mappings->get($column->id, []);
            $sourceType = $mappingPayload['source_type'] ?? ($column->mapping->source_type ?? 'lead_field');
            if (!in_array($sourceType, ['lead_field', 'fixed_value'], true)) {
                $sourceType = 'lead_field';
            }

            MondayBoardColumnMapping::query()->updateOrCreate(
                [
                    'monday_board_id' => $board->id,
                    'monday_board_column_id' => $column->id,
                ],
                [
                    'source_type' => $sourceType,
                    'lead_field_name' => $sourceType === 'lead_field'
                        ? ($mappingPayload['lead_field_name'] ?? null)
                        : null,
                    'static_value' => $sourceType === 'fixed_value'
                        ? ($mappingPayload['static_value'] ?? null)
                        : null,
                ]
            );
        }

        Log::info('MONDAY BOARD CONFIG UPDATED', [
            'integration_id' => $board->integration_id,
            'board_id' => $board->id,
            'status' => $board->status,
            'condition_lead_field' => $board->condition_lead_field,
            'condition_expected_value' => $board->condition_expected_value,
            'monday_group_id' => $board->monday_group_id,
        ]);

        return $board->fresh(['groups', 'columns.mapping']);
    }

    protected function sendGraphQLRequest(
        Integration $integration,
        string $query,
        array $variables = [],
        ?string $operationName = null,
        ?string $logLabel = null
    ): array {
        $url = $this->normalizeMondayUrl($integration->url ?? null);
        $token = trim((string) $integration->tokent);

        if ($token === '') {
            throw new RuntimeException('No existe Authorization configurado para Monday.');
        }

        $documentOperationName = $this->extractGraphQLOperationName($query);
        $shouldSendOperationName = $operationName !== null
            && $documentOperationName !== null
            && $documentOperationName === $operationName;

        $payload = [
            'query' => trim($query),
        ];

        if ($variables !== []) {
            $payload['variables'] = $variables;
        }

        if ($shouldSendOperationName) {
            $payload['operationName'] = $operationName;
        }

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($jsonPayload === false) {
            throw new RuntimeException('No fue posible serializar el payload GraphQL de Monday.');
        }

        $headers = [
            'Authorization' => $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'API-Version' => config('monday.api_version', '2024-10'),
            'Apollo-Require-Preflight' => 'true',
        ];

        if ($shouldSendOperationName) {
            $headers['X-Apollo-Operation-Name'] = $operationName;
        }

        Log::info('MONDAY GRAPHQL REQUEST', [
            'integration_id' => $integration->id,
            'log_label' => $logLabel,
            'requested_operation_name' => $operationName,
            'document_operation_name' => $documentOperationName,
            'is_named_document' => $documentOperationName !== null,
            'sent_operation_name' => $shouldSendOperationName ? $operationName : null,
            'url' => $url,
            'has_variables' => $variables !== [],
            'query_preview' => mb_substr(trim(preg_replace('/\s+/', ' ', $query)), 0, 180),
        ]);

        try {
            $response = Http::timeout((int) config('monday.timeout', 20))
                ->connectTimeout((int) config('monday.connect_timeout', 10))
                ->withHeaders($headers)
                ->withBody($jsonPayload, 'application/json')
                ->post($url);
        } catch (ConnectionException $exception) {
            Log::error('MONDAY GRAPHQL CONNECTION ERROR', [
                'integration_id' => $integration->id,
                'log_label' => $logLabel,
                'requested_operation_name' => $operationName,
                'document_operation_name' => $documentOperationName,
                'url' => $url,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException('No fue posible conectar con Monday para la operacion ' . ($logLabel ?: 'graphql') . '.', 0, $exception);
        } catch (\Throwable $exception) {
            Log::error('MONDAY GRAPHQL TRANSPORT ERROR', [
                'integration_id' => $integration->id,
                'log_label' => $logLabel,
                'requested_operation_name' => $operationName,
                'document_operation_name' => $documentOperationName,
                'url' => $url,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException('Fallo el transporte HTTP hacia Monday para la operacion ' . ($logLabel ?: 'graphql') . '.', 0, $exception);
        }

        $rawBody = $response->body();
        $decoded = json_decode($rawBody, true);
        $requestId = $response->header('X-Request-Id')
            ?: $response->header('x-request-id')
            ?: ($decoded['request_id'] ?? null);

        Log::info('MONDAY GRAPHQL RESPONSE', [
            'integration_id' => $integration->id,
            'log_label' => $logLabel,
            'requested_operation_name' => $operationName,
            'document_operation_name' => $documentOperationName,
            'sent_operation_name' => $shouldSendOperationName ? $operationName : null,
            'status' => $response->status(),
            'request_id' => $requestId,
            'body_preview' => mb_substr($rawBody, 0, 500),
        ]);

        if (!is_array($decoded)) {
            throw new RuntimeException('Monday devolvio una respuesta no JSON valida en la operacion ' . ($logLabel ?: 'graphql') . ': ' . $rawBody);
        }

        if (!$response->successful()) {
            throw new RuntimeException('Monday devolvio HTTP ' . $response->status() . ' en la operacion ' . ($logLabel ?: 'graphql') . ': ' . $rawBody);
        }

        if (isset($decoded['errors']) && is_array($decoded['errors']) && $decoded['errors'] !== []) {
            throw new RuntimeException('Monday devolvio errores GraphQL en la operacion ' . ($logLabel ?: 'graphql') . ': ' . $rawBody);
        }

        return [
            'status' => $response->status(),
            'body' => $rawBody,
            'response' => $decoded,
            'request_id' => $requestId,
            'log_label' => $logLabel,
            'document_operation_name' => $documentOperationName,
            'sent_operation_name' => $shouldSendOperationName ? $operationName : null,
        ];
    }

    protected function evaluateCondition(MondayBoard $board, Lead $lead): bool
    {
        $field = trim((string) $board->condition_lead_field);
        $expected = $this->normalizeComparisonValue($board->condition_expected_value);
        $actual = $this->normalizeComparisonValue($this->resolveLeadFieldValue($lead, $field));

        if ($field === '' || $expected === '') {
            return false;
        }

        return $actual === $expected;
    }

    protected function buildColumnValues(MondayBoard $board, Lead $lead, ?Integration $integration = null): array
    {
        $board->loadMissing('columns.mapping');
        $mappings = $integration ? $this->integrationVariableMappings($integration) : collect();

        $values = [];

        foreach ($board->columns as $column) {
            $mapping = $column->mapping;
            if (!$mapping || strtolower((string) $column->type) === 'name') {
                continue;
            }

            $sourceType = $mapping->source_type ?: 'lead_field';
            $rawValue = $sourceType === 'fixed_value'
                ? $mapping->static_value
                : $this->resolveLeadFieldValue($lead, $mapping->lead_field_name);

            if ($sourceType === 'lead_field' && $integration) {
                $leadValue = $this->resolveLeadFieldValue($lead, $mapping->lead_field_name);
                $rawValue = $this->resolveMappedIntegrationValueForTargets(
                    $mappings,
                    [$column->monday_column_id, $column->title],
                    (string) $mapping->lead_field_name,
                    $leadValue,
                    $rawValue,
                    'MONDAY'
                );
            }

            $formattedValue = $this->formatColumnValue($column, $rawValue, $lead);

            if ($formattedValue === null || $formattedValue === '') {
                continue;
            }

            $values[$column->monday_column_id] = $formattedValue;
        }

        return $values;
    }

    protected function createItem(Integration $integration, MondayBoard $board, Lead $lead): Response
    {
        $itemName = $this->buildItemName($board, $lead);
        $columnValues = $this->buildColumnValues($board, $lead, $integration);

        $result = $this->sendGraphQLRequest(
            $integration,
            'mutation ($boardId: ID!, $groupId: String!, $itemName: String!, $columnValues: JSON!) { create_item(board_id: $boardId, group_id: $groupId, item_name: $itemName, column_values: $columnValues) { id name } }',
            [
                'boardId' => $board->monday_board_id,
                'groupId' => (string) $board->monday_group_id,
                'itemName' => $itemName,
                'columnValues' => json_encode($columnValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
            null,
            'createMondayItem'
        );

        $mondayItemId = $result['response']['data']['create_item']['id'] ?? null;
        if (!filled($mondayItemId)) {
            throw new RuntimeException('Monday respondio create_item sin id. Body: ' . $result['body']);
        }

        $this->persistMondayItemId($lead, $integration, (string) $mondayItemId);

        Log::info('MONDAY CREATE ITEM RESULT', [
            'integration_id' => $integration->id,
            'board_id' => $board->id,
            'lead_id' => $lead->id,
            'monday_item_id' => $mondayItemId,
            'log_label' => $result['log_label'],
            'request_id' => $result['request_id'],
        ]);

        return $this->makeSyntheticResponse($result['response'], 200);
    }

    protected function persistMondayItemId(Lead $lead, Integration $integration, string $itemId): void
    {
        $lead->crm_id = $integration->id . '-' . $itemId;
        $lead->save();
    }

    private function boardIsReadyForDispatch(MondayBoard $board): bool
    {
        $board->loadMissing(['groups', 'columns.mapping']);

        return $board->status
            && filled($board->condition_lead_field)
            && filled($board->condition_expected_value)
            && filled($board->monday_group_id)
            && $board->details_synced_at !== null
            && $board->groups->contains(fn ($group) => (string) $group->monday_group_id === (string) $board->monday_group_id)
            && $board->columns->isNotEmpty();
    }

    private function buildItemName(MondayBoard $board, Lead $lead): string
    {
        $board->loadMissing('columns.mapping');

        $nameColumn = $board->columns->first(function ($column) {
            return strtolower((string) $column->type) === 'name' && filled($column->mapping?->lead_field_name);
        });

        if ($nameColumn) {
            $value = $this->resolveLeadFieldValue($lead, (string) $nameColumn->mapping->lead_field_name);
            if (filled($value)) {
                return (string) $value;
            }
        }

        $fullName = trim(implode(' ', array_filter([
            $lead->name,
            $lead->last_name,
        ])));

        return (string) $this->firstNonEmpty(
            $fullName,
            $lead->name,
            $lead->email,
            $lead->company,
            'Lead ' . $lead->id
        );
    }

    private function resolveLeadFieldValue(Lead $lead, ?string $field)
    {
        if (blank($field)) {
            return null;
        }

        return data_get($lead, $field);
    }

    private function formatColumnValue(MondayBoardColumn $column, $value, Lead $lead)
    {
        if ($value === null || $value === '') {
            return null;
        }

        $columnType = strtolower((string) $column->type);

        if ($columnType === 'location') {
            return $this->resolveMondayLocationValue(is_scalar($value) ? (string) $value : null, $lead);
        }

        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return match ($columnType) {
            'text' => (string) $value,
            'phone' => [
                'phone' => (string) $value,
                'countryShortName' => $this->guessCountryShortName($lead),
            ],
            'email' => [
                'email' => (string) $value,
                'text' => (string) $value,
            ],
            'status' => [
                'label' => (string) $value,
            ],
            'date' => $this->formatDateValue($value),
            default => $value,
        };
    }

    private function formatDateValue($value): ?array
    {
        try {
            $date = Carbon::parse($value);
        } catch (\Throwable $exception) {
            return null;
        }

        return [
            'date' => $date->format('Y-m-d'),
        ];
    }

    private function guessCountryShortName(Lead $lead): string
    {
        $country = strtoupper(trim((string) $lead->country));

        return match ($country) {
            'CO', 'COL', 'COLOMBIA' => 'CO',
            'MX', 'MEX', 'MEXICO' => 'MX',
            'PE', 'PER', 'PERU' => 'PE',
            'CL', 'CHL', 'CHILE' => 'CL',
            default => 'CO',
        };
    }

    private function resolveMondayLocationValue(?string $city, Lead $lead): ?array
    {
        $normalizedCity = $this->normalizeMondayCityKey($city);

        if ($normalizedCity === '') {
            return null;
        }

        $catalog = $this->mondayLocationCatalog();
        $location = $catalog[$normalizedCity] ?? null;

        if ($location === null) {
            Log::info('MONDAY LOCATION CITY NOT FOUND', [
                'lead_id' => $lead->id,
                'city' => $city,
                'normalized_city' => $normalizedCity,
            ]);

            return null;
        }

        return [
            'address' => $location['address'],
            'lat' => $location['lat'],
            'lng' => $location['lng'],
        ];
    }

    private function mondayLocationCatalog(): array
    {
        static $catalog = null;

        if ($catalog !== null) {
            return $catalog;
        }

        $entries = [
            [
                'address' => 'Bogotá, Colombia',
                'lat' => 4.7110,
                'lng' => -74.0721,
                'aliases' => ['bogota', 'bogota dc', 'bogota d c', 'bogota d.c','Bogotá', 'Bogotá D.C.', 'Bogotá DC'],
            ],
            [
                'address' => 'Medellín, Colombia',
                'lat' => 6.2442,
                'lng' => -75.5812,
                'aliases' => ['medellin','medellín','Medellin', 'Medellín'],
            ],
            [
                'address' => 'Cali, Colombia',
                'lat' => 3.4516,
                'lng' => -76.5320,
                'aliases' => ['cali', 'santiago de cali'],
            ],
            [
                'address' => 'Barranquilla, Colombia',
                'lat' => 10.9685,
                'lng' => -74.7813,
                'aliases' => ['barranquilla'],
            ],
            [
                'address' => 'Cartagena, Colombia',
                'lat' => 10.3910,
                'lng' => -75.4794,
                'aliases' => ['cartagena', 'cartagena de indias'],
            ],
            [
                'address' => 'Bucaramanga, Colombia',
                'lat' => 7.1193,
                'lng' => -73.1227,
                'aliases' => ['bucaramanga'],
            ],
            [
                'address' => 'Pereira, Colombia',
                'lat' => 4.8133,
                'lng' => -75.6961,
                'aliases' => ['pereira'],
            ],
            [
                'address' => 'Manizales, Colombia',
                'lat' => 5.0703,
                'lng' => -75.5138,
                'aliases' => ['manizales'],
            ],
            [
                'address' => 'Ciudad de México, México',
                'lat' => 19.4326,
                'lng' => -99.1332,
                'aliases' => ['ciudad de mexico', 'cdmx', 'mexico city','Ciudad de México', 'Ciudad de México', 'CDMX', 'Mexico City'],
            ],
            [
                'address' => 'Guadalajara, México',
                'lat' => 20.6597,
                'lng' => -103.3496,
                'aliases' => ['guadalajara','Guadalajara'],
            ],
        ];

        $catalog = [];

        foreach ($entries as $entry) {
            foreach ($entry['aliases'] as $alias) {
                $catalog[$this->normalizeMondayCityKey($alias)] = [
                    'address' => $entry['address'],
                    'lat' => $entry['lat'],
                    'lng' => $entry['lng'],
                ];
            }
        }

        return $catalog;
    }

    private function normalizeMondayCityKey(?string $city): string
    {
        return Str::of((string) $city)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9\\s]+/', ' ')
            ->squish()
            ->toString();
    }

    private function normalizeComparisonValue($value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return mb_strtolower(trim((string) $value));
    }

    private function extractGraphQLOperationName(string $query): ?string
    {
        $normalized = ltrim(preg_replace('/#[^\r\n]*/', '', $query));

        if (!preg_match('/^(query|mutation|subscription)\s+([_A-Za-z][_0-9A-Za-z]*)\b/s', $normalized, $matches)) {
            return null;
        }

        return $matches[2] ?? null;
    }

    private function normalizeMondayUrl(?string $url): string
    {
        $normalized = trim((string) $url);
        if ($normalized === '') {
            $normalized = (string) config('monday.base_url', 'https://api.monday.com/v2');
        }

        if (!preg_match('#^https?://#i', $normalized)) {
            $normalized = 'https://' . ltrim($normalized, '/');
        }

        $normalized = rtrim($normalized, '/');

        if (filter_var($normalized, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('La URL configurada para Monday no es valida: ' . $normalized);
        }

        return $normalized;
    }

    private function makeSyntheticResponse(array $body, int $status): Response
    {
        return new Response(new Psr7Response(
            $status,
            ['Content-Type' => 'application/json'],
            json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ));
    }

    private function firstNonEmpty(...$values)
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}






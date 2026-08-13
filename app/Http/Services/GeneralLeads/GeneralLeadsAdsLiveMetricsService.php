<?php

namespace App\Http\Services\GeneralLeads;

use App\Http\Services\GoogleAds\GoogleAdsApiClient;
use App\Http\Services\GoogleAds\GoogleAdsAuthService;
use App\Http\Services\Meta\MetaGraphService;
use App\Models\Customer;
use App\Models\MetaAccessToken;
use App\Models\MetaAdAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GeneralLeadsAdsLiveMetricsService
{
    /** @var array<string, \Illuminate\Support\Collection<int|string, object>> */
    private array $metaCache = [];

    /** @var array<string, \Illuminate\Support\Collection<int|string, object>> */
    private array $googleCache = [];

    public function __construct(
        private readonly MetaGraphService $meta,
        private readonly GoogleAdsApiClient $google,
        private readonly GoogleAdsAuthService $googleAuth,
    ) {}

    public function metaRows(GeneralLeadsFilters $filters, string $section): Collection
    {
        return $this->metaCache[$this->cacheKey($filters, $section)]
            ??= $this->fetchMetaRows($filters, $section);
    }

    public function metaAdMap(GeneralLeadsFilters $filters): Collection
    {
        return $this->metaRows($filters, 'meta_ads');
    }

    public function googleRows(GeneralLeadsFilters $filters, string $section): Collection
    {
        return $this->googleCache[$this->cacheKey($filters, $section)]
            ??= $this->fetchGoogleRows($filters, $section);
    }

    private function fetchMetaRows(GeneralLeadsFilters $filters, string $section): Collection
    {
        $token = $this->resolveMetaAccessToken();
        if (! $token) {
            Log::warning('General leads live Meta metrics skipped because no active Meta token exists.');

            return new Collection;
        }

        $accounts = MetaAdAccount::query()
            ->whereNotNull('meta_account_id')
            ->where('token_can_view_account', true)
            ->when($filters->customerId, fn ($query) => $query->where('customer_id', $filters->customerId))
            ->orderBy('id')
            ->get(['id', 'customer_id', 'meta_account_id', 'name']);

        $rows = [];
        $errors = [];
        foreach ($accounts as $account) {
            try {
                foreach ($this->metaInsightsItems($account, $filters, $section, $token) as $item) {
                    $entityId = (string) data_get($item, $this->metaIdField($section), '');
                    if ($entityId === '') {
                        continue;
                    }

                    $this->mergeMetricRow($rows, $entityId, [
                        'entity_value' => $entityId,
                        'name_value' => $this->label(data_get($item, $this->metaNameField($section))),
                        'cost_value' => $this->decimal(data_get($item, 'spend')),
                        'impressions_value' => $this->integer(data_get($item, 'impressions')),
                        'clicks_value' => $this->integer(data_get($item, 'clicks')),
                        'conversions_value' => $this->metaResults($item),
                        'roas_value' => $this->metaRoas($item),
                        'ad_ids' => [(string) data_get($item, 'ad_id', '')],
                        'adset_id' => (string) data_get($item, 'adset_id', ''),
                        'campaign_id' => (string) data_get($item, 'campaign_id', ''),
                    ]);
                }
            } catch (\Throwable $exception) {
                $errors[] = $this->sanitizeErrorMessage($exception->getMessage());
                Log::warning('General leads live Meta metrics request failed.', [
                    'section' => $section,
                    'meta_ad_account_id' => $account->id,
                    'meta_account_id' => $account->meta_account_id,
                    'message' => $this->sanitizeErrorMessage($exception->getMessage()),
                ]);
            }
        }

        if ($rows === [] && $errors !== []) {
            throw new \RuntimeException('Meta no respondió la consulta de '.$this->label($section).': '.$errors[0]);
        }

        return $this->metricRows($rows);
    }

    private function metaInsightsItems(MetaAdAccount $account, GeneralLeadsFilters $filters, string $section, string $token): array
    {
        try {
            return $this->collectMetaInsightsItems($account, $filters, $section, $token, includeResults: true);
        } catch (\Throwable $exception) {
            if (! $this->isUnsupportedMetaResultsField($exception->getMessage())) {
                throw $exception;
            }

            Log::info('General leads live Meta metrics retrying without results field.', [
                'section' => $section,
                'meta_ad_account_id' => $account->id,
                'meta_account_id' => $account->meta_account_id,
            ]);

            return $this->collectMetaInsightsItems($account, $filters, $section, $token, includeResults: false);
        }
    }

    private function collectMetaInsightsItems(MetaAdAccount $account, GeneralLeadsFilters $filters, string $section, string $token, bool $includeResults): array
    {
        $items = [];

        foreach ($this->meta->paginatedGet($this->normalizeMetaActId((string) $account->meta_account_id).'/insights', [
            'access_token' => $token,
            'level' => $this->metaLevel($section),
            'fields' => implode(',', $this->metaFields($section, $includeResults)),
            'time_range' => json_encode([
                'since' => $filters->from->toDateString(),
                'until' => $filters->to->toDateString(),
            ]),
            'limit' => 500,
        ]) as $item) {
            $items[] = $item;
        }

        return $items;
    }

    private function fetchGoogleRows(GeneralLeadsFilters $filters, string $section): Collection
    {
        $credential = $this->googleAuth->ensureValidAccessToken();
        if (! $credential) {
            Log::warning('General leads live Google Ads metrics skipped because no active credential exists.');

            $reason = $this->googleAuth->lastRefreshError();

            throw new \RuntimeException(
                'No hay una credencial activa de Google Ads o no fue posible refrescar el access token.'
                .($reason ? ' '.$this->sanitizeErrorMessage($reason) : '')
            );
        }

        $customers = Customer::query()
            ->where('status', true)
            ->whereNotNull('id_Gads')
            ->where('id_Gads', '!=', '')
            ->when($filters->customerId, fn ($query) => $query->where('id', $filters->customerId))
            ->orderBy('name')
            ->get(['id', 'name', 'id_Gads']);

        $rows = [];
        $errors = [];
        foreach ($customers as $customer) {
            try {
                foreach ($this->google->searchStream($credential, (string) $customer->id_Gads, $this->googleQuery($filters, $section))['results'] as $item) {
                    $entityId = (string) data_get($item, $this->googleIdField($section), '');
                    if ($entityId === '') {
                        continue;
                    }

                    $cost = $this->integer(data_get($item, 'metrics.costMicros')) / 1000000;
                    $this->mergeMetricRow($rows, $entityId, [
                        'entity_value' => $entityId,
                        'name_value' => $this->label(data_get($item, $this->googleNameField($section))),
                        'cost_value' => $cost,
                        'impressions_value' => $this->integer(data_get($item, 'metrics.impressions')),
                        'clicks_value' => $this->integer(data_get($item, 'metrics.clicks')),
                        'conversions_value' => $this->decimal(data_get($item, 'metrics.conversions')),
                        'roas_value' => $this->googleRoas($item, $cost),
                    ]);
                }
            } catch (\Throwable $exception) {
                $errors[] = $this->sanitizeErrorMessage($exception->getMessage());
                Log::warning('General leads live Google Ads metrics request failed.', [
                    'section' => $section,
                    'customer_id' => $customer->id,
                    'message' => $this->sanitizeErrorMessage($exception->getMessage()),
                ]);
            }
        }

        if ($rows === [] && $errors !== []) {
            throw new \RuntimeException('Google Ads no respondió la consulta de '.$this->label($section).': '.$errors[0]);
        }

        return $this->metricRows($rows);
    }

    private function mergeMetricRow(array &$rows, string $entityId, array $row): void
    {
        $current = $rows[$entityId] ?? [
            'entity_value' => $entityId,
            'name_value' => $row['name_value'],
            'cost_value' => 0.0,
            'impressions_value' => 0,
            'clicks_value' => 0,
            'conversions_value' => 0.0,
            'roas_cost_weighted_value' => 0.0,
            'roas_weight_value' => 0.0,
            'ad_ids' => [],
            'adset_id' => $row['adset_id'] ?? '',
            'campaign_id' => $row['campaign_id'] ?? '',
        ];

        $current['name_value'] = $current['name_value'] ?: $row['name_value'];
        $current['cost_value'] += (float) $row['cost_value'];
        $current['impressions_value'] += (int) $row['impressions_value'];
        $current['clicks_value'] += (int) $row['clicks_value'];
        $current['conversions_value'] += (float) $row['conversions_value'];

        if (($row['roas_value'] ?? null) !== null && (float) $row['cost_value'] > 0) {
            $current['roas_cost_weighted_value'] += (float) $row['roas_value'] * (float) $row['cost_value'];
            $current['roas_weight_value'] += (float) $row['cost_value'];
        }

        foreach ($row['ad_ids'] ?? [] as $adId) {
            if ($adId !== '') {
                $current['ad_ids'][$adId] = $adId;
            }
        }

        $rows[$entityId] = $current;
    }

    private function metricRows(array $rows): Collection
    {
        return collect($rows)
            ->map(function (array $row) {
                $cost = (float) $row['cost_value'];
                $impressions = (int) $row['impressions_value'];
                $clicks = (int) $row['clicks_value'];

                return (object) [
                    'entity_value' => (string) $row['entity_value'],
                    'name_value' => (string) $row['name_value'],
                    'cost_value' => $cost,
                    'impressions_value' => $impressions,
                    'clicks_value' => $clicks,
                    'conversions_value' => (float) $row['conversions_value'],
                    'roas_value' => (float) $row['roas_weight_value'] > 0
                        ? (float) $row['roas_cost_weighted_value'] / (float) $row['roas_weight_value']
                        : null,
                    'ctr_value' => $impressions > 0 ? ($clicks / $impressions) * 100 : 0.0,
                    'cpc_value' => $clicks > 0 ? $cost / $clicks : 0.0,
                    'cpm_value' => $impressions > 0 ? ($cost / $impressions) * 1000 : 0.0,
                    'ad_ids' => array_values($row['ad_ids'] ?? []),
                    'adset_id' => (string) ($row['adset_id'] ?? ''),
                    'campaign_id' => (string) ($row['campaign_id'] ?? ''),
                ];
            })
            ->keyBy('entity_value');
    }

    private function resolveMetaAccessToken(): ?string
    {
        return MetaAccessToken::activeByType(MetaAccessToken::TYPE_SYSTEM_ACCESS_TOKEN)?->working_token
            ?: MetaAccessToken::activeByType(MetaAccessToken::TYPE_USER_ACCESS_TOKEN)?->working_token
            ?: MetaAccessToken::activeByType(MetaAccessToken::TYPE_APP_ACCESS_TOKEN)?->working_token;
    }

    private function metaLevel(string $section): string
    {
        return match ($section) {
            'meta_campaigns' => 'campaign',
            'meta_ad_sets' => 'adset',
            default => 'ad',
        };
    }

    private function metaFields(string $section, bool $includeResults = true): array
    {
        $fields = [
            'account_id',
            'account_name',
            'campaign_id',
            'campaign_name',
            'objective',
            'spend',
            'impressions',
            'clicks',
            'ctr',
            'cpc',
            'cpm',
            'actions',
            ...($includeResults ? ['results'] : []),
            'purchase_roas',
        ];

        if ($section !== 'meta_campaigns') {
            $fields[] = 'adset_id';
            $fields[] = 'adset_name';
        }

        if ($section === 'meta_ads') {
            $fields[] = 'ad_id';
            $fields[] = 'ad_name';
        }

        return $fields;
    }

    private function metaIdField(string $section): string
    {
        return match ($section) {
            'meta_campaigns' => 'campaign_id',
            'meta_ad_sets' => 'adset_id',
            default => 'ad_id',
        };
    }

    private function metaNameField(string $section): string
    {
        return match ($section) {
            'meta_campaigns' => 'campaign_name',
            'meta_ad_sets' => 'adset_name',
            default => 'ad_name',
        };
    }

    private function googleQuery(GeneralLeadsFilters $filters, string $section): string
    {
        return match ($section) {
            'google_campaigns' => "SELECT campaign.id, campaign.name, campaign.status, campaign.advertising_channel_type, segments.date, metrics.impressions, metrics.clicks, metrics.ctr, metrics.average_cpc, metrics.cost_micros, metrics.conversions, metrics.conversions_value FROM campaign WHERE {$this->googleDateClause($filters)} AND campaign.status != 'REMOVED' ORDER BY campaign.name",
            'google_ad_groups' => "SELECT campaign.id, campaign.name, ad_group.id, ad_group.name, ad_group.status, segments.date, metrics.impressions, metrics.clicks, metrics.ctr, metrics.average_cpc, metrics.cost_micros, metrics.conversions, metrics.conversions_value FROM ad_group WHERE {$this->googleDateClause($filters)} AND campaign.status != 'REMOVED' AND ad_group.status != 'REMOVED' ORDER BY campaign.name, ad_group.name",
            default => "SELECT campaign.id, campaign.name, ad_group.id, ad_group.name, ad_group_ad.ad.id, ad_group_ad.ad.name, ad_group_ad.status, segments.date, metrics.impressions, metrics.clicks, metrics.ctr, metrics.average_cpc, metrics.cost_micros, metrics.conversions, metrics.conversions_value FROM ad_group_ad WHERE {$this->googleDateClause($filters)} AND campaign.status != 'REMOVED' AND ad_group.status != 'REMOVED' AND ad_group_ad.status != 'REMOVED' ORDER BY campaign.name, ad_group.name",
        };
    }

    private function googleDateClause(GeneralLeadsFilters $filters): string
    {
        return "segments.date BETWEEN '{$filters->from->toDateString()}' AND '{$filters->to->toDateString()}'";
    }

    private function googleIdField(string $section): string
    {
        return match ($section) {
            'google_campaigns' => 'campaign.id',
            'google_ad_groups' => 'adGroup.id',
            default => 'adGroupAd.ad.id',
        };
    }

    private function googleNameField(string $section): string
    {
        return match ($section) {
            'google_campaigns' => 'campaign.name',
            'google_ad_groups' => 'adGroup.name',
            default => 'adGroupAd.ad.name',
        };
    }

    private function googleRoas(array $item, float $cost): ?float
    {
        if ($cost <= 0) {
            return null;
        }

        return $this->decimal(data_get($item, 'metrics.conversionsValue')) / $cost;
    }

    private function metaResults(array $item): float
    {
        $results = data_get($item, 'results');

        if (is_numeric($results)) {
            return $this->decimal($results);
        }

        if (is_array($results)) {
            return $this->sumActionValues($results);
        }

        $objective = Str::lower((string) data_get($item, 'objective'));
        if (Str::contains($objective, 'lead')) {
            return $this->metaLeadResult($item);
        }

        if (Str::contains($objective, ['video', 'thruplay'])) {
            return $this->metaFirstActionValue($item, [
                'video_view',
                'thruplay',
            ]);
        }

        if (Str::contains($objective, ['traffic', 'link_click'])) {
            return $this->metaFirstActionValue($item, [
                'link_click',
                'landing_page_view',
            ]);
        }

        if (Str::contains($objective, ['engagement', 'messages'])) {
            return $this->metaFirstActionValue($item, [
                'onsite_conversion.messaging_conversation_started_7d',
                'post_engagement',
                'page_engagement',
            ]);
        }

        return $this->metaConversions($item);
    }

    private function metaLeadResult(array $item): float
    {
        $leadTotal = $this->metaFirstActionValue($item, [
            'lead',
            'omni_lead',
            'leadgen_grouped',
        ]);

        if ($leadTotal > 0) {
            return $leadTotal;
        }

        return $this->sumActionValues(array_filter(
            data_get($item, 'actions', []),
            fn ($action) => $this->isMetaLeadAction((string) ($action['action_type'] ?? ''))
        ));
    }

    private function metaFirstActionValue(array $item, array $types): float
    {
        $actions = data_get($item, 'actions', []);
        if (! is_array($actions)) {
            return 0.0;
        }

        foreach ($types as $type) {
            foreach ($actions as $action) {
                if (($action['action_type'] ?? null) === $type) {
                    return $this->decimal(data_get($action, 'value'));
                }
            }
        }

        return 0.0;
    }

    private function metaConversions(array $item): float
    {
        $conversions = data_get($item, 'conversions');
        if (is_array($conversions)) {
            return $this->sumActionValues($conversions);
        }

        $actions = data_get($item, 'actions');
        if (! is_array($actions)) {
            return 0.0;
        }

        return $this->sumActionValues(array_filter($actions, fn ($action) => $this->isMetaConversionAction((string) ($action['action_type'] ?? ''))));
    }

    private function sumActionValues(array $actions): float
    {
        return array_reduce($actions, fn (float $total, mixed $action) => $total + $this->actionValue($action), 0.0);
    }

    private function actionValue(mixed $action): float
    {
        $value = data_get($action, 'value');
        if (is_numeric($value)) {
            return $this->decimal($value);
        }

        $values = data_get($action, 'values');
        if (is_array($values)) {
            return $this->sumActionValues($values);
        }

        return 0.0;
    }

    private function isMetaConversionAction(string $type): bool
    {
        return Str::contains($type, [
            'lead',
            'purchase',
            'complete_registration',
            'submit_application',
            'contact',
            'subscribe',
            'schedule',
        ]);
    }

    private function isMetaLeadAction(string $type): bool
    {
        return Str::contains($type, [
            'lead',
            'leadgen',
            'complete_registration',
            'submit_application',
            'contact',
            'subscribe',
            'schedule',
        ]);
    }

    private function isUnsupportedMetaResultsField(string $message): bool
    {
        return Str::contains(Str::lower($message), [
            'results is not valid',
            'results is not a valid',
            'unknown field',
            'invalid field',
            'provide valid app id',
        ]);
    }

    private function metaRoas(array $item): ?float
    {
        $roas = data_get($item, 'purchase_roas.0.value');

        return is_numeric($roas) ? (float) $roas : null;
    }

    private function normalizeMetaActId(string $value): string
    {
        $value = trim($value);

        return Str::startsWith($value, 'act_') ? $value : 'act_'.$value;
    }

    private function cacheKey(GeneralLeadsFilters $filters, string $section): string
    {
        return implode('|', [
            $section,
            $filters->customerId ?: 'all',
            $filters->from->toDateString(),
            $filters->to->toDateString(),
        ]);
    }

    private function integer(mixed $value): int
    {
        return (int) $value;
    }

    private function decimal(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function label(mixed $value): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : 'Sin Nombre';
    }

    private function sanitizeErrorMessage(string $message): string
    {
        $message = preg_replace('/([?&]access_token=)[^&\\s]+/i', '$1[redacted]', $message) ?? $message;

        return preg_replace('#(/customers/)\\d+(/googleAds:)#i', '$1[redacted]$2', $message) ?? $message;
    }
}

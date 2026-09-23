<?php

namespace App\Services\Alerts\Metrics;

use App\Http\Services\GoogleAds\GoogleAdsApiClient;
use App\Http\Services\GoogleAds\GoogleAdsAuthService;
use App\Http\Services\Meta\MetaGraphService;
use App\Models\Alerts\AlertMetricSubscription;
use App\Models\MetaAccessToken;
use App\Models\MetaAdAccount;
use App\Support\MetaAdAccountId;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class DirectImpressionReader
{
    public function __construct(private MetaGraphService $meta, private GoogleAdsApiClient $google, private GoogleAdsAuthService $auth, private MetricSchedule $schedule) {}

    /** Complete inventory and hourly reports only. Any failed source invalidates the whole evaluation. */
    public function read(AlertMetricSubscription $subscription, CarbonInterface $at): array
    {
        $settings = $subscription->settings;
        $rows = [];
        foreach ($settings['platforms'] as $platform) {
            if ($platform === 'meta') {
                $token = MetaAccessToken::activeByType(MetaAccessToken::TYPE_SYSTEM_ACCESS_TOKEN)?->working_token
                    ?: MetaAccessToken::activeByType(MetaAccessToken::TYPE_USER_ACCESS_TOKEN)?->working_token
                    ?: MetaAccessToken::activeByType(MetaAccessToken::TYPE_APP_ACCESS_TOKEN)?->working_token;
                if (! $token) {
                    throw new RuntimeException('Meta: no hay una credencial activa.');
                }
                $accounts = MetaAdAccount::where(function ($q) use ($subscription) {
                    $q->whereHas('customers', fn ($c) => $c->whereKey($subscription->customer_id))
                        ->orWhere(fn ($legacy) => $legacy->whereDoesntHave('customers')->where('customer_id', $subscription->customer_id));
                })->get()->filter(fn ($a) => $a->isActive())->unique(fn ($a) => MetaAdAccountId::normalize($a->meta_account_id));
                if ($accounts->isEmpty()) {
                    throw new RuntimeException('Meta: el cliente no tiene cuentas activas asociadas.');
                }
                foreach ($accounts as $account) {
                    $id = MetaAdAccountId::normalize($account->meta_account_id);
                    $key = 'metric-api:'.hash('sha256', json_encode([$platform, $id, $settings, $at->format('Y-m-d H:i'), hash('sha256', $token)]));
                    $rows = array_merge($rows, Cache::remember($key, 60, fn () => $this->metaRows($id, $token, $settings, $at)));
                }
            } else {
                $id = $this->google->normalizeCustomerId($subscription->customer->id_Gads);
                $credential = $this->auth->ensureValidAccessToken();
                if (! $id || ! $credential) {
                    throw new RuntimeException('Google Ads: falta la cuenta del cliente o una credencial válida.');
                }
                $key = 'metric-api:'.hash('sha256', json_encode([$platform, $id, $credential->id, $settings, $at->format('Y-m-d H:i')]));
                $rows = array_merge($rows, Cache::remember($key, 60, fn () => $this->googleRows($id, $credential, $settings, $at)));
            }
        }

        return $rows;
    }

    private function metaRows(string $account, string $token, array $settings, CarbonInterface $at): array
    {
        $path = MetaAdAccountId::act($account);
        $info = $this->meta->get($path, ['access_token' => $token, 'fields' => 'id,timezone_name,account_status']);
        if (! isset($info['timezone_name'], $info['account_status'])) {
            throw new RuntimeException('Meta: respuesta de cuenta incompleta.');
        }
        if ((int) $info['account_status'] !== 1) {
            throw new RuntimeException('Meta: cuenta no disponible para medir impresiones.');
        }
        [$start, $end] = $this->window($settings, $info['timezone_name'], $at);
        $level = $settings['level'] === 'group' ? 'adset' : $settings['level'];
        $edge = ['campaign' => 'campaigns', 'adset' => 'adsets', 'ad' => 'ads'][$level];
        $inventory = $this->metaPages($path.'/'.$edge, [
            'access_token' => $token, 'fields' => 'id,name,effective_status',
            'filtering' => json_encode([['field' => 'effective_status', 'operator' => 'IN', 'value' => ['ACTIVE']]]), 'limit' => 500,
        ]);
        $counts = [];
        $daily = ($settings['window_mode'] ?? 'hours') === 'days';
        $query = [
            'access_token' => $token, 'level' => $level, 'fields' => $level.'_id,impressions,date_start',
            'time_increment' => 1,
            'time_range' => json_encode(['since' => $start->toDateString(), 'until' => $end->subSecond()->toDateString()]), 'limit' => 500,
        ];
        if (! $daily) {
            $query['breakdowns'] = 'hourly_stats_aggregated_by_advertiser_time_zone';
        }
        $items = $this->metaPages($path.'/insights', $query);
        foreach ($items as $item) {
            $hour = $item['hourly_stats_aggregated_by_advertiser_time_zone'] ?? '';
            if ((! $daily && ! preg_match('/^(\d{2}):00:00 - /', $hour, $matches)) || ! isset($item[$level.'_id'], $item['date_start'], $item['impressions'])) {
                throw new RuntimeException('Meta: desglose horario incompleto.');
            }
            $bucket = CarbonImmutable::parse($item['date_start'].' '.($daily ? '00' : $matches[1]).':00:00', $info['timezone_name']);
            if ($bucket->gte($start) && $bucket->lt($end)) {
                $id = (string) $item[$level.'_id'];
                $counts[$id] = ($counts[$id] ?? 0) + $this->count($item['impressions']);
            }
        }
        $rows = [];
        foreach ($inventory as $entity) {
            if (! isset($entity['id'], $entity['effective_status'])) {
                throw new RuntimeException('Meta: inventario incompleto.');
            }
            if ($entity['effective_status'] === 'ACTIVE' && $this->selected($settings, 'meta', (string) $entity['id'])) {
                $rows[] = $this->row('meta', $account, (string) $entity['id'], $entity['name'] ?? $entity['id'], $counts[$entity['id']] ?? 0, $start, $end);
            }
        }

        return $rows;
    }

    private function metaPages(string $path, array $query): array
    {
        $items = [];
        $seen = [];
        do {
            $payload = $this->meta->get($path, $query);
            if (! isset($payload['data']) || ! is_array($payload['data'])) {
                throw new RuntimeException('Meta: reporte incompleto.');
            }
            $items = array_merge($items, $payload['data']);
            if (empty($payload['paging']['next'])) {
                break;
            }
            $cursor = data_get($payload, 'paging.cursors.after');
            if (! is_string($cursor) || isset($seen[$cursor]) || count($seen) >= 1000) {
                throw new RuntimeException('Meta: no se pudo completar la paginación.');
            }
            $seen[$cursor] = true;
            $query['after'] = $cursor;
        } while (true);

        return $items;
    }

    private function googleRows(string $account, $credential, array $settings, CarbonInterface $at): array
    {
        $info = $this->googleQuery($credential, $account, 'SELECT customer.id, customer.time_zone FROM customer LIMIT 1');
        $timezone = data_get($info, '0.customer.timeZone');
        if (! $timezone) {
            throw new RuntimeException('Google Ads: zona horaria no disponible.');
        }
        [$start, $end] = $this->window($settings, $timezone, $at);
        $daily = ($settings['window_mode'] ?? 'hours') === 'days';
        if ($settings['level'] === 'ad' && ! $daily) {
            throw new RuntimeException('Google Ads: los anuncios requieren días completos.');
        }
        [$resource, $idField, $nameField, $conditions] = match ($settings['level']) {
            'campaign' => ['campaign', 'campaign.id', 'campaign.name', "campaign.status = 'ENABLED'"],
            'group' => ['ad_group', 'ad_group.id', 'ad_group.name', "campaign.status = 'ENABLED' AND ad_group.status = 'ENABLED'"],
            default => ['ad_group_ad', 'ad_group_ad.ad.id', 'ad_group_ad.ad.name', "campaign.status = 'ENABLED' AND ad_group.status = 'ENABLED' AND ad_group_ad.status = 'ENABLED'"],
        };
        // Query inventory without metrics: reports can omit entities with zero impressions.
        $inventory = $this->googleQuery($credential, $account, "SELECT {$idField}, {$nameField} FROM {$resource} WHERE {$conditions}");
        $until = $end->subSecond()->toDateString();
        $segments = $daily ? 'segments.date' : 'segments.date, segments.hour';
        $items = $this->googleQuery($credential, $account, "SELECT {$idField}, {$segments}, metrics.impressions FROM {$resource} WHERE {$conditions} AND segments.date BETWEEN '{$start->toDateString()}' AND '{$until}'");
        $jsonId = str_replace(['ad_group_ad', 'ad_group'], ['adGroupAd', 'adGroup'], $idField);
        $jsonName = str_replace(['ad_group_ad', 'ad_group'], ['adGroupAd', 'adGroup'], $nameField);
        $counts = [];
        foreach ($items as $item) {
            $id = data_get($item, $jsonId);
            $date = data_get($item, 'segments.date');
            $hour = $daily ? 0 : data_get($item, 'segments.hour');
            $count = data_get($item, 'metrics.impressions');
            if ($id === null || ! $date || $hour === null || $count === null || (int) $hour < 0 || (int) $hour > 23) {
                throw new RuntimeException('Google Ads: desglose horario incompleto.');
            }
            $bucket = CarbonImmutable::parse($date, $timezone)->startOfDay()->setHour((int) $hour);
            if ($bucket->gte($start) && $bucket->lt($end)) {
                $counts[$id] = ($counts[$id] ?? 0) + $this->count($count);
            }
        }
        $rows = [];
        foreach ($inventory as $item) {
            $id = data_get($item, $jsonId);
            if ($id === null) {
                throw new RuntimeException('Google Ads: inventario incompleto.');
            }
            if ($this->selected($settings, 'google', (string) $id)) {
                $rows[(string) $id] = $this->row('google', $account, (string) $id, data_get($item, $jsonName) ?: (string) $id, $counts[$id] ?? 0, $start, $end);
            }
        }

        return array_values($rows);
    }

    private function googleQuery($credential, string $account, string $query): array
    {
        $result = $this->google->searchStream($credential, $account, $query);
        if (! is_array($result['raw'] ?? null) || ! array_is_list($result['raw']) || $result['raw'] === []) {
            throw new RuntimeException('Google Ads: respuesta no verificable.');
        }
        foreach ($result['raw'] as $chunk) {
            if (! is_array($chunk) || isset($chunk['error']) || (! isset($chunk['results']) && ! isset($chunk['fieldMask']))) {
                throw new RuntimeException('Google Ads: reporte incompleto.');
            }
        }

        return $result['results'];
    }

    private function window(array $settings, string $timezone, CarbonInterface $at): array
    {
        [$start, $end] = $this->schedule->window($settings, $timezone, $at);
        // Hourly reports cannot distinguish the repeated local hour at a DST transition.
        if (($settings['window_mode'] ?? 'hours') !== 'days' && $start->offset !== $end->offset) {
            throw new RuntimeException('El periodo cruza un cambio horario; no se evaluará como cero.');
        }

        return [$start, $end];
    }

    private function selected(array $settings, string $platform, string $id): bool
    {
        $ids = $settings[$platform.'_ids'] ?? [];

        return $ids === [] || in_array($id, array_map('strval', $ids), true);
    }

    private function count($value): int
    {
        if (! preg_match('/^\d+$/', (string) $value)) {
            throw new RuntimeException('La API devolvió un valor de impresiones inválido.');
        }

        return (int) $value;
    }

    private function row(string $platform, string $account, string $entity, string $name, int $count, CarbonInterface $start, CarbonInterface $end): array
    {
        return compact('platform', 'account', 'entity', 'name', 'count') + ['window_start' => $start->toIso8601String(), 'window_end' => $end->toIso8601String()];
    }
}

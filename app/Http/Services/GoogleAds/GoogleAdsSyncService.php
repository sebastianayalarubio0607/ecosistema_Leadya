<?php

namespace App\Http\Services\GoogleAds;

use App\Models\Customer;
use App\Models\GoogleAdsAd;
use App\Models\GoogleAdsAdGroup;
use App\Models\GoogleAdsCampaign;
use App\Models\GoogleAdsCredential;
use App\Support\SensitiveValue;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GoogleAdsSyncService
{
    public function __construct(
        protected GoogleAdsApiClient $apiClient,
        protected GoogleAdsAuthService $authService,
    ) {
    }

    public function syncYesterday(?Customer $customer = null): array
    {
        return $this->syncForDate(now()->subDay(), $customer);
    }

    public function syncForDate(Carbon|string $date, ?Customer $customer = null): array
    {
        $reportDate = $date instanceof Carbon ? $date->copy()->startOfDay() : Carbon::parse($date)->startOfDay();
        $customers = $customer
            ? collect([$customer])
            : Customer::query()
                ->where('status', 1)
                ->whereNotNull('id_Gads')
                ->where('id_Gads', '!=', '')
                ->orderBy('name')
                ->get();

        Log::info('Google Ads sync started.', [
            'report_date' => $reportDate->toDateString(),
            'single_customer_mode' => (bool) $customer,
            'requested_customer_id' => $customer?->id,
            'customers_found' => $customers->count(),
            'customers_ids' => $customers->pluck('id')->all(),
        ]);

        $credential = $this->authService->ensureValidAccessToken();

        $stats = [
            'report_date' => $reportDate->toDateString(),
            'customers_processed' => 0,
            'campaigns_upserted' => 0,
            'ad_groups_upserted' => 0,
            'ads_upserted' => 0,
            'errors' => [],
        ];

        if (! $credential) {
            $stats['errors'][] = 'No hay credenciales activas de Google Ads.';

            Log::warning('Google Ads sync aborted because there is no valid active global credential.', [
                'report_date' => $reportDate->toDateString(),
            ]);

            return $stats;
        }

        if ($customers->isEmpty()) {
            Log::warning('Google Ads sync found no customers with id_Gads.', [
                'report_date' => $reportDate->toDateString(),
            ]);
        }

        foreach ($customers as $item) {
            if (! $item->id_Gads) {
                Log::info('Google Ads sync skipped customer without id_Gads.', [
                    'local_customer_id' => $item->id,
                    'local_customer_name' => $item->name,
                ]);
                continue;
            }

            $stats['customers_processed']++;
            Log::info('Google Ads sync processing customer.', [
                'report_date' => $reportDate->toDateString(),
                'local_customer_id' => $item->id,
                'local_customer_name' => $item->name,
                'google_ads_customer_id' => SensitiveValue::redact($this->apiClient->normalizeCustomerId($item->id_Gads)),
                'mcc_id_masked' => SensitiveValue::redact($this->apiClient->normalizeCustomerId((string) $credential->mcc_id)),
                'credential_id' => $credential->id,
            ]);

            try {
                $result = $this->syncAllForDate($item, $reportDate, $credential);
                $stats['campaigns_upserted'] += $result['campaigns_upserted'];
                $stats['ad_groups_upserted'] += $result['ad_groups_upserted'];
                $stats['ads_upserted'] += $result['ads_upserted'];

                Log::info('Google Ads sync finished customer successfully.', [
                    'report_date' => $reportDate->toDateString(),
                    'local_customer_id' => $item->id,
                    'local_customer_name' => $item->name,
                    'google_ads_customer_id' => SensitiveValue::redact($this->apiClient->normalizeCustomerId($item->id_Gads)),
                    'mcc_id_masked' => SensitiveValue::redact($this->apiClient->normalizeCustomerId((string) $credential->mcc_id)),
                    'result' => $result,
                ]);
            } catch (\Throwable $exception) {
                $stats['errors'][] = "No fue posible sincronizar el customer {$item->name}.";
                Log::error('Google Ads sync failed for customer.', [
                    'local_customer_id' => $item->id,
                    'local_customer_name' => $item->name,
                    'google_ads_customer_id' => SensitiveValue::redact($this->apiClient->normalizeCustomerId($item->id_Gads)),
                    'mcc_id_masked' => SensitiveValue::redact($this->apiClient->normalizeCustomerId((string) $credential->mcc_id)),
                    'credential_id' => $credential->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        Log::info('Google Ads sync finished.', $stats);

        return $stats;
    }

    public function syncAllForDate(Customer $customer, Carbon $date, GoogleAdsCredential $credential): array
    {
        if (! $customer->id_Gads) {
            throw new \InvalidArgumentException('El customer no tiene id_Gads configurado.');
        }

        Log::info('Google Ads syncAllForDate started.', [
            'report_date' => $date->toDateString(),
            'local_customer_id' => $customer->id,
            'local_customer_name' => $customer->name,
            'google_ads_customer_id' => SensitiveValue::redact($this->apiClient->normalizeCustomerId($customer->id_Gads)),
            'credential_id' => $credential->id,
        ]);

        $campaigns = $this->syncCampaignMetrics($customer, $date, $credential);
        $adGroups = $this->syncAdGroupMetrics($customer, $date, $credential);
        $ads = $this->syncAdMetrics($customer, $date, $credential);

        $result = [
            'campaigns_upserted' => $campaigns,
            'ad_groups_upserted' => $adGroups,
            'ads_upserted' => $ads,
        ];

        Log::info('Google Ads syncAllForDate finished.', [
            'report_date' => $date->toDateString(),
            'local_customer_id' => $customer->id,
            'result' => $result,
        ]);

        return $result;
    }

    public function syncCampaignMetrics(Customer $customer, Carbon $date, GoogleAdsCredential $credential): int
    {
        $response = $this->apiClient->searchStream(
            $credential,
            (string) $customer->id_Gads,
            $this->buildCampaignQuery($date)
        );

        $count = 0;
        $googleAdsCustomerId = $this->apiClient->normalizeCustomerId((string) $customer->id_Gads);

        foreach ($response['results'] as $row) {
            $reportDate = (string) data_get($row, 'segments.date', $date->toDateString());
            $campaignId = (string) data_get($row, 'campaign.id', '');

            if ($campaignId === '') {
                continue;
            }

            GoogleAdsCampaign::updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'google_campaign_id' => $campaignId,
                    'report_date' => $reportDate,
                ],
                [
                    'google_ads_customer_id' => $googleAdsCustomerId,
                    'campaign_name' => data_get($row, 'campaign.name'),
                    'campaign_status' => data_get($row, 'campaign.status'),
                    'advertising_channel_type' => data_get($row, 'campaign.advertisingChannelType'),
                    'impressions' => $this->toInteger(data_get($row, 'metrics.impressions')),
                    'clicks' => $this->toInteger(data_get($row, 'metrics.clicks')),
                    'conversions' => $this->toDecimal(data_get($row, 'metrics.conversions')),
                    'cost_micros' => $this->toInteger(data_get($row, 'metrics.costMicros')),
                    'cost' => $this->normalizeCost(data_get($row, 'metrics.costMicros')),
                    'roas' => $this->calculateRoas(
                        data_get($row, 'metrics.conversionsValue') ?? data_get($row, 'metrics.conversions_value'),
                        data_get($row, 'metrics.costMicros') ?? data_get($row, 'metrics.cost_micros')
                    ),
                    'raw_payload' => $row,
                ]
            );

            $count++;
        }

        Log::info('Google Ads campaign metrics sync finished.', [
            'report_date' => $date->toDateString(),
            'local_customer_id' => $customer->id,
            'google_ads_customer_id' => SensitiveValue::redact($googleAdsCustomerId),
            'rows_received' => count($response['results']),
            'campaigns_upserted' => $count,
            'request_id' => $response['request_id'] ?? null,
        ]);

        return $count;
    }

    public function syncAdGroupMetrics(Customer $customer, Carbon $date, GoogleAdsCredential $credential): int
    {
        $response = $this->apiClient->searchStream(
            $credential,
            (string) $customer->id_Gads,
            $this->buildAdGroupQuery($date)
        );

        $count = 0;
        $googleAdsCustomerId = $this->apiClient->normalizeCustomerId((string) $customer->id_Gads);

        foreach ($response['results'] as $row) {
            $reportDate = (string) data_get($row, 'segments.date', $date->toDateString());
            $adGroupId = (string) data_get($row, 'adGroup.id', '');

            if ($adGroupId === '') {
                continue;
            }

            GoogleAdsAdGroup::updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'google_ad_group_id' => $adGroupId,
                    'report_date' => $reportDate,
                ],
                [
                    'google_ads_customer_id' => $googleAdsCustomerId,
                    'google_campaign_id' => (string) data_get($row, 'campaign.id', ''),
                    'campaign_name' => data_get($row, 'campaign.name'),
                    'ad_group_name' => data_get($row, 'adGroup.name'),
                    'ad_group_status' => data_get($row, 'adGroup.status'),
                    'impressions' => $this->toInteger(data_get($row, 'metrics.impressions')),
                    'clicks' => $this->toInteger(data_get($row, 'metrics.clicks')),
                    'conversions' => $this->toDecimal(data_get($row, 'metrics.conversions')),
                    'cost_micros' => $this->toInteger(data_get($row, 'metrics.costMicros')),
                    'cost' => $this->normalizeCost(data_get($row, 'metrics.costMicros')),
                    'roas' => $this->calculateRoas(
                        data_get($row, 'metrics.conversionsValue') ?? data_get($row, 'metrics.conversions_value'),
                        data_get($row, 'metrics.costMicros') ?? data_get($row, 'metrics.cost_micros')
                    ),
                    'raw_payload' => $row,
                ]
            );

            $count++;
        }

        Log::info('Google Ads ad group metrics sync finished.', [
            'report_date' => $date->toDateString(),
            'local_customer_id' => $customer->id,
            'google_ads_customer_id' => SensitiveValue::redact($googleAdsCustomerId),
            'rows_received' => count($response['results']),
            'ad_groups_upserted' => $count,
            'request_id' => $response['request_id'] ?? null,
        ]);

        return $count;
    }

    public function syncAdMetrics(Customer $customer, Carbon $date, GoogleAdsCredential $credential): int
    {
        $response = $this->apiClient->searchStream(
            $credential,
            (string) $customer->id_Gads,
            $this->buildAdQuery($date)
        );

        $count = 0;
        $googleAdsCustomerId = $this->apiClient->normalizeCustomerId((string) $customer->id_Gads);

        foreach ($response['results'] as $row) {
            $reportDate = (string) data_get($row, 'segments.date', $date->toDateString());
            $adId = (string) data_get($row, 'adGroupAd.ad.id', '');

            if ($adId === '') {
                continue;
            }

            GoogleAdsAd::updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'google_ad_id' => $adId,
                    'report_date' => $reportDate,
                ],
                [
                    'google_ads_customer_id' => $googleAdsCustomerId,
                    'google_campaign_id' => (string) data_get($row, 'campaign.id', ''),
                    'campaign_name' => data_get($row, 'campaign.name'),
                    'google_ad_group_id' => (string) data_get($row, 'adGroup.id', ''),
                    'ad_group_name' => data_get($row, 'adGroup.name'),
                    'ad_status' => data_get($row, 'adGroupAd.status'),
                    'impressions' => $this->toInteger(data_get($row, 'metrics.impressions')),
                    'clicks' => $this->toInteger(data_get($row, 'metrics.clicks')),
                    'conversions' => $this->toDecimal(data_get($row, 'metrics.conversions')),
                    'cost_micros' => $this->toInteger(data_get($row, 'metrics.costMicros')),
                    'cost' => $this->normalizeCost(data_get($row, 'metrics.costMicros')),
                    'roas' => $this->calculateRoas(
                        data_get($row, 'metrics.conversionsValue') ?? data_get($row, 'metrics.conversions_value'),
                        data_get($row, 'metrics.costMicros') ?? data_get($row, 'metrics.cost_micros')
                    ),
                    'raw_payload' => $row,
                ]
            );

            $count++;
        }

        Log::info('Google Ads ad metrics sync finished.', [
            'report_date' => $date->toDateString(),
            'local_customer_id' => $customer->id,
            'google_ads_customer_id' => SensitiveValue::redact($googleAdsCustomerId),
            'rows_received' => count($response['results']),
            'ads_upserted' => $count,
            'request_id' => $response['request_id'] ?? null,
        ]);

        return $count;
    }

    protected function buildCampaignQuery(Carbon $date): string
    {
        return "SELECT campaign.id, campaign.name, campaign.status, campaign.advertising_channel_type, segments.date, metrics.impressions, metrics.clicks, metrics.conversions, metrics.conversions_value, metrics.cost_micros FROM campaign WHERE ".$this->buildDateWhereClause($date)." AND campaign.status != 'REMOVED' ORDER BY campaign.name";
    }

    protected function buildAdGroupQuery(Carbon $date): string
    {
        return "SELECT campaign.id, campaign.name, ad_group.id, ad_group.name, ad_group.status, segments.date, metrics.impressions, metrics.clicks, metrics.conversions, metrics.conversions_value, metrics.cost_micros FROM ad_group WHERE ".$this->buildDateWhereClause($date)." AND campaign.status != 'REMOVED' AND ad_group.status != 'REMOVED' ORDER BY campaign.name, ad_group.name";
    }

    protected function buildAdQuery(Carbon $date): string
    {
        return "SELECT campaign.id, campaign.name, ad_group.id, ad_group.name, ad_group_ad.ad.id, ad_group_ad.status, segments.date, metrics.impressions, metrics.clicks, metrics.conversions, metrics.conversions_value, metrics.cost_micros FROM ad_group_ad WHERE ".$this->buildDateWhereClause($date)." AND campaign.status != 'REMOVED' AND ad_group.status != 'REMOVED' AND ad_group_ad.status != 'REMOVED' ORDER BY campaign.name, ad_group.name";
    }

    protected function buildDateWhereClause(Carbon $date): string
    {
        return "segments.date = '".$date->toDateString()."'";
    }

    protected function normalizeCost(mixed $value): float
    {
        return round($this->toInteger($value) / 1000000, 6);
    }

    protected function toInteger(mixed $value): int
    {
        return (int) $value;
    }

    protected function toDecimal(mixed $value): float
    {
        return round((float) $value, 2);
    }

    protected function calculateRoas(mixed $conversionsValue, mixed $costMicros): ?float
    {
        $conversionsValue = is_numeric($conversionsValue) ? (float) $conversionsValue : 0.0;
        $costMicros = is_numeric($costMicros) ? (int) $costMicros : 0;

        if ($costMicros <= 0) {
            return null;
        }

        return round($conversionsValue / ($costMicros / 1000000), 4);
    }
}

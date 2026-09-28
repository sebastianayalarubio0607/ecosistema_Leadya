<?php

namespace App\Http\Services\Integration;

use App\Models\GoogleAdsAd;
use App\Models\GoogleAdsAdGroup;
use App\Models\GoogleAdsCampaign;
use App\Models\Lead;
use App\Models\MetaAd;
use App\Models\Origin;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Builds the attribution values that are safe to send to an external CRM.
 *
 * All data comes from Leadya's local MySQL catalogues. This class must never
 * call Google, Meta or a destination CRM: catalogue synchronization is the
 * responsibility of the existing synchronization services.
 */
class LeadIntegrationContextService
{
    private const RELATION_CACHE_KEY = 'leadIntegrationContext';

    /** @var array<string, bool> */
    private array $tableExists = [];

    public static function variableNames(): array
    {
        return [
            'campaign_name',
            'campaign_id',
            'campaign_provider',
            'campaign_relation',
            'ad_group_name',
            'ad_group_id',
            'ad_group_relation',
            'ad_name',
            'ad_id',
            'ad_relation',
            'campaign_origin_name',
            'origin_name',
            'origin_relation',
            'source_name',
            'source_relation',
            'platform_name',
            'platform_relation',
            'attribution_relation',
        ];
    }

    public function hasVariable(string $name): bool
    {
        return in_array(trim($name), self::variableNames(), true);
    }

    public function value(Lead $lead, string $name): mixed
    {
        return $this->context($lead)[$name] ?? '';
    }

    /**
     * Caches the context on this lead instance so a lead sent to several
     * integrations does not repeat local catalogue queries.
     *
     * @return array<string, mixed>
     */
    public function context(Lead $lead): array
    {
        if ($lead->relationLoaded(self::RELATION_CACHE_KEY)) {
            return (array) $lead->getRelation(self::RELATION_CACHE_KEY);
        }

        $originAttribution = $this->originAttribution($lead);
        $origin = $originAttribution['origin'];
        $source = $originAttribution['source'];
        $platform = $originAttribution['platform'];
        $advertising = $this->advertising($lead);
        $campaign = $advertising['campaign'];
        $adGroup = $advertising['ad_group'];
        $ad = $advertising['ad'];

        $context = [
            'campaign_name' => $campaign['name'],
            'campaign_id' => $campaign['id'],
            'campaign_provider' => $campaign['provider'],
            'campaign_relation' => $campaign,
            'ad_group_name' => $adGroup['name'],
            'ad_group_id' => $adGroup['id'],
            'ad_group_relation' => $adGroup,
            'ad_name' => $ad['name'],
            'ad_id' => $ad['id'],
            'ad_relation' => $ad,
            'campaign_origin_name' => $origin['name'],
            'origin_name' => $origin['name'],
            'origin_relation' => $origin,
            'source_name' => $source['name'],
            'source_relation' => $source,
            'platform_name' => $platform['name'],
            'platform_relation' => $platform,
            'attribution_relation' => [
                'campaign' => $campaign,
                'ad_group' => $adGroup,
                'ad' => $ad,
                'origin' => $origin,
                'source' => $source,
                'platform' => $platform,
            ],
        ];

        $lead->setRelation(self::RELATION_CACHE_KEY, $context);

        return $context;
    }

    /**
     * Resolves the full advertising hierarchy from local catalogues only.
     *
     * @return array{campaign:array{id:?string,name:string,provider:?string,resolved:bool},ad_group:array{id:?string,name:string,provider:?string,resolved:bool},ad:array{id:?string,name:string,provider:?string,resolved:bool}}
     */
    private function advertising(Lead $lead): array
    {
        $googleCampaignId = $this->firstFilled($lead->google_campaign_id, $lead->gad_campaignid);
        $googleAdId = $this->firstFilled($lead->google_ad_id, $lead->g_ad);
        $googleAdGroupId = $this->firstFilled($lead->google_adgroup_id);

        if ($googleCampaignId !== null || $googleAdId !== null || $googleAdGroupId !== null) {
            return $this->googleAdvertising($lead, $googleCampaignId, $googleAdId, $googleAdGroupId);
        }

        return $this->metaAdvertising($lead);
    }

    /**
     * @return array{campaign:array{id:?string,name:string,provider:string,resolved:bool},ad_group:array{id:?string,name:string,provider:string,resolved:bool},ad:array{id:?string,name:string,provider:string,resolved:bool}}
     */
    private function googleAdvertising(Lead $lead, ?string $campaignId, ?string $adId, ?string $adGroupId): array
    {
        $ad = null;

        if ($adId !== null && $this->hasTable('google_ads_ads')) {
            $ad = GoogleAdsAd::query()
                ->where('customer_id', $lead->customer_id)
                ->where('google_ad_id', $adId)
                ->orderByDesc('report_date')
                ->orderByDesc('id')
                ->first();
        }

        // The hierarchy is intentionally resolved from the ad upward. The
        // IDs carried directly by the lead are only fallbacks when a local
        // catalogue level has not yet been synchronized.
        $adGroupId = $this->firstFilled($ad?->google_ad_group_id, $adGroupId);
        $adGroup = null;
        if ($adGroupId !== null && $this->hasTable('google_ads_ad_groups')) {
            $adGroup = GoogleAdsAdGroup::query()
                ->where('customer_id', $lead->customer_id)
                ->where('google_ad_group_id', $adGroupId)
                ->orderByDesc('report_date')
                ->orderByDesc('id')
                ->first();
        }

        $campaignId = $this->firstFilled($adGroup?->google_campaign_id, $ad?->google_campaign_id, $campaignId);

        $campaign = null;
        if ($campaignId !== null && $this->hasTable('google_ads_campaigns')) {
            $campaign = GoogleAdsCampaign::query()
                ->where('customer_id', $lead->customer_id)
                ->where('google_campaign_id', $campaignId)
                ->orderByDesc('report_date')
                ->orderByDesc('id')
                ->first();
        }

        $campaignName = $this->firstFilled($campaign?->campaign_name, $adGroup?->campaign_name, $ad?->campaign_name);
        $adGroupName = $this->firstFilled($adGroup?->ad_group_name, $ad?->ad_group_name);
        // Google Ads does not expose a universal editable ad name for every
        // ad type. If a synchronizer provides one in the raw payload, use it;
        // otherwise retain the ad ID instead of inventing a name.
        $adName = $this->firstFilled(
            data_get($ad?->raw_payload, 'adGroupAd.ad.name'),
            data_get($ad?->raw_payload, 'ad_group_ad.ad.name'),
            data_get($ad?->raw_payload, 'ad.name')
        );

        $hasLocalCampaignPath = $campaign !== null || $adGroup !== null || $ad !== null;

        return [
            'campaign' => $this->entity($campaignId, $campaignName, 'google', $hasLocalCampaignPath ? null : $adId),
            'ad_group' => $this->entity($adGroupId, $adGroupName, 'google'),
            'ad' => $this->entity($adId, $adName, 'google'),
        ];
    }

    /**
     * @return array{campaign:array{id:?string,name:string,provider:?string,resolved:bool},ad_group:array{id:?string,name:string,provider:?string,resolved:bool},ad:array{id:?string,name:string,provider:?string,resolved:bool}}
     */
    private function metaAdvertising(Lead $lead): array
    {
        $adId = $this->firstFilled($lead->meta_id_ad);
        $campaign = null;
        $ad = null;
        $adGroup = null;

        if ($adId !== null && $this->hasTable('meta_ads') && $this->hasTable('meta_ad_sets') && $this->hasTable('meta_campaigns')) {
            $ad = MetaAd::query()
                ->with('adSet.campaign')
                ->where('meta_ad_id', $adId)
                ->first();
            $adGroup = $ad?->adSet;
            $campaign = $adGroup?->campaign;
        }

        $campaignId = $this->firstFilled($campaign?->meta_campaign_id, data_get($lead->meta_payload, 'campaign_id'));
        $adGroupId = $this->firstFilled($adGroup?->meta_ad_set_id, data_get($lead->meta_payload, 'adset_id'), data_get($lead->meta_payload, 'ad_set_id'));

        $hasLocalCampaignPath = $campaign !== null || $adGroup !== null || $ad !== null;

        return [
            'campaign' => $this->entity($campaignId, $this->firstFilled($campaign?->name), $adId !== null || $campaignId !== null ? 'meta' : null, $hasLocalCampaignPath ? null : $adId),
            'ad_group' => $this->entity($adGroupId, $this->firstFilled($adGroup?->name), $adGroupId !== null ? 'meta' : null),
            'ad' => $this->entity($adId, $this->firstFilled($ad?->name), $adId !== null ? 'meta' : null),
        ];
    }

    /** @return array{id:?string,name:string,provider:?string,resolved:bool} */
    private function entity(?string $id, ?string $name, ?string $provider, ?string $fallbackName = null): array
    {
        return [
            'id' => $id,
            'name' => $name ?? $fallbackName ?? $id ?? '',
            'provider' => $provider,
            'resolved' => $name !== null,
        ];
    }

    /**
     * Resolves campaign origin through its own local relationship only:
     * Origin -> Source -> Platform. Lead::plataforma is never used as a
     * standalone lookup; it can only select among the platforms related to
     * the resolved source. This avoids sending an unrelated platform.
     *
     * @return array{origin:array<string,mixed>,source:array{code:?string,name:string,resolved:bool},platform:array{code:?string,name:string,resolved:bool}}
     */
    private function originAttribution(Lead $lead): array
    {
        $fallback = $this->firstFilled($lead->campaign_origin) ?? '';
        $fallbackRelation = [
            'code' => $fallback !== '' ? $fallback : null,
            'name' => $fallback,
            'resolved' => false,
        ];

        $result = [
            'origin' => $fallbackRelation,
            'source' => $fallbackRelation,
            'platform' => $fallbackRelation,
        ];

        if ($fallback === '' || ! $this->hasTable('origins') || ! $this->hasTable('sources')) {
            return $this->withOriginRelations($result);
        }

        try {
            $withPlatforms = $this->hasTable('platforms') && $this->hasTable('platform_source');
            $origin = Origin::query()
                ->where('code', $fallback)
                ->with($withPlatforms ? 'source.platforms' : 'source')
                ->first();

            if ($origin === null) {
                return $this->withOriginRelations($result);
            }

            $result['origin'] = [
                'code' => $this->firstFilled($origin->code, $fallback),
                'name' => $this->firstFilled($origin->name, $fallback) ?? $fallback,
                'resolved' => $this->firstFilled($origin->name) !== null,
            ];

            $source = $origin->source;
            if ($source === null) {
                return $this->withOriginRelations($result);
            }

            $result['source'] = [
                'code' => $this->firstFilled($source->code, $fallback),
                'name' => $this->firstFilled($source->name, $fallback) ?? $fallback,
                'resolved' => $this->firstFilled($source->name) !== null,
            ];

            // A source can belong to several platforms. Prefer the value
            // already on the lead only when it is one of those platforms;
            // otherwise only a single unambiguous related platform is used.
            $platforms = $withPlatforms ? $source->platforms : collect();
            $leadPlatformCode = $this->firstFilled($lead->plataforma);
            $platform = $leadPlatformCode === null
                ? ($platforms->count() === 1 ? $platforms->first() : null)
                : $platforms->first(fn ($item) => $this->firstFilled($item->code) === $leadPlatformCode);

            if ($platform !== null) {
                $result['platform'] = [
                    'code' => $this->firstFilled($platform->code, $fallback),
                    'name' => $this->firstFilled($platform->name, $fallback) ?? $fallback,
                    'resolved' => $this->firstFilled($platform->name) !== null,
                ];
            }
        } catch (Throwable) {
            // A local catalogue issue must not block the destination CRM.
            // The lead's campaign_origin remains the documented fallback.
        }

        return $this->withOriginRelations($result);
    }

    /** @param array{origin:array<string,mixed>,source:array{code:?string,name:string,resolved:bool},platform:array{code:?string,name:string,resolved:bool}} $attribution */
    private function withOriginRelations(array $attribution): array
    {
        $attribution['origin']['source'] = $attribution['source'];
        $attribution['origin']['platform'] = $attribution['platform'];

        return $attribution;
    }

    private function hasTable(string $table): bool
    {
        return $this->tableExists[$table] ??= Schema::hasTable($table);
    }

    private function firstFilled(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '' && ! in_array(mb_strtolower($value), ['null', 'undefined'], true)) {
                return $value;
            }
        }

        return null;
    }
}

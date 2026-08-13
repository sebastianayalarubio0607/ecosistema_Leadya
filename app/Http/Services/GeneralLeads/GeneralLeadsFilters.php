<?php

namespace App\Http\Services\GeneralLeads;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class GeneralLeadsFilters
{
    public function __construct(
        public readonly ?int $customerId,
        public readonly ?int $integrationId,
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly ?string $source,
        public readonly ?string $campaignOrigin,
        public readonly ?string $platform,
        public readonly ?string $crmState,
        public readonly ?int $qualification,
        public readonly ?string $language,
        public readonly ?string $geo,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $timezone = config('app.timezone');
        $now = now($timezone);
        $from = ($request->filled('from') ? Carbon::parse((string) $request->input('from'), $timezone) : $now->copy()->subDays(7))->startOfMinute();
        $to = ($request->filled('to') ? Carbon::parse((string) $request->input('to'), $timezone) : $now->copy())->endOfMinute();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfMinute(), $from->copy()->endOfMinute()];
        }

        return new self(
            customerId: $request->integer('customer_id') ?: null,
            integrationId: $request->integer('integration_id') ?: null,
            from: $from,
            to: $to,
            source: self::str($request->input('source')),
            campaignOrigin: self::str($request->input('campaign_origin')),
            platform: self::str($request->input('plataforma')),
            crmState: self::str($request->input('crm_state')),
            qualification: $request->integer('qualification') ?: null,
            language: self::str($request->input('lenguaje')),
            geo: self::str($request->input('geo')),
        );
    }

    public function query(array $overrides = []): array
    {
        return array_filter(array_merge([
            'customer_id' => $this->customerId,
            'integration_id' => $this->integrationId,
            'from' => $this->from->format('Y-m-d\TH:i'),
            'to' => $this->to->format('Y-m-d\TH:i'),
            'source' => $this->source,
            'campaign_origin' => $this->campaignOrigin,
            'plataforma' => $this->platform,
            'crm_state' => $this->crmState,
            'qualification' => $this->qualification,
            'lenguaje' => $this->language,
            'geo' => $this->geo,
        ], $overrides), fn ($value) => $value !== null && $value !== '');
    }

    private static function str(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}

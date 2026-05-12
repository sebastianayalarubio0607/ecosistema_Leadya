<?php

namespace App\Http\Services\Meta;

use App\Models\MetaAd;
use App\Models\MetaAdAccount;
use App\Models\MetaAdInsight;
use App\Models\MetaAdSet;
use App\Models\MetaAccessToken;
use App\Models\MetaCampaign;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Sincroniza Meta Insights (level=ad) para una fecha (YYYY-MM-DD).
 *
 * Importante:
 * - NO modifica el CRUD: este servicio es consumido por MetaAdInsightController@consult y MetaSyncController.
 * - El token SIEMPRE se toma de meta_access_tokens como configuracion global.
 * - El id de cuenta publicitaria SIEMPRE se toma de MetaAdAccount->meta_account_id
 * - Se consultan TODOS los MetaAdAccount con meta_account_id (sin filtrar status)
 */
class MetaInsightsSyncService
{
    private string $graphVersion = 'v24.0';

    /** @var array<string, \App\Models\MetaAdAccount> */
    private array $accountCache = [];

    /** @var array<string, \App\Models\MetaCampaign> */
    private array $campaignCache = [];

    /** @var array<string, \App\Models\MetaAdSet> */
    private array $adSetCache = [];

    /** @var array<string, \App\Models\MetaAd> */
    private array $adCache = [];

    /** @var string[]|null */
    private ?array $insightColumns = null;

    public function syncYesterday(?string $timezone = null): array
    {
        $tz = $timezone ?: config('app.timezone');
        $date = now($tz)->subDay()->toDateString();

        return $this->syncForDate($date);
    }

    public function syncForDate(string $date): array
    {
        $stats = [
            'date' => $date,
            'accounts_processed' => 0,
            'rows' => 0,
            'ad_accounts_upserted' => 0,
            'campaigns_upserted' => 0,
            'ad_sets_upserted' => 0,
            'ads_upserted' => 0,
            'insights_upserted' => 0,
            'errors' => [],
        ];

        $token = $this->resolveGlobalAccessToken();

        if (! $token) {
            $msg = 'No hay token global activo en meta_access_tokens.';
            $stats['errors'][] = [
                'message' => $msg,
            ];

            // 🔔 Alerta a Slack solo cuando no es satisfactorio
            $this->sendMetaWebhookLog('META_NO_TOKEN', [
                'date' => $date,
                'message' => $msg,
            ]);

            return $stats;
        }

        // ✅ Consultar TODOS los accounts guardados
        $accounts = MetaAdAccount::query()
            ->whereNotNull('meta_account_id')
            ->get();

        if ($accounts->isEmpty()) {
            $msg = 'No hay MetaAdAccount con meta_account_id para consultar.';
            $stats['errors'][] = ['message' => $msg];

            // 🔔 Alerta a Slack solo cuando no es satisfactorio
            $this->sendMetaWebhookLog('META_NO_ACCOUNTS', [
                'date' => $date,
                'message' => $msg,
            ]);

            return $stats;
        }

        foreach ($accounts as $account) {
            $stats['accounts_processed']++;

            try {
                $r = $this->syncAccount($account, $date, $token);

                $stats['rows'] += $r['rows'];
                $stats['ad_accounts_upserted'] += $r['ad_accounts_upserted'];
                $stats['campaigns_upserted'] += $r['campaigns_upserted'];
                $stats['ad_sets_upserted'] += $r['ad_sets_upserted'];
                $stats['ads_upserted'] += $r['ads_upserted'];
                $stats['insights_upserted'] += $r['insights_upserted'];
            } catch (\Throwable $e) {
                $stats['errors'][] = [
                    'meta_account_id' => $account->meta_account_id,
                    'message' => $e->getMessage(),
                ];

                // 🔔 Alerta a Slack solo cuando no es satisfactorio.
                // Evita duplicar alertas cuando el error ya viene de Meta API (porque metaHttpGet ya envía la respuesta exacta).
                $isMetaApiRuntime = $e instanceof \RuntimeException
                    && Str::startsWith($e->getMessage(), 'Meta API error');

                if (! $isMetaApiRuntime) {
                    $this->sendMetaWebhookLog('META_SYNC_ACCOUNT_EXCEPTION', [
                        'date' => $date,
                        'meta_account_id' => $account->meta_account_id,
                        'message' => $e->getMessage(),
                    ]);
                }

                Log::error('Meta insights sync failed', [
                    'meta_account_id' => $account->meta_account_id,
                    'date' => $date,
                    'exception' => $e,
                ]);
            }
        }

        // 🔔 Alertas a Slack solo cuando NO es satisfactorio (evita ruido en ejecuciones OK)
        if ($stats['accounts_processed'] > 0 && (int) $stats['rows'] === 0) {
            $this->sendMetaWebhookLog('META_NO_DATA', [
                'date' => $date,
                'accounts_processed' => $stats['accounts_processed'],
                'message' => 'Meta respondió sin data para el rango consultado (data vacía).',
            ]);
        }

        if (! empty($stats['errors'])) {
            $this->sendMetaWebhookLog('META_SYNC_PARTIAL_ERRORS', [
                'date' => $date,
                'accounts_processed' => $stats['accounts_processed'],
                'rows' => $stats['rows'],
                'errors_count' => count($stats['errors']),
                'errors_sample' => array_slice($stats['errors'], 0, 5),
            ]);
        }

        return $stats;
    }

    private function resolveGlobalAccessToken(): ?string
    {
        return MetaAccessToken::activeByType(MetaAccessToken::TYPE_SYSTEM_ACCESS_TOKEN)?->working_token
            ?: MetaAccessToken::activeByType(MetaAccessToken::TYPE_USER_ACCESS_TOKEN)?->working_token
            ?: MetaAccessToken::activeByType(MetaAccessToken::TYPE_APP_ACCESS_TOKEN)?->working_token;
    }

    private function syncAccount(MetaAdAccount $account, string $date, string $token): array
    {
        $counts = [
            'rows' => 0,
            'ad_accounts_upserted' => 0,
            'campaigns_upserted' => 0,
            'ad_sets_upserted' => 0,
            'ads_upserted' => 0,
            'insights_upserted' => 0,
        ];

        $metaAccountIdRaw = (string) ($account->meta_account_id ?? '');
        if ($metaAccountIdRaw === '') {
            return $counts;
        }

        // ✅ El endpoint requiere act_{id} pero a veces ya viene con "act_"
        $actId = $this->normalizeActId($metaAccountIdRaw);

        $fields = [
            'account_id', 'account_name',
            'campaign_id', 'campaign_name',
            'adset_id', 'adset_name',
            'ad_id', 'ad_name',

            'objective', 'optimization_goal', 'buying_type', 'attribution_setting',

            'impressions', 'reach', 'frequency', 'spend',
            'clicks', 'unique_clicks', 'inline_link_clicks',
            'ctr', 'unique_ctr', 'cpc', 'cpm',

            'actions', 'action_values', 'cost_per_action_type', 'purchase_roas',

            'date_start', 'date_stop',
        ];

        $url = "https://graph.facebook.com/{$this->graphVersion}/{$actId}/insights";

        $params = [
            'access_token' => $token,
            'level' => 'ad',
            'fields' => implode(',', $fields),
            'time_range' => ['since' => $date, 'until' => $date],
            'time_increment' => 1,
        ];

        $page = 1;

        // INFO (no se envía a Slack por filtro interno)
        $this->sendMetaWebhookLog('META_SYNC_ACCOUNT_START', [
            'meta_account_id' => $metaAccountIdRaw,
            'act_id' => $actId,
            'date' => $date,
            'graph_version' => $this->graphVersion,
            'url' => $this->sanitizeUrl($url),
            'params' => $this->sanitizeParamsForLog($params),
        ]);

        while (true) {
            $response = $this->metaHttpGet($url, $params, [
                'meta_account_id' => $metaAccountIdRaw,
                'act_id' => $actId,
                'date' => $date,
                'page' => $page,
            ]);

            if (! $response->successful()) {
                $payload = $response->json();
                throw new \RuntimeException(
                    'Meta API error ('.$response->status().'): '.json_encode($payload, JSON_UNESCAPED_UNICODE)
                );
            }

            $json = $response->json();
            $data = $json['data'] ?? [];

            foreach ($data as $item) {
                $counts['rows']++;
                $counts = $this->sumCounts($counts, $this->upsertRow($account, $item, $date));
            }

            $next = data_get($json, 'paging.next');
            if (! $next) {
                break;
            }

            // paging.next ya trae querystring (incluye access_token)
            $url = $next;
            $params = [];
            $page++;
        }

        // INFO (no se envía a Slack por filtro interno)
        $this->sendMetaWebhookLog('META_SYNC_ACCOUNT_DONE', [
            'meta_account_id' => $metaAccountIdRaw,
            'act_id' => $actId,
            'date' => $date,
            'counts' => $counts,
        ]);

        return $counts;
    }

    private function upsertRow(MetaAdAccount $seedAccount, array $item, string $consultationDate): array
    {
        $counts = [
            'ad_accounts_upserted' => 0,
            'campaigns_upserted' => 0,
            'ad_sets_upserted' => 0,
            'ads_upserted' => 0,
            'insights_upserted' => 0,
        ];

        $accountId = (string) ($item['account_id'] ?? $seedAccount->meta_account_id ?? '');
        $campaignId = (string) ($item['campaign_id'] ?? '');
        $adSetId = (string) ($item['adset_id'] ?? '');
        $adId = (string) ($item['ad_id'] ?? '');

        if ($campaignId === '' || $adSetId === '' || $adId === '') {
            return $counts;
        }

        $adAccount = $this->getOrUpsertAdAccount($accountId, $item);
        $counts['ad_accounts_upserted']++;

        $campaign = $this->getOrUpsertCampaign($campaignId, $adAccount, $item);
        $counts['campaigns_upserted']++;

        $adSet = $this->getOrUpsertAdSet($adSetId, $campaign, $item);
        $counts['ad_sets_upserted']++;

        $ad = $this->getOrUpsertAd($adId, $adSet, $item);
        $counts['ads_upserted']++;

        $dateStart = (string) ($item['date_start'] ?? $consultationDate);
        $dateStop = (string) ($item['date_stop'] ?? $consultationDate);

        $insight = MetaAdInsight::query()
            ->where('meta_ad_id', $ad->id)
            ->where('date_stop', $dateStop)
            ->first();

        if (! $insight) {
            $insight = new MetaAdInsight;
            $insight->meta_ad_id = $ad->id;
            $insight->date_stop = $dateStop;
        }

        $this->bootInsightColumns($insight);

        $this->setIfInsightColumn($insight, 'meta_ad_id', $ad->id);

        $this->setIfInsightColumn($insight, 'account_id', $accountId);
        $this->setIfInsightColumn($insight, 'account_name', $item['account_name'] ?? null);

        $this->setIfInsightColumn($insight, 'campaign_id', $campaignId);
        $this->setIfInsightColumn($insight, 'campaign_name', $item['campaign_name'] ?? null);

        $this->setIfInsightColumn($insight, 'adset_id', $adSetId);
        $this->setIfInsightColumn($insight, 'adset_name', $item['adset_name'] ?? null);

        $this->setIfInsightColumn($insight, 'ad_id', $adId);
        $this->setIfInsightColumn($insight, 'ad_name', $item['ad_name'] ?? null);

        $this->setIfInsightColumn($insight, 'objective', $item['objective'] ?? null);
        $this->setIfInsightColumn($insight, 'optimization_goal', $item['optimization_goal'] ?? null);
        $this->setIfInsightColumn($insight, 'buying_type', $item['buying_type'] ?? null);
        $this->setIfInsightColumn($insight, 'attribution_setting', $item['attribution_setting'] ?? null);

        $this->setIfInsightColumn($insight, 'impressions', $item['impressions'] ?? null);
        $this->setIfInsightColumn($insight, 'reach', $item['reach'] ?? null);
        $this->setIfInsightColumn($insight, 'frequency', $item['frequency'] ?? null);
        $this->setIfInsightColumn($insight, 'spend', $item['spend'] ?? null);

        $this->setIfInsightColumn($insight, 'clicks', $item['clicks'] ?? null);
        $this->setIfInsightColumn($insight, 'unique_clicks', $item['unique_clicks'] ?? null);
        $this->setIfInsightColumn($insight, 'inline_link_clicks', $item['inline_link_clicks'] ?? null);

        $this->setIfInsightColumn($insight, 'ctr', $item['ctr'] ?? null);
        $this->setIfInsightColumn($insight, 'unique_ctr', $item['unique_ctr'] ?? null);
        $this->setIfInsightColumn($insight, 'cpc', $item['cpc'] ?? null);
        $this->setIfInsightColumn($insight, 'cpm', $item['cpm'] ?? null);

        $this->setIfInsightColumn($insight, 'date_start', $dateStart);
        $this->setIfInsightColumn($insight, 'date_stop', $dateStop);

        // Fecha consultada desde el formulario
        $this->setIfInsightColumn($insight, 'consultation_date', $consultationDate);

        $this->setIfInsightColumn($insight, 'status', $insight->status ?: 'active');

        foreach (['actions', 'action_values', 'cost_per_action_type', 'purchase_roas'] as $col) {
            if ($this->hasInsightColumn($col)) {
                $this->setIfInsightColumn(
                    $insight,
                    $col,
                    $this->normalizeMaybeJson($insight, $col, $item[$col] ?? null)
                );
            }
        }

        $insight->save();
        $counts['insights_upserted']++;

        return $counts;
    }

    private function getOrUpsertAdAccount(string $accountId, array $item): MetaAdAccount
    {
        if (isset($this->accountCache[$accountId])) {
            return $this->accountCache[$accountId];
        }

        $model = MetaAdAccount::query()->where('meta_account_id', $accountId)->first();

        if (! $model) {
            $model = new MetaAdAccount;
            $model->meta_account_id = $accountId;
        }

        if (! empty($item['account_name'])) {
            $model->name = $item['account_name'];
        }

        $model->status = $model->status ?: 'active';
        $model->save();

        return $this->accountCache[$accountId] = $model;
    }

    private function getOrUpsertCampaign(string $campaignId, MetaAdAccount $adAccount, array $item): MetaCampaign
    {
        if (isset($this->campaignCache[$campaignId])) {
            return $this->campaignCache[$campaignId];
        }

        $model = MetaCampaign::query()
            ->where('meta_campaign_id', $campaignId)
            ->first();

        if (! $model) {
            $model = new MetaCampaign;
            $model->meta_campaign_id = $campaignId;
            $model->meta_ad_account_id = $adAccount->id;
        }

        if (! empty($item['campaign_name'])) {
            $model->name = $item['campaign_name'];
        }

        $model->status = $model->status ?: 'active';
        $model->save();

        return $this->campaignCache[$campaignId] = $model;
    }

    private function getOrUpsertAdSet(string $adSetId, MetaCampaign $campaign, array $item): MetaAdSet
    {
        if (isset($this->adSetCache[$adSetId])) {
            return $this->adSetCache[$adSetId];
        }

        $model = MetaAdSet::query()
            ->where('meta_ad_set_id', $adSetId)
            ->first();

        if (! $model) {
            $model = new MetaAdSet;
            $model->meta_ad_set_id = $adSetId;
            $model->meta_campaign_id = $campaign->id;
        }

        if (! empty($item['adset_name'])) {
            $model->name = $item['adset_name'];
        }

        $model->status = $model->status ?: 'active';
        $model->save();

        return $this->adSetCache[$adSetId] = $model;
    }

    private function getOrUpsertAd(string $adId, MetaAdSet $adSet, array $item): MetaAd
    {
        if (isset($this->adCache[$adId])) {
            return $this->adCache[$adId];
        }

        $model = MetaAd::query()
            ->where('meta_ad_id', $adId)
            ->first();

        if (! $model) {
            $model = new MetaAd;
            $model->meta_ad_id = $adId;
            $model->meta_ad_set_id = $adSet->id;
        }

        if (! empty($item['ad_name'])) {
            $model->name = $item['ad_name'];
        }

        $model->status = $model->status ?: 'active';
        $model->save();

        return $this->adCache[$adId] = $model;
    }

    private function bootInsightColumns(Model $insight): void
    {
        if ($this->insightColumns !== null) {
            return;
        }

        $this->insightColumns = Schema::getColumnListing($insight->getTable());
    }

    private function hasInsightColumn(string $col): bool
    {
        if ($this->insightColumns === null) {
            $this->insightColumns = Schema::getColumnListing((new MetaAdInsight)->getTable());
        }

        return in_array($col, $this->insightColumns, true);
    }

    private function setIfInsightColumn(Model $insight, string $col, mixed $value): void
    {
        if (! $this->hasInsightColumn($col)) {
            return;
        }

        $insight->{$col} = $value;
    }

    private function normalizeMaybeJson(Model $insight, string $col, mixed $value): mixed
    {
        if (is_null($value)) {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return json_encode($decoded, JSON_UNESCAPED_UNICODE);
            }

            return $value;
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return $value;
    }

    private function sumCounts(array $a, array $b): array
    {
        foreach ($b as $k => $v) {
            if (! isset($a[$k])) {
                $a[$k] = 0;
            }
            $a[$k] += (int) $v;
        }

        return $a;
    }

    // =========================
    // Slack Alerts (solo NO satisfactorias)
    // =========================

    private function sendMetaWebhookLog(string $event, array $payload = []): void
    {
        // Este webhook ahora se usa como Slack Incoming Webhook (no como logger general).
        // ✅ Solo enviamos alertas cuando NO es satisfactorio (errores / warnings).
        $url = config('services.meta.log_webhook_url');

        if (! $url) {
            return;
        }

        $shouldSend = false;

        // Eventos que SI deben notificar a Slack
        if (in_array($event, [
            'META_NO_TOKEN',
            'META_NO_ACCOUNTS',
            'META_NO_DATA',
            'META_SYNC_PARTIAL_ERRORS',
            'META_SYNC_ACCOUNT_EXCEPTION',
            'META_EXCEPTION',
            'META_RESPONSE_ERROR',
        ], true)) {
            $shouldSend = true;
        }

        // Si es una respuesta HTTP NO OK, notificamos (incluye body_raw exacto)
        if ($event === 'META_RESPONSE') {
            $status = (int) ($payload['status'] ?? 0);
            if ($status >= 400) {
                $shouldSend = true;
            }
        }

        if (! $shouldSend) {
            return;
        }

        try {
            $status = isset($payload['status']) ? (int) $payload['status'] : null;

            $isWarning = in_array($event, ['META_NO_DATA', 'META_NO_ACCOUNTS'], true);
            $emoji = $isWarning ? '⚠️' : '🚨';

            if ($event === 'META_RESPONSE' && $status !== null && $status >= 400) {
                $emoji = '🚨';
            }

            $title = $event;
            $lines = [];

            $lines[] = "{$emoji} *Meta Alert* — `{$title}`";

            // Contexto útil
            $date = $payload['date'] ?? data_get($payload, 'context.date');
            $metaAccountId = $payload['meta_account_id'] ?? data_get($payload, 'context.meta_account_id');
            $actId = $payload['act_id'] ?? data_get($payload, 'context.act_id');

            if ($date) {
                $lines[] = "*Fecha:* {$date}";
            }
            if ($metaAccountId) {
                $lines[] = "*Meta Account ID:* {$metaAccountId}";
            }
            if ($actId) {
                $lines[] = "*Act ID:* {$actId}";
            }
            if (! is_null($status)) {
                $lines[] = "*HTTP:* {$status}";
            }

            if (! empty($payload['url'])) {
                $lines[] = "*URL:* {$payload['url']}";
            }

            if (! empty($payload['message'])) {
                $lines[] = "*Mensaje:* {$payload['message']}";
            }

            // Si tenemos error estructurado
            if (! empty($payload['error']) && is_array($payload['error'])) {
                $lines[] = "*Error:* `".($payload['error']['message'] ?? 'Meta error')."`";
                if (! empty($payload['error']['type'])) {
                    $lines[] = "*Type:* {$payload['error']['type']}";
                }
                if (! empty($payload['error']['code'])) {
                    $lines[] = "*Code:* {$payload['error']['code']}";
                }
                if (! empty($payload['error']['error_subcode'])) {
                    $lines[] = "*Subcode:* {$payload['error']['error_subcode']}";
                }
            }

            // Enviar encabezado
            $this->sendSlackText($url, implode("\n", $lines));

            // ✅ Enviar body_raw EXACTO cuando exista (para debugging de Meta)
            $bodyRaw = (string) ($payload['body_raw'] ?? '');
            if ($bodyRaw !== '') {
                $this->sendSlackCodeBlockChunks($url, $bodyRaw);
                return;
            }

            // Si no hay body_raw, enviamos un resumen JSON (sin ruido)
            $payloadForSlack = $payload;
            unset($payloadForSlack['headers'], $payloadForSlack['body_raw']); // evita mensajes gigantes

            $json = json_encode($payloadForSlack, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($json !== false && $json !== 'null') {
                $this->sendSlackCodeBlockChunks($url, $json);
            }
        } catch (\Throwable $e) {
            // No bloqueamos la sincronización por fallos del webhook
            Log::warning('Slack webhook alert failed: '.$e->getMessage());
        }
    }

    private function sendSlackText(string $url, string $text): void
    {
        // Slack Incoming Webhook requiere "text"
        Http::timeout(10)->asJson()->post($url, ['text' => $text]);
    }

    private function sendSlackCodeBlockChunks(string $url, string $content, int $chunkSize = 3500): void
    {
        $content = (string) $content;

        if ($content === '') {
            return;
        }

        // En Slack, un mensaje muy largo puede fallar; por eso se envía en partes.
        $len = strlen($content);
        $offset = 0;
        $part = 1;

        while ($offset < $len) {
            $chunk = substr($content, $offset, $chunkSize);
            $offset += $chunkSize;

            $prefix = ($len > $chunkSize) ? "(parte {$part})\n" : '';
            $this->sendSlackText($url, $prefix."```\n".$chunk."\n```");

            $part++;
        }
    }

    private function maskToken(?string $token): ?string
    {
        if (! $token) return $token;
        return Str::mask($token, '*', 8);
    }

    private function sanitizeParamsForLog(array $params): array
    {
        $safe = $params;

        if (isset($safe['access_token'])) {
            $safe['access_token'] = $this->maskToken((string) $safe['access_token']);
        }

        return $safe;
    }

    private function sanitizeUrl(string $url): string
    {
        $parts = parse_url($url);
        if (! $parts) return $url;

        $query = [];
        if (! empty($parts['query'])) parse_str($parts['query'], $query);

        if (isset($query['access_token'])) {
            $query['access_token'] = $this->maskToken((string) $query['access_token']);
        }

        $scheme   = $parts['scheme'] ?? null;
        $host     = $parts['host'] ?? null;
        $port     = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path     = $parts['path'] ?? '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        $base = ($scheme && $host) ? $scheme.'://'.$host.$port : '';
        $queryString = http_build_query($query);

        return $base.$path.($queryString !== '' ? '?'.$queryString : '').$fragment;
    }

    /**
     * Wrapper GET con log de request/response (body exacto)
     */
    private function metaHttpGet(string $url, array $params, array $context = []): Response
    {
        // INFO (no se envía a Slack por filtro interno)
        $this->sendMetaWebhookLog('META_REQUEST', [
            'url' => $this->sanitizeUrl($url),
            'params' => $this->sanitizeParamsForLog($params),
            'context' => $context,
        ]);

        try {
            $response = Http::retry(3, 800)->timeout(120)->get($url, $params);

            // IMPORTANTE:
            // - Este evento solo se enviará a Slack si status >= 400 (ver filtro en sendMetaWebhookLog)
            $this->sendMetaWebhookLog('META_RESPONSE', [
                'url' => $this->sanitizeUrl($url),
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body_raw' => $response->body(), // EXACTO
                'context' => $context,
            ]);

            $json = $response->json();
            if (is_array($json) && isset($json['error'])) {
                // Este SI se envía a Slack siempre (no satisfactorio), e incluye body_raw exacto
                $this->sendMetaWebhookLog('META_RESPONSE_ERROR', [
                    'url' => $this->sanitizeUrl($url),
                    'status' => $response->status(),
                    'error' => $json['error'],
                    'body_raw' => $response->body(), // EXACTO
                    'context' => $context,
                ]);
            }

            return $response;
        } catch (\Throwable $e) {
            $this->sendMetaWebhookLog('META_EXCEPTION', [
                'url' => $this->sanitizeUrl($url),
                'message' => $e->getMessage(),
                'trace' => Str::limit($e->getTraceAsString(), 8000),
                'context' => $context,
            ]);

            throw $e;
        }
    }

    /**
     * Normaliza el ID para el endpoint (/act_{id}/insights).
     * Si ya viene "act_XXXX", lo respeta.
     */
    private function normalizeActId(string $metaAccountId): string
    {
        $metaAccountId = trim($metaAccountId);

        if (Str::startsWith($metaAccountId, 'act_')) {
            return $metaAccountId;
        }

        return 'act_'.$metaAccountId;
    }
}

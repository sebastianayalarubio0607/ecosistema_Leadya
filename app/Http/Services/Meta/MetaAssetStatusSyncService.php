<?php

namespace App\Http\Services\Meta;

use App\Models\MetaAccessToken;
use App\Models\MetaAdAccount;
use App\Models\MetaAdAccountStatusHistory;
use App\Models\MetaPage;
use App\Models\MetaPageStatusHistory;
use App\Models\MetaWebhookEvent;
use App\Support\MetaAdAccountId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MetaAssetStatusSyncService
{
    public const ASSET_TYPE_ALL = 'all';
    public const ASSET_TYPE_AD_ACCOUNTS = 'ad_accounts';
    public const ASSET_TYPE_PAGES = 'pages';

    private const AD_ACCOUNT_STATUS_LABELS = [
        '1' => 'Activa',
        '2' => 'Deshabilitada',
        '3' => 'Pendiente de pago',
        '7' => 'Pendiente de revision de riesgo',
        '8' => 'Pendiente de liquidacion',
        '9' => 'En periodo de gracia',
        '100' => 'Pendiente de cierre',
        '101' => 'Cerrada',
        '201' => 'Cualquier activa',
        '202' => 'Cualquier cerrada',
    ];

    public function __construct(private readonly MetaGraphService $graph) {}

    public function syncAll(string $queryType = 'scheduled', string $assetType = self::ASSET_TYPE_ALL): array
    {
        return $this->sync(null, $queryType, null, $assetType);
    }

    public function syncCustomer(
        int $customerId,
        string $queryType = 'webhook',
        ?MetaWebhookEvent $webhookEvent = null,
        string $assetType = self::ASSET_TYPE_ALL,
    ): array {
        return $this->sync($customerId, $queryType, $webhookEvent, $assetType);
    }

    public function syncAdAccountId(int $metaAdAccountId, string $queryType = 'manual'): array
    {
        $stats = $this->emptyStats();
        $account = MetaAdAccount::find($metaAdAccountId);

        if (! $account) {
            $stats['skipped'] = 1;

            return $stats;
        }

        $stats['accounts_checked'] = 1;

        try {
            $this->syncAdAccount($account, $queryType, null, $this->resolveGlobalAccessToken());
            $stats['histories_created'] = 1;
        } catch (\Throwable $exception) {
            $stats['errors'][] = [
                'type' => 'ad_account',
                'id' => $account->id,
                'meta_account_id' => $account->meta_account_id,
                'message' => $exception->getMessage(),
            ];

            $this->logErrors(null, $queryType, self::ASSET_TYPE_AD_ACCOUNTS, $stats['errors']);
        }

        return $stats;
    }

    public function syncPageId(int $metaPageId, string $queryType = 'manual'): array
    {
        $stats = $this->emptyStats();
        $page = MetaPage::find($metaPageId);

        if (! $page) {
            $stats['skipped'] = 1;

            return $stats;
        }

        $stats['pages_checked'] = 1;

        try {
            $this->syncPage($page, $queryType, null, $this->resolveGlobalAccessToken());
            $stats['histories_created'] = 1;
        } catch (\Throwable $exception) {
            $stats['errors'][] = [
                'type' => 'page',
                'id' => $page->id,
                'meta_page_id' => $page->meta_page_id,
                'message' => $exception->getMessage(),
            ];

            $this->logErrors(null, $queryType, self::ASSET_TYPE_PAGES, $stats['errors']);
        }

        return $stats;
    }

    public function syncForWebhookEvent(MetaWebhookEvent $webhookEvent, string $queryType = 'webhook'): array
    {
        $customerId = $this->resolveCustomerIdFromWebhookEvent($webhookEvent);

        if (! $customerId) {
            return [
                'accounts_checked' => 0,
                'pages_checked' => 0,
                'histories_created' => 0,
                'errors' => [],
                'skipped' => 1,
            ];
        }

        return $this->syncCustomer($customerId, $queryType, $webhookEvent);
    }

    public function resolveCustomerIdFromWebhookEvent(MetaWebhookEvent $webhookEvent): ?int
    {
        $accountId = trim((string) ($webhookEvent->account_id ?: (
            $webhookEvent->object === 'ad_account' ? $webhookEvent->entry_id : null
        )));

        if ($accountId === '') {
            return null;
        }

        $account = MetaAdAccount::query()
            ->whereIn('meta_account_id', MetaAdAccountId::candidates($accountId))
            ->first(['id', 'customer_id']);

        return $account?->resolveWhatsappLeadCustomerId()
            ?: ($account?->customer_id ? (int) $account->customer_id : null);
    }

    private function sync(
        ?int $customerId,
        string $queryType,
        ?MetaWebhookEvent $webhookEvent,
        string $assetType = self::ASSET_TYPE_ALL,
    ): array
    {
        $stats = $this->emptyStats();
        $assetType = $this->normalizeAssetType($assetType);

        $globalToken = $this->resolveGlobalAccessToken();

        if ($this->shouldSyncAdAccounts($assetType)) {
            MetaAdAccount::query()
                ->when($customerId, function ($query) use ($customerId): void {
                    $query->where(function ($innerQuery) use ($customerId): void {
                        $innerQuery->where('customer_id', $customerId)
                            ->orWhereHas('customers', fn ($customers) => $customers->whereKey($customerId));
                    });
                })
                ->whereNotNull('meta_account_id')
                ->orderBy('id')
                ->chunkById(100, function ($accounts) use (&$stats, $queryType, $webhookEvent, $globalToken) {
                    foreach ($accounts as $account) {
                        $stats['accounts_checked']++;

                        try {
                            $this->syncAdAccount($account, $queryType, $webhookEvent, $globalToken);
                            $stats['histories_created']++;
                        } catch (\Throwable $exception) {
                            $stats['errors'][] = [
                                'type' => 'ad_account',
                                'id' => $account->id,
                                'meta_account_id' => $account->meta_account_id,
                                'message' => $exception->getMessage(),
                            ];
                        }
                    }
                });
        }

        if ($this->shouldSyncPages($assetType)) {
            MetaPage::query()
                ->when($customerId, fn ($query) => $query->where('customer_id', $customerId))
                ->whereNotNull('meta_page_id')
                ->orderBy('id')
                ->chunkById(100, function ($pages) use (&$stats, $queryType, $webhookEvent, $globalToken) {
                    foreach ($pages as $page) {
                        $stats['pages_checked']++;

                        try {
                            $this->syncPage($page, $queryType, $webhookEvent, $globalToken);
                            $stats['histories_created']++;
                        } catch (\Throwable $exception) {
                            $stats['errors'][] = [
                                'type' => 'page',
                                'id' => $page->id,
                                'meta_page_id' => $page->meta_page_id,
                                'message' => $exception->getMessage(),
                            ];
                        }
                    }
                });
        }

        if ($stats['errors'] !== []) {
            $this->logErrors($customerId, $queryType, $assetType, $stats['errors']);
        }

        return $stats;
    }

    private function emptyStats(): array
    {
        return [
            'accounts_checked' => 0,
            'pages_checked' => 0,
            'histories_created' => 0,
            'errors' => [],
            'skipped' => 0,
        ];
    }

    private function normalizeAssetType(string $assetType): string
    {
        return in_array($assetType, [
            self::ASSET_TYPE_ALL,
            self::ASSET_TYPE_AD_ACCOUNTS,
            self::ASSET_TYPE_PAGES,
        ], true) ? $assetType : self::ASSET_TYPE_ALL;
    }

    private function shouldSyncAdAccounts(string $assetType): bool
    {
        return in_array($assetType, [self::ASSET_TYPE_ALL, self::ASSET_TYPE_AD_ACCOUNTS], true);
    }

    private function shouldSyncPages(string $assetType): bool
    {
        return in_array($assetType, [self::ASSET_TYPE_ALL, self::ASSET_TYPE_PAGES], true);
    }

    private function logErrors(?int $customerId, string $queryType, string $assetType, array $errors): void
    {
        Log::warning('Meta asset status sync completed with errors.', [
            'customer_id' => $customerId,
            'query_type' => $queryType,
            'asset_type' => $assetType,
            'errors' => array_slice($errors, 0, 10),
        ]);
    }

    private function syncAdAccount(MetaAdAccount $account, string $queryType, ?MetaWebhookEvent $webhookEvent, ?string $globalToken): void
    {
        $previousCode = $this->stringOrNull($account->estado_meta);
        $previousLabel = $this->stringOrNull($account->estado_meta_nombre);

        try {
            if (! $globalToken) {
                throw new RuntimeException('No hay token activo para consultar cuentas publicitarias de Meta.');
            }

            $payload = $this->graph->get(MetaAdAccountId::act((string) $account->meta_account_id), [
                'fields' => 'id,account_id,name,account_status,disable_reason',
                'access_token' => $globalToken,
            ]);

            $newCode = $this->stringOrNull($payload['account_status'] ?? null);
            $newLabel = $this->adAccountStatusLabel($newCode, $payload['disable_reason'] ?? null);

            $account->forceFill([
                'name' => $payload['name'] ?? $account->name,
                'estado_meta' => $newCode,
                'estado_meta_nombre' => $newLabel,
                'estado_meta_checked_at' => now(),
                'estado_meta_payload' => $payload,
                'estado_meta_last_error' => null,
            ])->saveQuietly();

            $this->recordAdAccountHistory($account, $previousCode, $previousLabel, $newCode, $newLabel, $queryType, $webhookEvent, $payload);
        } catch (\Throwable $exception) {
            $this->markAdAccountError($account, $previousCode, $previousLabel, $queryType, $webhookEvent, $exception);

            throw $exception;
        }
    }

    private function syncPage(MetaPage $page, string $queryType, ?MetaWebhookEvent $webhookEvent, ?string $globalToken): void
    {
        $previousCode = $this->stringOrNull($page->estado_meta);
        $previousLabel = $this->stringOrNull($page->estado_meta_nombre);
        $token = $page->page_access_token ?: $globalToken;

        try {
            if (! $token) {
                throw new RuntimeException('No hay token utilizable para consultar la pagina de Meta.');
            }

            $payload = $this->graph->get((string) $page->meta_page_id, [
                'fields' => 'id,name,is_published',
                'access_token' => $token,
            ]);

            [$newCode, $newLabel] = $this->pageStatus($payload);

            $page->forceFill([
                'name' => $payload['name'] ?? $page->name,
                'estado_meta' => $newCode,
                'estado_meta_nombre' => $newLabel,
                'estado_meta_checked_at' => now(),
                'estado_meta_payload' => $payload,
                'estado_meta_last_error' => null,
            ])->saveQuietly();

            $this->recordPageHistory($page, $previousCode, $previousLabel, $newCode, $newLabel, $queryType, $webhookEvent, $payload);
        } catch (\Throwable $exception) {
            $this->markPageError($page, $previousCode, $previousLabel, $queryType, $webhookEvent, $exception);

            throw $exception;
        }
    }

    private function recordAdAccountHistory(
        MetaAdAccount $account,
        ?string $previousCode,
        ?string $previousLabel,
        ?string $newCode,
        ?string $newLabel,
        string $queryType,
        ?MetaWebhookEvent $webhookEvent,
        ?array $payload,
        ?string $error = null,
    ): void {
        MetaAdAccountStatusHistory::create([
            'customer_id' => $account->resolveWhatsappLeadCustomerId() ?: $account->customer_id,
            'meta_ad_account_id' => $account->id,
            'meta_webhook_event_id' => $webhookEvent?->id,
            'meta_account_id' => $account->meta_account_id,
            'estado_meta_anterior' => $previousCode,
            'estado_meta_anterior_nombre' => $previousLabel,
            'estado_meta_nuevo' => $newCode,
            'estado_meta_nuevo_nombre' => $newLabel,
            'changed' => $previousCode !== $newCode || $previousLabel !== $newLabel,
            'query_type' => $queryType,
            'consulted_at' => now(),
            'payload' => $payload,
            'error' => $error,
        ]);
    }

    private function recordPageHistory(
        MetaPage $page,
        ?string $previousCode,
        ?string $previousLabel,
        ?string $newCode,
        ?string $newLabel,
        string $queryType,
        ?MetaWebhookEvent $webhookEvent,
        ?array $payload,
        ?string $error = null,
    ): void {
        MetaPageStatusHistory::create([
            'customer_id' => $page->customer_id,
            'meta_page_id' => $page->id,
            'meta_webhook_event_id' => $webhookEvent?->id,
            'meta_page_external_id' => $page->meta_page_id,
            'estado_meta_anterior' => $previousCode,
            'estado_meta_anterior_nombre' => $previousLabel,
            'estado_meta_nuevo' => $newCode,
            'estado_meta_nuevo_nombre' => $newLabel,
            'changed' => $previousCode !== $newCode || $previousLabel !== $newLabel,
            'query_type' => $queryType,
            'consulted_at' => now(),
            'payload' => $payload,
            'error' => $error,
        ]);
    }

    private function markAdAccountError(
        MetaAdAccount $account,
        ?string $previousCode,
        ?string $previousLabel,
        string $queryType,
        ?MetaWebhookEvent $webhookEvent,
        \Throwable $exception,
    ): void {
        $account->forceFill([
            'estado_meta_checked_at' => now(),
            'estado_meta_last_error' => $exception->getMessage(),
        ])->saveQuietly();

        $this->recordAdAccountHistory(
            $account,
            $previousCode,
            $previousLabel,
            $previousCode,
            $previousLabel,
            $queryType,
            $webhookEvent,
            null,
            $exception->getMessage(),
        );
    }

    private function markPageError(
        MetaPage $page,
        ?string $previousCode,
        ?string $previousLabel,
        string $queryType,
        ?MetaWebhookEvent $webhookEvent,
        \Throwable $exception,
    ): void {
        $page->forceFill([
            'estado_meta_checked_at' => now(),
            'estado_meta_last_error' => $exception->getMessage(),
        ])->saveQuietly();

        $this->recordPageHistory(
            $page,
            $previousCode,
            $previousLabel,
            $previousCode,
            $previousLabel,
            $queryType,
            $webhookEvent,
            null,
            $exception->getMessage(),
        );
    }

    private function resolveGlobalAccessToken(): ?string
    {
        return MetaAccessToken::activeByType(MetaAccessToken::TYPE_SYSTEM_ACCESS_TOKEN)?->working_token
            ?: MetaAccessToken::activeByType(MetaAccessToken::TYPE_USER_ACCESS_TOKEN)?->working_token
            ?: MetaAccessToken::activeByType(MetaAccessToken::TYPE_APP_ACCESS_TOKEN)?->working_token;
    }

    private function adAccountStatusLabel(?string $status, mixed $disableReason): ?string
    {
        if ($status === null) {
            return null;
        }

        $label = self::AD_ACCOUNT_STATUS_LABELS[$status] ?? 'Estado Meta '.$status;
        $reason = $this->stringOrNull($disableReason);

        if ($reason !== null && $reason !== '0') {
            $label .= ' (disable_reason '.$reason.')';
        }

        return $label;
    }

    private function pageStatus(array $payload): array
    {
        if (! array_key_exists('is_published', $payload)) {
            return [null, null];
        }

        $published = (bool) $payload['is_published'];

        return [$published ? '1' : '0', $published ? 'Publicada' : 'No publicada'];
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null || $value instanceof Model || is_array($value) || is_object($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}

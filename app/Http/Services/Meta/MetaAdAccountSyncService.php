<?php

namespace App\Http\Services\Meta;

use App\Models\MetaAccessToken;
use App\Models\MetaAdAccount;
use App\Support\MetaAdAccountId;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MetaAdAccountSyncService
{
    private const FIELDS = 'id,account_id,name,account_status,disable_reason';

    private const STATUS_LABELS = [
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

    public function syncAllAvailable(): array
    {
        $stats = [
            'tokens_checked' => 0,
            'accounts_found' => 0,
            'accounts_created' => 0,
            'accounts_updated' => 0,
            'errors' => [],
        ];

        $tokens = $this->tokens();

        if ($tokens->isEmpty()) {
            throw new RuntimeException('No hay tokens Meta generales activos para sincronizar cuentas publicitarias.');
        }

        $seen = [];

        foreach ($tokens as $token) {
            $stats['tokens_checked']++;

            foreach ($this->pathsForToken($token) as $path) {
                try {
                    foreach ($this->fetchAccounts($path, $token) as $item) {
                        $metaAccountId = $this->metaAccountIdFromPayload($item);

                        if ($metaAccountId === null || isset($seen[$metaAccountId])) {
                            continue;
                        }

                        $seen[$metaAccountId] = true;
                        $stats['accounts_found']++;

                        $account = $this->findAccount($metaAccountId);
                        $exists = $account !== null;
                        $account ??= new MetaAdAccount(['meta_account_id' => $metaAccountId]);

                        $account->forceFill([
                            'name' => $item['name'] ?? $account->name,
                            'status' => $account->status ?: 'active',
                            'estado_meta' => $this->stringOrNull($item['account_status'] ?? null),
                            'estado_meta_nombre' => $this->statusLabel($item['account_status'] ?? null, $item['disable_reason'] ?? null),
                            'estado_meta_checked_at' => now(),
                            'estado_meta_payload' => $item,
                            'estado_meta_last_error' => null,
                        ])->save();

                        $stats[$exists ? 'accounts_updated' : 'accounts_created']++;
                    }
                } catch (\Throwable $exception) {
                    $stats['errors'][] = [
                        'token_id' => $token->id,
                        'path' => $path,
                        'message' => $exception->getMessage(),
                    ];

                    Log::warning('Meta ad account sync path failed.', [
                        'meta_access_token_id' => $token->id,
                        'path' => $path,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }
        }

        return $stats;
    }

    private function tokens(): Collection
    {
        return MetaAccessToken::query()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('purpose')
                    ->orWhere('purpose', MetaAccessToken::PURPOSE_GENERAL);
            })
            ->where(function ($query): void {
                $query->whereNotNull('long_lived_token')
                    ->where('long_lived_token', '<>', '')
                    ->orWhere(function ($shortTokenQuery): void {
                        $shortTokenQuery->whereNotNull('short_lived_token')
                            ->where('short_lived_token', '<>', '');
                    });
            })
            ->orderByDesc('id')
            ->get(MetaAccessToken::SYNC_COLUMNS)
            ->sortBy(fn (MetaAccessToken $token): string => sprintf(
                '%d-%d-%010d',
                [
                    MetaAccessToken::TYPE_SYSTEM_ACCESS_TOKEN => 0,
                    MetaAccessToken::TYPE_USER_ACCESS_TOKEN => 1,
                    MetaAccessToken::TYPE_APP_ACCESS_TOKEN => 2,
                ][$token->token_type] ?? 9,
                $token->is_default ? 0 : 1,
                9999999999 - (int) $token->id,
            ))
            ->values();
    }

    private function pathsForToken(MetaAccessToken $token): array
    {
        $paths = ['me/adaccounts'];

        if (filled($token->meta_business_id)) {
            $paths[] = trim((string) $token->meta_business_id).'/owned_ad_accounts';
            $paths[] = trim((string) $token->meta_business_id).'/client_ad_accounts';
        }

        return array_values(array_unique($paths));
    }

    private function fetchAccounts(string $path, MetaAccessToken $token): array
    {
        return $this->graph->paginatedGet($path, [
            'access_token' => $token->working_token,
            'fields' => self::FIELDS,
            'limit' => 500,
        ]);
    }

    private function findAccount(string $metaAccountId): ?MetaAdAccount
    {
        return MetaAdAccount::query()
            ->whereIn('meta_account_id', MetaAdAccountId::candidates($metaAccountId))
            ->orderBy('id')
            ->first();
    }

    private function metaAccountIdFromPayload(array $item): ?string
    {
        $accountId = $this->stringOrNull($item['account_id'] ?? null)
            ?? $this->stringOrNull($item['id'] ?? null);

        if ($accountId === null) {
            return null;
        }

        return MetaAdAccountId::normalize($accountId);
    }

    private function statusLabel(mixed $status, mixed $disableReason): ?string
    {
        $status = $this->stringOrNull($status);

        if ($status === null) {
            return null;
        }

        $label = self::STATUS_LABELS[$status] ?? 'Estado Meta '.$status;
        $reason = $this->stringOrNull($disableReason);

        if ($reason !== null && $reason !== '0') {
            $label .= ' (disable_reason '.$reason.')';
        }

        return $label;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}

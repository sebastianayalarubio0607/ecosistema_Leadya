<?php

namespace App\Console\Commands;

use App\Http\Services\Meta\MetaInsightsSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MetaSyncInsightsYesterday extends Command
{
    protected $signature = 'meta:sync-insights-yesterday {--timezone= : Timezone (ej: America/Bogota)}';
    protected $description = 'Sincroniza Meta Insights del día anterior para todas las cuentas (MetaAdAccount).';

    public function handle(MetaInsightsSyncService $service): int
    {
        $tz = $this->option('timezone') ?: config('app.timezone');

        $this->info("Iniciando sync Meta Insights (ayer) | TZ={$tz} | ".now($tz)->toDateTimeString());

        try {
            $result = $service->syncYesterday($tz);

            $msg = "Sync OK (ayer {$result['date']}): "
                . "{$result['accounts_processed']} cuentas, "
                . "{$result['rows']} filas, "
                . "Insights upsert: {$result['insights_upserted']}, "
                . "Ads: {$result['ads_upserted']}, "
                . "AdSets: {$result['ad_sets_upserted']}, "
                . "Campaigns: {$result['campaigns_upserted']}, "
                . "Accounts: {$result['ad_accounts_upserted']}.";

            if (!empty($result['errors'])) {
                $msg .= " Errores: " . count($result['errors']) . ".";
            }

            $this->info($msg);

            Log::info('Meta scheduled sync completed', [
                'date' => $result['date'] ?? null,
                'accounts_processed' => $result['accounts_processed'] ?? null,
                'rows' => $result['rows'] ?? null,
                'insights_upserted' => $result['insights_upserted'] ?? null,
                'errors_count' => !empty($result['errors']) ? count($result['errors']) : 0,
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Error ejecutando sync: '.$e->getMessage());

            Log::error('Meta scheduled sync failed', [
                'message' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }
}

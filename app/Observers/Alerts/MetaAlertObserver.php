<?php

namespace App\Observers\Alerts;

use App\Models\MetaAdAccount;
use App\Models\MetaAdAccountStatusHistory;
use App\Services\Alerts\MetaAlertObservationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MetaAlertObserver
{
    public function created(MetaAdAccount|MetaAdAccountStatusHistory $model): void
    {
        $this->observe($model, true);
    }

    public function updated(MetaAdAccount $model): void
    {
        if ($model->wasChanged('estado_meta')) {
            $this->observe($model);
        }
    }

    private function observe(MetaAdAccount|MetaAdAccountStatusHistory $model, bool $created = false): void
    {
        // This optional module must never turn a successful Meta sync into a failure.
        try {
            if (! app(\App\Services\Alerts\AlertAvailability::class)->ready()) {
                return;
            }
            $service = app(MetaAlertObservationService::class);
            if ($model instanceof MetaAdAccountStatusHistory) {
                $service->history($model);
            } elseif (! $model->estado_meta_last_error) {
                $service->capture($model, 'import:'.Str::uuid(),
                    $created ? null : $model->getRawOriginal('estado_meta'),
                    $model->estado_meta, $model->estado_meta_checked_at ?? now(), $model->estado_meta_payload ?? []);
            }
        } catch (\Throwable $e) {
            Log::warning('Alert observation deferred to reconciliation.', ['model' => $model::class, 'id' => $model->id, 'exception' => $e::class]);
        }
    }
}

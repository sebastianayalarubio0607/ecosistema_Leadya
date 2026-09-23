<?php

namespace App\Services\Alerts;

use App\Models\Alerts\AlertObservation;
use App\Models\Alerts\AlertType;
use Illuminate\Support\Facades\DB;

class ProcessAlertObservations
{
    public function run(int $limit = 500): int
    {
        $ids = AlertObservation::whereNull('processed_at')->orderBy('observed_at')->orderBy('id')->limit($limit)->pluck('id');
        foreach ($ids as $id) {
            $this->process((int) $id);
        }

        return $ids->count();
    }

    public function process(int $id): void
    {
        DB::transaction(function () use ($id) {
            $observation = AlertObservation::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($observation->processed_at) {
                return;
            }
            $data = $observation->data;
            $key = 'meta:ad_account:'.$data['entity_id'];
            DB::table('alert_source_states')->insertOrIgnore(['source_key' => $key]);
            $state = DB::table('alert_source_states')->where('source_key', $key)->lockForUpdate()->first();
            if (! $state->observed_at || $observation->observed_at->gte($state->observed_at)) {
                $previous = $state->state ?? $data['previous'];
                $data['is_problem'] = $data['current'] !== '1';
                $data['was_healthy'] = $previous === '1';
                $data['message_template'] = 'La cuenta publicitaria {entity_name} ({entity_id}) del cliente {customer_name} cambió de {previous} a {current}.';
                $type = AlertType::where('code', 'cuenta_publicitaria_inactiva')->firstOrFail();
                app(AlertIncidentService::class)->observe($type, 'meta', 'ad_account', $data, $previous, $observation->observed_at);
                DB::table('alert_source_states')->where('source_key', $key)->update([
                    'state' => $data['current'], 'observed_at' => $observation->observed_at,
                ]);
            }
            $observation->update(['processed_at' => now()]);
        }, 3);
    }
}

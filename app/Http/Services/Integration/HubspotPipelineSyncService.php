<?php

namespace App\Http\Services\Integration;

use App\Models\CrmState;
use App\Models\Integration;
use App\Models\Qualification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class HubspotPipelineSyncService
{
    public function syncCrmStates(Integration $integration): array
    {
        $endpoint = $this->pipelinesEndpoint($integration);
        $token = trim((string) $integration->tokent);

        if ($token === '') {
            throw new RuntimeException('La integracion requiere un access_token de HubSpot guardado.');
        }

        $response = Http::acceptJson()->withToken($token)->get($endpoint);

        if (!$response->successful()) {
            Log::warning('HUBSPOT CRM STATES SYNC API ERROR', [
                'integration_id' => $integration->id,
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 1000),
            ]);

            throw new RuntimeException('HubSpot no permitio sincronizar los pipelines de negocios.');
        }

        $stages = collect($response->json('results', []))
            ->flatMap(function ($pipeline) {
                $pipelineName = trim((string) ($pipeline['label'] ?? $pipeline['displayOrder'] ?? $pipeline['id'] ?? ''));

                return collect($pipeline['stages'] ?? [])->map(function ($stage) use ($pipelineName) {
                    return [
                        'id' => (string) ($stage['id'] ?? ''),
                        'name' => trim((string) ($stage['label'] ?? $stage['id'] ?? '')),
                        'pipeline_name' => $pipelineName,
                    ];
                });
            })
            ->filter(fn (array $stage) => $stage['id'] !== '' && $stage['name'] !== '')
            ->values();

        if ($stages->isEmpty()) {
            throw new RuntimeException('HubSpot respondio sin etapas de negocio para sincronizar.');
        }

        $qualificationId = Qualification::query()->orderBy('id')->value('id');
        if ($qualificationId === null) {
            throw new RuntimeException('No existe una Qualification disponible para crear CRM States.');
        }

        $created = 0;
        $updated = 0;
        $prefix = $integration->crmIdPrefix();

        DB::transaction(function () use ($stages, $qualificationId, $prefix, &$created, &$updated) {
            foreach ($stages as $stage) {
                $crmState = CrmState::query()->firstOrNew(['id' => $prefix . '-' . $stage['id']]);
                $exists = $crmState->exists;
                $crmState->name = Str::limit($stage['name'] . ' | ' . $stage['pipeline_name'], 255, '');

                if (!$exists) {
                    $crmState->qualification = $qualificationId;
                }

                $crmState->save();
                $exists ? $updated++ : $created++;
            }
        });

        return compact('created', 'updated');
    }

    private function pipelinesEndpoint(Integration $integration): string
    {
        $url = parse_url((string) $integration->url_negocio);
        $baseUrl = isset($url['scheme'], $url['host'])
            ? $url['scheme'] . '://' . $url['host'] . (isset($url['port']) ? ':' . $url['port'] : '')
            : '';

        if ($baseUrl === '') {
            throw new RuntimeException('No existe una url_negocio valida para sincronizar los pipelines de HubSpot.');
        }

        return $baseUrl . '/crm/v3/pipelines/deals';
    }
}

<?php

namespace App\Http\Services\Integration;

use App\Models\CrmState;
use App\Models\Integration;
use App\Models\Pipeline;
use App\Models\Qualification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class HubspotPipelineSyncService
{
    public function getPipelines(Integration $integration): array
    {
        $endpoint = $this->pipelinesEndpoint($integration);
        $token = trim((string) $integration->tokent);
        if ($token === '') {
            throw new RuntimeException('La integración requiere un access_token de HubSpot guardado.');
        }

        $response = Http::acceptJson()->withToken($token)->get($endpoint);
        if (! $response->successful()) {
            Log::warning('HUBSPOT CRM PIPELINES API ERROR', [
                'integration_id' => $integration->id,
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 1000),
            ]);

            throw new RuntimeException('HubSpot no permitió consultar sus pipelines de negocios.');
        }

        return collect($response->json('results', []))
            ->filter(fn ($pipeline) => is_array($pipeline) && filled($pipeline['id'] ?? null))
            ->map(function (array $pipeline): array {
                $stages = collect($pipeline['stages'] ?? [])
                    ->filter(fn ($stage) => is_array($stage) && filled($stage['id'] ?? null))
                    ->sortBy(fn (array $stage, int $index) => is_numeric($stage['displayOrder'] ?? null)
                        ? (float) $stage['displayOrder']
                        : $index)
                    ->values()
                    ->all();

                return [
                    'id' => (string) $pipeline['id'],
                    'name' => trim((string) ($pipeline['label'] ?? $pipeline['id'])),
                    'stages' => $stages,
                ];
            })
            ->values()
            ->all();
    }

    public function syncCrmStates(Integration $integration, string $pipelineId): array
    {
        $pipelineId = trim($pipelineId);
        if ($pipelineId === '') {
            throw new RuntimeException('Selecciona un pipeline de HubSpot antes de sincronizar sus etapas.');
        }

        $pipeline = collect($this->getPipelines($integration))->firstWhere('id', $pipelineId);
        if (! is_array($pipeline)) {
            throw new RuntimeException('El pipeline seleccionado ya no existe o no está disponible para esta integración.');
        }

        $stages = collect($pipeline['stages'])
            ->filter(fn ($stage) => is_array($stage) && filled($stage['id'] ?? null))
            ->values();
        if ($stages->isEmpty()) {
            throw new RuntimeException('El pipeline seleccionado no tiene etapas para sincronizar.');
        }

        $qualificationId = Qualification::query()->orderBy('id')->value('id');
        if ($qualificationId === null) {
            throw new RuntimeException('No existe una Qualification disponible para crear CRM States.');
        }

        $created = 0;
        $updated = 0;
        $prefix = $integration->crmIdPrefix();

        DB::transaction(function () use ($integration, $pipeline, $pipelineId, $stages, $qualificationId, $prefix, &$created, &$updated): void {
            $localPipeline = Pipeline::query()->updateOrCreate(
                ['integration_id' => $integration->id, 'external_id' => $pipelineId],
                ['name' => $pipeline['name']]
            );

            $stageCount = $stages->count();
            foreach ($stages as $index => $stage) {
                $stageId = (string) $stage['id'];
                $crmState = CrmState::query()->firstOrNew(['id' => $prefix.'-'.$stageId]);
                $exists = $crmState->exists;
                $crmState->name = Str::limit(
                    trim((string) ($stage['label'] ?? $stageId)).' | '.$pipeline['name'],
                    255,
                    ''
                );

                if ($crmState->pipeline_id && (int) $crmState->pipeline_id !== (int) $localPipeline->id) {
                    throw new RuntimeException(sprintf(
                        'La etapa "%s" ya está asociada a otro pipeline. No se modificó para evitar mezclar estados.',
                        $stageId
                    ));
                }
                $crmState->pipeline_id = $localPipeline->id;

                // Keep manual progress values; initialize missing ones using the
                // selected pipeline's stage order for forward-only transitions.
                if (blank($crmState->progress_percentage)) {
                    $crmState->progress_percentage = round((($index + 1) * 100) / ($stageCount + 1), 2);
                }

                if (! $exists) {
                    $crmState->qualification = $qualificationId;
                }

                $crmState->save();
                $exists ? $updated++ : $created++;
            }

            $integration->forceFill(['hubspot_pipeline_id' => $pipelineId])->save();
        });

        return compact('created', 'updated');
    }

    private function pipelinesEndpoint(Integration $integration): string
    {
        $url = parse_url((string) $integration->url_negocio);
        $baseUrl = isset($url['scheme'], $url['host'])
            ? $url['scheme'].'://'.$url['host'].(isset($url['port']) ? ':'.$url['port'] : '')
            : '';

        if ($baseUrl === '') {
            throw new RuntimeException('No existe una url_negocio válida para sincronizar los pipelines de HubSpot.');
        }

        return $baseUrl.'/crm/v3/pipelines/deals';
    }
}

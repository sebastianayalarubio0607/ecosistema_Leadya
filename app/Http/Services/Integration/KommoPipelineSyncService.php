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
use Throwable;

class KommoPipelineSyncService
{
    public function syncCrmStates(Integration $integration): array
    {
        $integration->loadMissing('integrationtype:id,name');

        if (! $this->supports($integration)) {
            throw new RuntimeException('Esta integracion no permite sincronizar tableros de Kommo.');
        }

        $endpoint = $this->pipelinesEndpoint($integration);
        $token = $this->token($integration);

        Log::info('KOMMO PIPELINE CRM STATES SYNC STARTED', [
            'integration_id' => $integration->id,
            'endpoint' => $endpoint,
        ]);

        try {
            $response = Http::acceptJson()
                ->withToken($token)
                ->get($endpoint);
        } catch (Throwable $exception) {
            Log::error('KOMMO PIPELINE CRM STATES SYNC CONNECTION ERROR', [
                'integration_id' => $integration->id,
                'endpoint' => $endpoint,
                'exception' => $exception->getMessage(),
            ]);

            throw new RuntimeException('No fue posible conectarse con Kommo. Revisa la URL y el token guardados.');
        }

        if (! $response->successful()) {
            Log::warning('KOMMO PIPELINE CRM STATES SYNC API ERROR', [
                'integration_id' => $integration->id,
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 1000),
            ]);

            throw new RuntimeException('Kommo no permitio sincronizar los tableros. Revisa la URL, el token y permisos de la integracion.');
        }

        $statuses = $this->extractStatuses($response->json());

        if ($statuses === []) {
            Log::warning('KOMMO PIPELINE CRM STATES SYNC EMPTY RESPONSE', [
                'integration_id' => $integration->id,
                'endpoint' => $endpoint,
            ]);

            throw new RuntimeException('Kommo respondio sin estados para sincronizar.');
        }

        $defaultQualificationId = Qualification::query()
            ->orderBy('id')
            ->value('id');

        if ($defaultQualificationId === null) {
            throw new RuntimeException('No existe una Qualification disponible para crear nuevos CRM States.');
        }

        $prefix = $integration->crmIdPrefix();
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($statuses, $prefix, $defaultQualificationId, &$created, &$updated) {
            foreach ($statuses as $status) {
                $crmStateId = $prefix . '-' . $status['id'];
                $crmStateName = Str::limit($status['name'] . ' | ' . $status['pipeline_name'], 255, '');

                $crmState = CrmState::query()->firstOrNew(['id' => $crmStateId]);
                $exists = $crmState->exists;

                $crmState->name = $crmStateName;

                if (! $exists) {
                    $crmState->qualification = $defaultQualificationId;
                }

                $crmState->save();

                $exists ? $updated++ : $created++;
            }
        });

        Log::info('KOMMO PIPELINE CRM STATES SYNC FINISHED', [
            'integration_id' => $integration->id,
            'created' => $created,
            'updated' => $updated,
        ]);

        return [
            'created' => $created,
            'updated' => $updated,
        ];
    }

    public function supports(Integration $integration): bool
    {
        return in_array($this->normalizeType((string) optional($integration->integrationtype)->name), [
            'kommo',
            'kommopipeline',
        ], true);
    }

    private function pipelinesEndpoint(Integration $integration): string
    {
        $url = rtrim((string) $integration->url, '/');

        if ($url === '') {
            throw new RuntimeException('La integracion requiere una URL de Kommo guardada.');
        }

        return $url . '/api/v4/leads/pipelines';
    }

    private function token(Integration $integration): string
    {
        $token = trim((string) $integration->tokent);

        if (stripos($token, 'bearer ') === 0) {
            $token = trim(substr($token, 7));
        }

        if ($token === '') {
            throw new RuntimeException('La integracion requiere un token de Kommo guardado.');
        }

        return $token;
    }

    private function extractStatuses($json): array
    {
        $pipelines = data_get($json, '_embedded.pipelines', data_get($json, 'pipelines', []));

        return collect(is_array($pipelines) ? $pipelines : [])
            ->flatMap(function ($pipeline) {
                $pipelineId = (string) data_get($pipeline, 'id');
                $pipelineName = trim((string) data_get($pipeline, 'name'));
                $pipelineLabel = $pipelineName !== '' ? $pipelineName : $pipelineId;
                $statuses = data_get($pipeline, '_embedded.statuses', data_get($pipeline, 'statuses', []));

                return collect(is_array($statuses) ? $statuses : [])
                    ->map(function ($status) use ($pipelineLabel) {
                        $statusId = (string) data_get($status, 'id');
                        $statusName = trim((string) data_get($status, 'name'));

                        return [
                            'id' => $statusId,
                            'name' => $statusName !== '' ? $statusName : $statusId,
                            'pipeline_name' => $pipelineLabel,
                        ];
                    });
            })
            ->filter(fn (array $status) => $status['id'] !== '' && $status['name'] !== '' && $status['pipeline_name'] !== '')
            ->values()
            ->all();
    }

    private function normalizeType(string $type): string
    {
        $normalized = Str::of($type)
            ->ascii()
            ->lower()
            ->replace([' ', '-'], '_')
            ->replaceMatches('/_+/', '_')
            ->trim('_')
            ->toString();

        return $normalized === 'kommo_pipeline' ? 'kommopipeline' : $normalized;
    }
}

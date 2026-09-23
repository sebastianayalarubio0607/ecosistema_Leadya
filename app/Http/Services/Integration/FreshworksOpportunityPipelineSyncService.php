<?php

namespace App\Http\Services\Integration;

use App\Models\CrmState;
use App\Models\FreshworksOpportunityStage;
use App\Models\Integration;
use App\Models\Qualification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class FreshworksOpportunityPipelineSyncService
{
    public function __construct(private readonly FreshworksOpportunityIntegrationService $freshworks) {}

    public function syncCrmStates(Integration $integration, string $pipelineId): array
    {
        $integration->loadMissing('integrationtype:id,name');
        if (! $this->freshworks->supports($integration)) {
            throw new RuntimeException('Esta integración no permite sincronizar estados de Freshworks-Oportunidad.');
        }

        $pipeline = collect($this->freshworks->getPipelines($integration))->firstWhere('id', $pipelineId);
        if (! is_array($pipeline)) {
            throw new RuntimeException('El pipeline seleccionado no está disponible en Freshworks.');
        }

        $stages = collect($this->freshworks->getStages($integration, $pipelineId));
        if ($stages->isEmpty()) {
            throw new RuntimeException('El pipeline seleccionado no tiene stages para sincronizar.');
        }

        $qualificationId = Qualification::query()->orderBy('id')->value('id');
        if ($qualificationId === null) {
            throw new RuntimeException('No existe una Qualification disponible para crear CRM States.');
        }

        $prefix = $integration->crmIdPrefix();
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($integration, $pipelineId, $stages, $pipeline, $qualificationId, $prefix, &$created, &$updated): void {
            foreach ($stages as $stage) {
                $state = CrmState::query()->firstOrNew(['id' => $prefix.'-'.$stage['id']]);
                $exists = $state->exists;
                $state->name = self::crmStateName($stage['name'], $pipeline['name']);
                if (! $exists) {
                    $state->qualification = $qualificationId;
                }
                $state->save();
                FreshworksOpportunityStage::query()->updateOrCreate([
                    'integration_id' => $integration->id,
                    'pipeline_id' => (string) $pipelineId,
                    'stage_id' => (string) $stage['id'],
                ], [
                    'crm_state_id' => $state->id,
                    'pipeline_name' => $pipeline['name'],
                    'stage_name' => $stage['name'],
                ]);
                $exists ? $updated++ : $created++;
            }
        });

        return compact('created', 'updated');
    }

    public static function crmStateName(string $stageName, string $pipelineName): string
    {
        return Str::limit(trim($stageName).' | '.trim($pipelineName), 255, '');
    }
}

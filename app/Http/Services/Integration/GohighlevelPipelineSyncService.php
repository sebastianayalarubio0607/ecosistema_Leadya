<?php

namespace App\Http\Services\Integration;

use App\Models\CrmState;
use App\Models\Integration;
use App\Models\Qualification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class GohighlevelPipelineSyncService
{
    public function __construct(private readonly GohighlevelService $gohighlevel) {}

    public function syncCrmStates(Integration $integration, string $pipelineId): array
    {
        $integration->loadMissing('integrationtype:id,name');
        if (! $this->gohighlevel->supportsOpportunity($integration)) {
            throw new RuntimeException('Esta integración no permite sincronizar estados de GoHighLevel.');
        }

        $locationId = trim((string) $integration->location_id);
        if ($locationId === '') {
            throw new RuntimeException('Configura locationId antes de sincronizar los estados.');
        }

        $pipeline = collect($this->gohighlevel->getPipelines($integration, $locationId))
            ->firstWhere('id', $pipelineId);
        if (! is_array($pipeline)) {
            throw new RuntimeException('El pipeline seleccionado ya no existe o no está disponible para esta integración.');
        }

        $stages = collect($pipeline['stages'] ?? [])
            ->filter(fn ($stage) => is_array($stage) && filled($stage['id'] ?? null) && filled($stage['name'] ?? null))
            ->values();
        if ($stages->isEmpty()) {
            throw new RuntimeException('El pipeline seleccionado no tiene stages para sincronizar.');
        }

        $qualificationId = Qualification::query()->orderBy('id')->value('id');
        if ($qualificationId === null) {
            throw new RuntimeException('No existe una Qualification disponible para crear nuevos CRM States.');
        }

        $prefix = $integration->crmIdPrefix();
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($stages, $pipeline, $qualificationId, $prefix, &$created, &$updated): void {
            foreach ($stages as $stage) {
                $crmState = CrmState::query()->firstOrNew([
                    'id' => $prefix.'-'.(string) $stage['id'],
                ]);
                $exists = $crmState->exists;
                $crmState->name = self::crmStateName((string) $stage['name'], (string) $pipeline['name']);

                if (! $exists) {
                    $crmState->qualification = $qualificationId;
                }

                $crmState->save();
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

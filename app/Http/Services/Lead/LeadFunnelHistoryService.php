<?php

namespace App\Http\Services\Lead;

use App\Models\Funnel;
use App\Models\Lead;
use App\Models\LeadFunnelHistory;
use Illuminate\Support\Facades\DB;

class LeadFunnelHistoryService
{
    private const LEADS_FUNNEL_ID = 5;

    private const LEADS_FUNNEL_NAME = 'Leads';

    public function recordInitialLead(Lead $lead): ?LeadFunnelHistory
    {
        return $this->record($lead, $this->resolveFunnelIdForLead($lead));
    }

    /**
     * Crea un registro solo si no existe ya la combinacion lead + funnel.
     * Si existe, actualiza updated_at para registrar la actividad reciente.
     */
    public function recordIfFunnelChanged(Lead $lead): ?LeadFunnelHistory
    {
        return $this->record($lead, $this->resolveFunnelIdForLead($lead));
    }

    private function record(Lead $lead, int $newFunnelId): ?LeadFunnelHistory
    {
        $existing = LeadFunnelHistory::query()
            ->where('lead_id', $lead->id)
            ->where('funnel_id', $newFunnelId)
            ->first();

        if ($existing) {
            $existing->touch();

            return $existing;
        }

        return LeadFunnelHistory::create([
            'lead_id' => $lead->id,
            'funnel_id' => $newFunnelId,
        ]);
    }

    /**
     * Resuelve funnel_id desde:
     * lead->crm_state -> crm_state.qualification -> qualification.funnel_id
     * Si crm_state null/vacio o no se resuelve funnel => usa funnel 5 "Leads".
     */
    public function resolveFunnelIdForLead(Lead $lead): int
    {
        $crmStateId = (string) ($lead->crm_state ?? '');

        if ($crmStateId === '') {
            return $this->ensureLeadsFunnelId();
        }

        $funnelId = DB::table('crm_state as cs')
            ->leftJoin('qualification as q', 'q.id', '=', 'cs.qualification')
            ->where('cs.id', $crmStateId)
            ->value('q.funnel_id');

        if (!$funnelId) {
            return $this->ensureLeadsFunnelId();
        }

        return (int) $funnelId;
    }

    private function ensureLeadsFunnelId(): int
    {
        $funnel = Funnel::query()->find(self::LEADS_FUNNEL_ID);

        if ($funnel) {
            if (mb_strtolower(trim((string) $funnel->name)) !== mb_strtolower(self::LEADS_FUNNEL_NAME)) {
                $funnel->forceFill(['name' => self::LEADS_FUNNEL_NAME])->save();
            }

            return self::LEADS_FUNNEL_ID;
        }

        Funnel::unguarded(function (): void {
            Funnel::query()->create([
                'id' => self::LEADS_FUNNEL_ID,
                'name' => self::LEADS_FUNNEL_NAME,
            ]);
        });

        return self::LEADS_FUNNEL_ID;
    }
}

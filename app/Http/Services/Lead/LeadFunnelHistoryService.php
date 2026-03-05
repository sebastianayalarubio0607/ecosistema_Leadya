<?php

namespace App\Http\Services\Lead;

use App\Models\Funnel;
use App\Models\Lead;
use App\Models\LeadFunnelHistory;
use Illuminate\Support\Facades\DB;

class LeadFunnelHistoryService
{
    /**
     * Crea un registro SOLO si el funnel actual del lead es diferente
     * al último funnel registrado en el histórico de ese lead.
     */
    public function recordIfFunnelChanged(Lead $lead): ?LeadFunnelHistory
    {
        $newFunnelId = $this->resolveFunnelIdForLead($lead);

        $last = LeadFunnelHistory::query()
            ->where('lead_id', $lead->id)
            ->latest('id')
            ->first();

        if ($last && (int) $last->funnel_id === (int) $newFunnelId) {
            return null;
        }

        return LeadFunnelHistory::create([
            'lead_id' => $lead->id,
            'funnel_id' => $newFunnelId,
        ]);
    }

    /**
     * Resuelve funnel_id desde:
     * lead->crm_state -> crm_state.qualification -> qualification.funnel_id
     * Si crm_state null/vacío o no se resuelve funnel => usa/crea funnel "Lead".
     */
    public function resolveFunnelIdForLead(Lead $lead): int
    {
        $crmStateId = (string) ($lead->crm_state ?? '');

        if ($crmStateId === '') {
            return $this->ensureLeadFunnelId();
        }

        $funnelId = DB::table('crm_state as cs')
            ->leftJoin('qualification as q', 'q.id', '=', 'cs.qualification')
            ->where('cs.id', $crmStateId)
            ->value('q.funnel_id');

        if (!$funnelId) {
            return $this->ensureLeadFunnelId();
        }

        return (int) $funnelId;
    }

    private function ensureLeadFunnelId(): int
    {
        $funnel = Funnel::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower('Lead')])
            ->first();

        if (!$funnel) {
            $funnel = Funnel::create([
                'name' => 'Lead',
            ]);
        }

        return (int) $funnel->id;
    }
}
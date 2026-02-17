<?php

namespace App\Http\Services;

use App\Models\Lead;
use App\Models\MetaAdInsight;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LeadDashboardMetricsService
{
    public function getLeadsLast7DaysCount(
        ?int $customerId,
        ?int $integrationId,
        string $sessionId,
        array $filters
    ): array {
        $filters = $this->normalizeFilters($filters);

        $key = $this->makeKey($customerId, $integrationId, $sessionId, $filters);

        return Cache::remember($key, now()->addSeconds(60), function () use ($customerId, $integrationId, $filters) {
            $from = now()->subDays(7);
            $to   = now();

            $leadTable = (new Lead())->getTable();

            $base = Lead::query()->whereBetween("{$leadTable}.created_at", [$from, $to]);
            $base = $this->applyBaseFilters($base, $customerId, $integrationId, $filters, $leadTable);

            // 1) Totales
            $qTotals = clone $base;
            $qTotals = $this->applyDimensionFilters($qTotals, $filters, $leadTable, true, true, true, true);

            $totalCount = (int) (clone $qTotals)->count();

            $managedCount = (int) (clone $qTotals)
                ->whereNotNull("{$leadTable}.crm_state")
                ->where("{$leadTable}.crm_state", '!=', '')
                ->count();

            $pendingCount = (int) (clone $qTotals)
                ->where(function ($qq) use ($leadTable) {
                    $qq->whereNull("{$leadTable}.crm_state")
                       ->orWhere("{$leadTable}.crm_state", '');
                })
                ->count();

            // 2) Fuente (NO aplica campaign_origin)
            $qChannels = clone $base;
            $qChannels = $this->applyDimensionFilters($qChannels, $filters, $leadTable, false, true, true, true);

            $channels = (clone $qChannels)
                ->selectRaw("
                    COALESCE(NULLIF(MIN({$leadTable}.campaign_origin), ''), '__NULL__') as campaign_origin,
                    COUNT(*) as total
                ")
                ->groupByRaw("COALESCE(NULLIF({$leadTable}.campaign_origin, ''), '__NULL__')")
                ->orderByDesc('total')
                ->limit(10)
                ->pluck('total', 'campaign_origin')
                ->toArray();

            $nullChannelsCount = (int) (clone $qChannels)
                ->where(function ($qq) use ($leadTable) {
                    $qq->whereNull("{$leadTable}.campaign_origin")
                       ->orWhere("{$leadTable}.campaign_origin", '');
                })
                ->count();

            if ($nullChannelsCount > 0) {
                $channels['__NULL__'] = $nullChannelsCount;
            }

            // 3) Medio (NO aplica plataforma)
            $qPlatforms = clone $base;
            $qPlatforms = $this->applyDimensionFilters($qPlatforms, $filters, $leadTable, true, false, true, true);

            $platforms = (clone $qPlatforms)
                ->selectRaw("
                    COALESCE(NULLIF(MIN({$leadTable}.plataforma), ''), '__NULL__') as plataforma,
                    COUNT(*) as total
                ")
                ->groupByRaw("COALESCE(NULLIF({$leadTable}.plataforma, ''), '__NULL__')")
                ->orderByDesc('total')
                ->limit(10)
                ->pluck('total', 'plataforma')
                ->toArray();

            $nullPlatformsCount = (int) (clone $qPlatforms)
                ->where(function ($qq) use ($leadTable) {
                    $qq->whereNull("{$leadTable}.plataforma")
                       ->orWhere("{$leadTable}.plataforma", '');
                })
                ->count();

            if ($nullPlatformsCount > 0) {
                $platforms['__NULL__'] = $nullPlatformsCount;
            }

            // 4) CRM States (NO aplica crm_state)
            $qCrmStates = clone $base;
            $qCrmStates = $this->applyDimensionFilters($qCrmStates, $filters, $leadTable, true, true, false, true);

            // FIX CRÍTICO: no alias "id"
            $crmStates = (clone $qCrmStates)
                ->whereNotNull("{$leadTable}.crm_state")
                ->where("{$leadTable}.crm_state", '!=', '')
                ->leftJoin('crm_state as cs', 'cs.id', '=', "{$leadTable}.crm_state")
                ->selectRaw("
                    {$leadTable}.crm_state as crm_state_id,
                    COALESCE(cs.name, {$leadTable}.crm_state) as name,
                    COUNT(*) as total
                ")
                ->groupBy("{$leadTable}.crm_state", 'cs.name')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($r) => [
                    'id' => (string) $r->crm_state_id,
                    'name' => (string) $r->name,
                    'count' => (int) $r->total,
                ])
                ->toArray();

            // 5) Qualifications (NO aplica qualification)
            $qQualifications = clone $base;
            $qQualifications = $this->applyDimensionFilters($qQualifications, $filters, $leadTable, true, true, true, false);

            $qualifications = (clone $qQualifications)
                ->whereNotNull("{$leadTable}.crm_state")
                ->where("{$leadTable}.crm_state", '!=', '')
                ->leftJoin('crm_state as csq', 'csq.id', '=', "{$leadTable}.crm_state")
                ->leftJoin('qualification as ql', 'ql.id', '=', 'csq.qualification')
                ->selectRaw("
                    ql.id as id,
                    COALESCE(ql.name, 'Sin cualificación') as name,
                    COUNT(*) as total
                ")
                ->groupBy('ql.id', 'ql.name')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($r) => [
                    'id' => $r->id !== null ? (int) $r->id : null,
                    'name' => (string) $r->name,
                    'count' => (int) $r->total,
                ])
                ->toArray();

            // ==============================
            // ✅ NUEVO: FUNNELS + CALIFICADOS + VENTAS
            // ==============================
            [$funnelTable, $qualFunnelFk] = $this->resolveFunnelJoinInfo();

            $funnels = [];
            $noFunnelCount = 0;

            $qualifiedCount = 0;
            $salesCount = 0;
            $qualifiedFunnelId = null;
            $salesFunnelId = null;

            if ($funnelTable && $qualFunnelFk) {
                // Leads por funnel (incluye null como "Sin Funnel")
                $qFunnels = clone $base;
                $qFunnels = $this->applyDimensionFilters($qFunnels, $filters, $leadTable, true, true, true, true);

                $rows = (clone $qFunnels)
                    ->leftJoin('crm_state as csf', 'csf.id', '=', "{$leadTable}.crm_state")
                    ->leftJoin('qualification as qlf', 'qlf.id', '=', 'csf.qualification')
                    ->leftJoin("{$funnelTable} as fn", 'fn.id', '=', "qlf.{$qualFunnelFk}")
                    ->selectRaw("
                        fn.id as funnel_id,
                        COALESCE(fn.name, '') as name,
                        COUNT(*) as total
                    ")
                    ->groupBy('fn.id', 'fn.name')
                    ->orderByDesc('total')
                    ->get();

                foreach ($rows as $r) {
                    if ($r->funnel_id === null) {
                        $noFunnelCount = (int) $r->total;
                        continue;
                    }
                    $funnels[] = [
                        'id' => (string) $r->funnel_id,
                        'name' => (string) $r->name,
                        'count' => (int) $r->total,
                    ];
                }

                // Funnel "Calificados" y "Ventas" (por nombre)
                $qualifiedFunnelId = $this->findFunnelIdByName($funnelTable, 'Oportunidades');
                $salesFunnelId     = $this->findFunnelIdByName($funnelTable, 'Ventas');

                if ($qualifiedFunnelId !== null) {
                    $qualifiedCount = $this->countLeadsByFunnelId($base, $filters, $leadTable, $funnelTable, $qualFunnelFk, $qualifiedFunnelId);
                }
                if ($salesFunnelId !== null) {
                    $salesCount = $this->countLeadsByFunnelId($base, $filters, $leadTable, $funnelTable, $qualFunnelFk, $salesFunnelId);
                }
            }

            // ✅ NUEVO: Spend (Costo) Meta Ads
            $metaSpend = $this->getMetaSpendLast7Days($customerId, $integrationId, $filters, $from, $to);

            return [
                'count'         => $totalCount,
                'managed_count' => $managedCount,
                'pending_count' => $pendingCount,
                'spend'         => $metaSpend,
                'channels'      => $channels,
                'platforms'     => $platforms,
                'crm_states'    => $crmStates,
                'qualifications'=> $qualifications,

                // NUEVO
                'funnels'            => $funnels,
                'no_funnel_count'    => $noFunnelCount,
                'qualified_count'    => $qualifiedCount,
                'qualified_funnel_id'=> $qualifiedFunnelId,
                'sales_count'        => $salesCount,
                'sales_funnel_id'    => $salesFunnelId,

                'calculated_at' => now()->toISOString(),
                'window_days'   => 7,
                'filters'       => $filters,
            ];
        });
    }

    /**
     * ✅ Tabla por grupo (cards + gráficos)
     */
    public function getLeadsForGroupLast7Days(
        ?int $customerId,
        ?int $integrationId,
        array $filters,
        string $groupType,
        string $groupId,
        int $perPage = 20
    ): LengthAwarePaginator {
        return $this->getLeadsForGroupLast7DaysQuery(
            $customerId,
            $integrationId,
            $filters,
            $groupType,
            $groupId
        )->paginate($perPage);
    }

    /**
     * ✅ Query sin paginar (para Export)
     */
    public function getLeadsForGroupLast7DaysQuery(
        ?int $customerId,
        ?int $integrationId,
        array $filters,
        string $groupType,
        string $groupId
    ): Builder {
        $filters = $this->normalizeFilters($filters);

        $from = now()->subDays(7);
        $to   = now();

        $leadTable = (new Lead())->getTable();

        $q = Lead::query()->whereBetween("{$leadTable}.created_at", [$from, $to]);
        $q = $this->applyBaseFilters($q, $customerId, $integrationId, $filters, $leadTable);

        // Aplica filtros del dashboard excepto la dimensión del grupo
        $applyChannel = $groupType !== 'campaign_origin';
        $applyPlatform= $groupType !== 'plataforma';
        $applyCrm     = $groupType !== 'crm_state';
        $applyQual    = $groupType !== 'qualification';

        $q = $this->applyDimensionFilters($q, $filters, $leadTable, $applyChannel, $applyPlatform, $applyCrm, $applyQual);

        // Filtro por grupo (excepto funnel, que se filtra después del join)
        if ($groupType === 'campaign_origin') {
            if ($groupId === '__NULL__') {
                $q->where(function ($qq) use ($leadTable) {
                    $qq->whereNull("{$leadTable}.campaign_origin")
                       ->orWhere("{$leadTable}.campaign_origin", '');
                });
            } else {
                $q->where("{$leadTable}.campaign_origin", $groupId);
            }
        }

        if ($groupType === 'plataforma') {
            if ($groupId === '__NULL__') {
                $q->where(function ($qq) use ($leadTable) {
                    $qq->whereNull("{$leadTable}.plataforma")
                       ->orWhere("{$leadTable}.plataforma", '');
                });
            } else {
                $q->where("{$leadTable}.plataforma", $groupId);
            }
        }

        if ($groupType === 'crm_state') {
            if ($groupId === '__NULL__') {
                $q->where(function ($qq) use ($leadTable) {
                    $qq->whereNull("{$leadTable}.crm_state")
                       ->orWhere("{$leadTable}.crm_state", '');
                });
            } else {
                $q->where("{$leadTable}.crm_state", $groupId);
            }
        }

        if ($groupType === 'qualification') {
            $q->whereNotNull("{$leadTable}.crm_state")
              ->where("{$leadTable}.crm_state", '!=', '');

            if ($groupId === '__NULL__') {
                $q->whereIn("{$leadTable}.crm_state", function ($sub) {
                    $sub->from('crm_state')->select('id')->whereNull('qualification');
                });
            } else {
                $q->whereIn("{$leadTable}.crm_state", function ($sub) use ($groupId) {
                    $sub->from('crm_state')->select('id')->where('qualification', (int) $groupId);
                });
            }
        }

        // joins para mostrar nombres
        $q->leftJoin('crm_state as cs', 'cs.id', '=', "{$leadTable}.crm_state")
          ->leftJoin('qualification as ql', 'ql.id', '=', 'cs.qualification');

        // ✅ NUEVO: join funnel solo si groupType=funnel (no afecta otros)
        if ($groupType === 'funnel') {
            [$funnelTable, $qualFunnelFk] = $this->resolveFunnelJoinInfo();
            if ($funnelTable && $qualFunnelFk) {
                $q->leftJoin("{$funnelTable} as fn", 'fn.id', '=', "ql.{$qualFunnelFk}");

                if ($groupId === '__NULL__') {
                    $q->whereNull('fn.id');
                } else {
                    $q->where('fn.id', $groupId);
                }
            } else {
                // si no existe esquema de funnel, no hay resultados útiles para ese groupType
                $q->whereRaw('1=0');
            }
        }

        $q->select("{$leadTable}.*")
          ->selectRaw("
              COALESCE(NULLIF(cs.name,''), NULLIF({$leadTable}.crm_state,''), 'Sin Estado') as crm_state_name
          ")
          ->selectRaw("
              COALESCE(NULLIF(ql.name,''), 'Sin Cualificación') as qualification_name
          ")
          ->orderByDesc("{$leadTable}.created_at");

        return $q;
    }

    public function resolveGroupLabel(string $groupType, string $groupId): string
    {
        if ($groupType === 'crm_state') {
            if ($groupId === '__NULL__') return 'Sin Estado';
            $name = DB::table('crm_state')->where('id', $groupId)->value('name');
            return $name ? (string) $name : $groupId;
        }

        if ($groupType === 'qualification') {
            if ($groupId === '__NULL__') return 'Sin Cualificación';
            $name = DB::table('qualification')->where('id', (int) $groupId)->value('name');
            return $name ? (string) $name : $groupId;
        }

        if ($groupType === 'campaign_origin') {
            return $groupId === '__NULL__' ? 'Sin Fuente' : $groupId;
        }

        if ($groupType === 'plataforma') {
            return $groupId === '__NULL__' ? 'Sin Medio' : $groupId;
        }

        // ✅ NUEVO: funnel
        if ($groupType === 'funnel') {
            if ($groupId === '__NULL__') return 'Sin Funnel';

            [$funnelTable] = $this->resolveFunnelJoinInfo();
            if (!$funnelTable) return $groupId;

            $name = DB::table($funnelTable)->where('id', $groupId)->value('name');
            return $name ? (string) $name : $groupId;
        }

        return 'Leads';
    }

    // =========================
    // Helpers Funnel
    // =========================
    private function resolveFunnelJoinInfo(): array
    {
        $funnelTable = null;
        if (Schema::hasTable('funnels')) $funnelTable = 'funnels';
        elseif (Schema::hasTable('funnel')) $funnelTable = 'funnel';

        if (!$funnelTable) return [null, null];

        // FK en qualification
        $fk = null;
        foreach (['funnel_id', 'funnel'] as $candidate) {
            if (Schema::hasColumn('qualification', $candidate)) {
                $fk = $candidate;
                break;
            }
        }

        return [$funnelTable, $fk];
    }

    private function findFunnelIdByName(string $funnelTable, string $name): ?string
    {
        // match case-insensitive por name
        $id = DB::table($funnelTable)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->value('id');

        return $id !== null ? (string) $id : null;
    }

    private function countLeadsByFunnelId(
        Builder $base,
        array $filters,
        string $leadTable,
        string $funnelTable,
        string $qualFunnelFk,
        string $funnelId
    ): int {
        $q = clone $base;
        $q = $this->applyDimensionFilters($q, $filters, $leadTable, true, true, true, true);

        return (int) $q
            ->leftJoin('crm_state as csf2', 'csf2.id', '=', "{$leadTable}.crm_state")
            ->leftJoin('qualification as qlf2', 'qlf2.id', '=', 'csf2.qualification')
            ->leftJoin("{$funnelTable} as fn2", 'fn2.id', '=', "qlf2.{$qualFunnelFk}")
            ->where('fn2.id', $funnelId)
            ->count();
    }

    private function applyBaseFilters(
        Builder $q,
        ?int $customerId,
        ?int $integrationId,
        array $filters,
        string $leadTable
    ): Builder {
        if ($customerId) {
            $q->where("{$leadTable}.customer_id", $customerId);
        }
        if ($integrationId) {
            $q->where("{$leadTable}.integration_id", $integrationId);
        }
        if (!empty($filters['lenguaje'])) {
            $q->where("{$leadTable}.lenguaje", $filters['lenguaje']);
        }
        if (!empty($filters['geo'])) {
            $q->where("{$leadTable}.geo", $filters['geo']);
        }
        return $q;
    }

    private function applyDimensionFilters(
        Builder $q,
        array $filters,
        string $leadTable,
        bool $applyChannel,
        bool $applyPlatform,
        bool $applyCrmState,
        bool $applyQualification
    ): Builder {
        // Fuente
        if ($applyChannel && !empty($filters['campaign_origin'])) {
            $values = array_values((array) $filters['campaign_origin']);
            $wantNull = in_array('__NULL__', $values, true);
            $real = array_values(array_filter($values, fn ($v) => $v !== '__NULL__' && $v !== null && $v !== ''));

            $q->where(function ($qq) use ($leadTable, $wantNull, $real) {
                if ($wantNull) {
                    $qq->orWhereNull("{$leadTable}.campaign_origin")
                       ->orWhere("{$leadTable}.campaign_origin", '');
                }
                if (!empty($real)) {
                    $qq->orWhereIn("{$leadTable}.campaign_origin", $real);
                }
            });
        }

        // Medio
        if ($applyPlatform && !empty($filters['plataforma'])) {
            if ($filters['plataforma'] === '__NULL__') {
                $q->where(function ($qq) use ($leadTable) {
                    $qq->whereNull("{$leadTable}.plataforma")
                       ->orWhere("{$leadTable}.plataforma", '');
                });
            } else {
                $q->where("{$leadTable}.plataforma", $filters['plataforma']);
            }
        }

        // CRM State
        if ($applyCrmState && !empty($filters['crm_state'])) {
            if ($filters['crm_state'] === '__NULL__') {
                $q->where(function ($qq) use ($leadTable) {
                    $qq->whereNull("{$leadTable}.crm_state")
                       ->orWhere("{$leadTable}.crm_state", '');
                });
            } else {
                $q->where("{$leadTable}.crm_state", $filters['crm_state']);
            }
        }

        // Qualification
        if ($applyQualification && !empty($filters['qualification'])) {
            $q->whereIn("{$leadTable}.crm_state", function ($sub) use ($filters) {
                $sub->from('crm_state')
                    ->select('id')
                    ->where('qualification', (int) $filters['qualification']);
            });
        }

        return $q;
    }

    // =========================
    // ✅ Spend (MetaAdInsight)
    // =========================
    private function getMetaSpendLast7Days(
        ?int $customerId,
        ?int $integrationId,
        array $filters,
        $from,
        $to
    ): float {
        // Ventana (misma que Leads)
        $fromDate = $from->toDateString();
        $toDate   = $to->toDateString();

        $q = MetaAdInsight::query()
            ->whereBetween('date_start', [$fromDate, $toDate]);

        // Filtro por customer/integration (si aplica)
        if ($customerId !== null || $integrationId !== null) {
            $q->whereHas('ad.adSet.campaign.account', function ($qq) use ($customerId, $integrationId) {
                if ($customerId !== null) {
                    $qq->where('customer_id', $customerId);
                }

                // integration_id es opcional (depende del esquema)
                if ($integrationId !== null && Schema::hasColumn($qq->getModel()->getTable(), 'integration_id')) {
                    $qq->where('integration_id', $integrationId);
                }
            });
        }

        // Intento de "amarrar" Spend a los filtros del dashboard:
        // Si el Lead tiene un campo de campaña (id o nombre), usamos los leads filtrados
        // para seleccionar campañas y sumar el spend solo de esas campañas.
        $leadTable = (new Lead())->getTable();
        [$leadCampaignCol, $metaCampaignCol] = $this->resolveLeadToMetaCampaignMapping($leadTable);

        if ($leadCampaignCol && $metaCampaignCol) {
            $sub = Lead::query()->whereBetween("{$leadTable}.created_at", [$from, $to]);

            $sub = $this->applyBaseFilters($sub, $customerId, $integrationId, $filters, $leadTable);
            $sub = $this->applyDimensionFilters($sub, $filters, $leadTable, true, true, true, true);

            $sub->whereNotNull("{$leadTable}.{$leadCampaignCol}")
                ->where("{$leadTable}.{$leadCampaignCol}", '!=', '');

            $q->whereIn($metaCampaignCol, $sub->select("{$leadTable}.{$leadCampaignCol}")->distinct());
        } else {
            // Fallback: si no podemos mapear campañas, aplicamos una heurística mínima
            // para evitar mostrar spend cuando los filtros claramente no corresponden a Meta.
            if (!$this->filtersCouldIncludeMetaSpend($filters)) {
                return 0.0;
            }
        }

        $sum = $q->sum('spend');

        return (float) $sum;
    }

    private function resolveLeadToMetaCampaignMapping(string $leadTable): array
    {
        // Preferimos ID (más estable)
        $idCandidates = [
            'meta_campaign_id',
            'campaign_id',
            'utm_campaign_id',
            'campaignid',
        ];

        foreach ($idCandidates as $col) {
            if (Schema::hasColumn($leadTable, $col)) {
                return [$col, 'campaign_id'];
            }
        }

        // Si no hay IDs, intentamos por nombre
        $nameCandidates = [
            'campaign_name',
            'utm_campaign',
            'utm_campaign_name',
            'campaign',
        ];

        foreach ($nameCandidates as $col) {
            if (Schema::hasColumn($leadTable, $col)) {
                return [$col, 'campaign_name'];
            }
        }

        return [null, null];
    }

    private function filtersCouldIncludeMetaSpend(array $filters): bool
    {
        $tokens = ['meta', 'facebook', 'fb', 'instagram', 'ig', 'whatsapp', 'messenger'];

        $hasMetaToken = function ($value) use ($tokens): bool {
            if ($value === null) return false;
            $v = mb_strtolower((string) $value);
            foreach ($tokens as $t) {
                if (str_contains($v, $t)) return true;
            }
            return false;
        };

        // plataforma puede venir como string
        if (!empty($filters['plataforma'])) {
            if ($filters['plataforma'] === '__NULL__') return false;
            if (!$hasMetaToken($filters['plataforma'])) return false;
        }

        // campaign_origin puede venir como array (o string)
        if (!empty($filters['campaign_origin'])) {
            $values = is_array($filters['campaign_origin']) ? $filters['campaign_origin'] : [$filters['campaign_origin']];
            $values = array_values(array_filter($values, fn ($v) => $v !== null && $v !== ''));

            if (empty($values)) return true;

            // si alguno sugiere Meta, permitimos
            foreach ($values as $v) {
                if ($v === '__NULL__') continue;
                if ($hasMetaToken($v)) return true;
            }

            // si había filtros y ninguno sugiere Meta
            return false;
        }

        return true;
    }

    private function makeKey(?int $customerId, ?int $integrationId, string $sessionId, array $filters): string
    {
        $hash = hash('sha256', json_encode([
            'customer_id' => $customerId,
            'integration_id' => $integrationId,
            'session_id' => $sessionId,
            'filters' => $filters,
            'window' => 7,
        ]));
        return "dash:leads:last7d:{$hash}";
    }

    private function normalizeFilters(array $filters): array
    {
        $allowed = Arr::only($filters, [
            'campaign_origin',
            'plataforma',
            'lenguaje',
            'geo',
            'crm_state',
            'qualification',
        ]);

        if (array_key_exists('campaign_origin', $allowed)) {
            $arr = array_values((array) $allowed['campaign_origin']);
            $arr = array_filter($arr, fn ($v) => $v !== null && $v !== '');
            sort($arr);
            if (empty($arr)) unset($allowed['campaign_origin']);
            else $allowed['campaign_origin'] = $arr;
        }

        foreach (['plataforma','crm_state','qualification','lenguaje','geo'] as $k) {
            if (isset($allowed[$k]) && ($allowed[$k] === null || $allowed[$k] === '')) {
                unset($allowed[$k]);
            }
        }

        ksort($allowed);
        return $allowed;
    }
}

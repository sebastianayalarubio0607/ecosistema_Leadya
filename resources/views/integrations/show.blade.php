@extends('meta.layout')

@section('title', $integration->name)
@section('subtitle', 'Detalle de la integración')

@php
    $normalizedIntegrationType = \Illuminate\Support\Str::of((string) optional($integration->integrationtype)->name)
        ->ascii()
        ->lower()
        ->replace([' ', '-'], '_')
        ->replaceMatches('/_+/', '_')
        ->trim('_')
        ->toString();

    $canSyncKommoBoards = in_array($normalizedIntegrationType, ['kommo', 'kommopipeline', 'kommo_pipeline'], true);
@endphp

@section('header_actions')
    @if($canSyncKommoBoards)
        <form method="POST" action="{{ route('integrations.kommo.sync-boards', $integration) }}">
            @csrf
            <button class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10" type="submit">
                Sincronizar tableros
            </button>
        </form>
    @endif

    <a href="{{ route('integrations.edit', $integration) }}"
       class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">
        Editar
    </a>
    <a href="{{ route('integrations.index') }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        Volver
    </a>
@endsection

@section('content')
    <div class="space-y-6">
        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-6 text-white/80">
            <div class="grid gap-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <div class="text-sm text-white/50">Cliente</div>
                        <div class="mt-1">{{ $integration->customer->name ?? '—' }}</div>
                    </div>

                    <div>
                        <div class="text-sm text-white/50">Tipo</div>
                        <div class="mt-1">{{ $integration->integrationtype->name ?? '—' }}</div>
                    </div>
                </div>

                <div>
                    <div class="text-sm text-white/50">URL</div>
                    <div class="mt-1 break-all">{{ $integration->url }}</div>
                </div>

                <div>
                    <div class="text-sm text-white/50">Public Key</div>
                    <div class="mt-1 rounded-xl border border-white/10 bg-slate-900/60 p-3 font-mono text-xs break-all">{{ $integration->public_key }}</div>
                    @if($integration->public_key)
                        <div class="mt-3 text-sm text-white/50">URL CRM State</div>
                        <div class="mt-1 rounded-xl border border-white/10 bg-slate-900/60 p-3 font-mono text-xs break-all">
                            {{ url('/api/integrations/leads/crm-state/' . $integration->public_key) }}
                        </div>
                    @endif
                </div>

                <div>
                    <div class="text-sm text-white/50">Status</div>
                    <div class="mt-1">
                        <span class="px-2 py-1 rounded-lg text-xs border {{ (int) $integration->status === 1 ? 'bg-emerald-500/10 border-emerald-300/20 text-emerald-200' : 'bg-white/10 border-white/10 text-white/70' }}">
                            {{ (int) $integration->status === 1 ? 'Activo' : 'Inactivo' }}
                        </span>
                    </div>
                </div>

                <div>
                    <div class="text-sm text-white/50">Prioridad</div>
                    <div class="mt-1">
                        <span class="px-2 py-1 rounded-lg bg-indigo-500/10 border border-indigo-300/20 text-indigo-100 text-xs">
                            {{ $integration->priority ?? 100 }}
                        </span>
                    </div>
                </div>

                <div>
                    <div class="text-sm text-white/50">Descripcion</div>
                    <div class="mt-1">{{ $integration->description ?: '—' }}</div>
                </div>

                <div class="pt-2 flex gap-2">
                    <form method="POST"
                          action="{{ route('integrations.destroy', $integration) }}"
                          onsubmit="return confirm('¿Seguro que deseas eliminar esta integración?');">
                        @csrf
                        @method('DELETE')
                        <button class="px-4 py-2 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 border border-rose-300/20 text-white" type="submit">
                            Eliminar
                        </button>
                    </form>
                </div>
            </div>
        </div>

        @if (strtolower((string) optional($integration->integrationtype)->name) === 'monday')
            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-6 text-white/80">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Boards Monday</h3>
                        <p class="text-sm text-white/50">Sincroniza boards, activa las que deban recibir leads y configura su condición, grupo y mapeos.</p>
                    </div>

                    <form method="POST" action="{{ route('integrations.monday.sync-boards', $integration) }}">
                        @csrf
                        <button class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10" type="submit">
                            Sincronizar boards
                        </button>
                    </form>
                </div>

                <div class="overflow-x-auto rounded-xl border border-white/10">
                    <table class="min-w-full text-sm">
                        <thead class="bg-white/5 text-white/70">
                            <tr>
                                <th class="text-left px-3 py-2">Board</th>
                                <th class="text-left px-3 py-2">Activa</th>
                                <th class="text-left px-3 py-2">Condicion</th>
                                <th class="text-left px-3 py-2">Grupo</th>
                                <th class="text-left px-3 py-2">Sync</th>
                                <th class="text-left px-3 py-2 w-56">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10 text-white/80">
                            @forelse($integration->mondayBoards ?? [] as $board)
                                <tr class="hover:bg-white/5">
                                    <td class="px-3 py-2">
                                        <div class="font-semibold">{{ $board->name }}</div>
                                        <div class="text-xs font-mono text-white/50">{{ $board->monday_board_id }}</div>
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="px-2 py-1 rounded-lg text-xs border {{ $board->status ? 'bg-emerald-500/10 border-emerald-300/20 text-emerald-200' : 'bg-white/10 border-white/10 text-white/70' }}">
                                            {{ $board->status ? 'Si' : 'No' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-sm">
                                        @if($board->condition_lead_field && $board->condition_expected_value)
                                            {{ $board->condition_lead_field }} = {{ $board->condition_expected_value }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-sm">{{ $board->monday_group_id ?: '—' }}</td>
                                    <td class="px-3 py-2 text-sm">
                                        <div>Grupos: {{ $board->groups_count ?? 0 }}</div>
                                        <div>Columnas: {{ $board->columns_count ?? 0 }}</div>
                                        <div>Mapeos: {{ $board->column_mappings_count ?? 0 }}</div>
                                    </td>
                                    <td class="px-3 py-2">
                                        <a class="inline-flex px-3 py-1.5 rounded-lg bg-indigo-500/20 hover:bg-indigo-500/30 border border-white/10 text-xs"
                                           href="{{ route('integrations.monday.boards.edit', [$integration, $board]) }}">
                                            Configurar
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-3 py-8 text-center text-white/60" colspan="6">Aun no hay boards sincronizadas para esta integracion.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if (in_array($normalizedIntegrationType, ['kommopipeline', 'kommo_pipeline'], true))
            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-6 text-white/80">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-white">KommoPipeline</h3>
                    <p class="text-sm text-white/50">Payload dinamico, fallback y condicionalidades configuradas.</p>
                </div>

                <div class="grid gap-4">
                    <div>
                        <div class="text-sm text-white/50">Token</div>
                        <div class="mt-1 font-mono text-sm">{{ \App\Support\SensitiveValue::mask($integration->tokent) }}</div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <div class="text-sm text-white/50">Pipeline por defecto</div>
                            <div class="mt-1">{{ $integration->kommo_pipeline_default_pipeline_name ?: $integration->kommo_pipeline_default_pipeline_id ?: '—' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-white/50">Status por defecto</div>
                            <div class="mt-1">{{ $integration->kommo_pipeline_default_status_name ?: $integration->kommo_pipeline_default_status_id ?: '—' }}</div>
                        </div>
                    </div>

                    <div>
                        <div class="text-sm text-white/50">Payload JSON</div>
                        <pre class="mt-1 overflow-x-auto rounded-xl border border-white/10 bg-slate-900/60 p-3 text-xs text-white/80">{{ $integration->body ?: '—' }}</pre>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-white/10">
                        <table class="min-w-full text-sm">
                            <thead class="bg-white/5 text-white/70">
                                <tr>
                                    <th class="text-left px-3 py-2">Campo Lead</th>
                                    <th class="text-left px-3 py-2">Valor esperado</th>
                                    <th class="text-left px-3 py-2">Pipeline</th>
                                    <th class="text-left px-3 py-2">Status</th>
                                    <th class="text-left px-3 py-2">Activa</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/10">
                                @forelse($integration->kommoPipelineConditions ?? [] as $condition)
                                    <tr class="hover:bg-white/5">
                                        <td class="px-3 py-2 font-mono text-xs">{{ $condition->lead_field }}</td>
                                        <td class="px-3 py-2">{{ $condition->expected_value }}</td>
                                        <td class="px-3 py-2">{{ $condition->pipeline_name ?: $condition->pipeline_id }}</td>
                                        <td class="px-3 py-2">{{ $condition->status_name ?: $condition->status_id }}</td>
                                        <td class="px-3 py-2">{{ $condition->active ? 'Si' : 'No' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="px-3 py-8 text-center text-white/60" colspan="5">Aun no hay condicionalidades configuradas.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        @if ($normalizedIntegrationType === 'freshworks')
            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-6 text-white/80">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-white">Freshworks</h3>
                    <p class="text-sm text-white/50">Configuracion del payload y normalizacion de variables.</p>
                </div>

                <div class="grid gap-4">
                    <div>
                        <div class="text-sm text-white/50">Token</div>
                        <div class="mt-1 font-mono text-sm">{{ \App\Support\SensitiveValue::mask($integration->tokent) }}</div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <div class="text-sm text-white/50">territory_id</div>
                            <div class="mt-1">{{ $integration->territory_id ?: '—' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-white/50">owner_id</div>
                            <div class="mt-1">{{ $integration->owner_id ?: '—' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-white/50">City</div>
                            <div class="mt-1">{{ $integration->city ?: '—' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-white/50">lead_source_id</div>
                            <div class="mt-1">{{ $integration->lead_source_id ?: '—' }}</div>
                        </div>
                    </div>

                    <div>
                        <div class="text-sm text-white/50">custom_field</div>
                        <pre class="mt-1 overflow-x-auto rounded-xl border border-white/10 bg-slate-900/60 p-3 text-xs text-white/80">{{ $integration->custom_field ?: '—' }}</pre>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-white/10">
                        <table class="min-w-full text-sm">
                            <thead class="bg-white/5 text-white/70">
                                <tr>
                                    <th class="text-left px-3 py-2">Variable payload</th>
                                    <th class="text-left px-3 py-2">Campo Lead</th>
                                    <th class="text-left px-3 py-2">Valor esperado</th>
                                    <th class="text-left px-3 py-2">Valor a enviar</th>
                                    <th class="text-left px-3 py-2">Activa</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/10">
                                @forelse($integration->freshworksVariableMappings ?? [] as $mapping)
                                    <tr class="hover:bg-white/5">
                                        <td class="px-3 py-2 font-mono text-xs">{{ $mapping->target_variable }}</td>
                                        <td class="px-3 py-2 font-mono text-xs">{{ $mapping->lead_field }}</td>
                                        <td class="px-3 py-2">{{ $mapping->expected_value }}</td>
                                        <td class="px-3 py-2">{{ $mapping->mapped_value ?? 'Valor original del lead' }}</td>
                                        <td class="px-3 py-2">{{ $mapping->active ? 'Si' : 'No' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="px-3 py-8 text-center text-white/60" colspan="5">Aun no hay mapeos de variables Freshworks configurados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        @if (in_array($normalizedIntegrationType, ['atom', 'zoho', 'salesforce', 'monday', 'lety', 'hubspot', 'gohighlevel'], true))
            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-6 text-white/80">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-white">Mapeo de variables</h3>
                    <p class="text-sm text-white/50">Normalizaciones configuradas para llaves del payload de esta integracion.</p>
                </div>

                <div class="overflow-x-auto rounded-xl border border-white/10">
                    <table class="min-w-full text-sm">
                        <thead class="bg-white/5 text-white/70">
                            <tr>
                                <th class="text-left px-3 py-2">Variable payload</th>
                                <th class="text-left px-3 py-2">Campo Lead</th>
                                <th class="text-left px-3 py-2">Valor esperado</th>
                                <th class="text-left px-3 py-2">Valor a enviar</th>
                                <th class="text-left px-3 py-2">Activa</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10">
                            @forelse($integration->variableMappings ?? [] as $mapping)
                                <tr class="hover:bg-white/5">
                                    <td class="px-3 py-2 font-mono text-xs">{{ $mapping->target_variable }}</td>
                                    <td class="px-3 py-2 font-mono text-xs">{{ $mapping->lead_field }}</td>
                                    <td class="px-3 py-2">{{ $mapping->expected_value }}</td>
                                    <td class="px-3 py-2">{{ $mapping->mapped_value ?? 'Valor original del lead' }}</td>
                                    <td class="px-3 py-2">{{ $mapping->active ? 'Si' : 'No' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-3 py-8 text-center text-white/60" colspan="5">Aun no hay mapeos de variables configurados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if (\Illuminate\Support\Str::of((string) optional($integration->integrationtype)->name)->ascii()->lower()->replace([' ', '-'], '_')->replaceMatches('/_+/', '_')->trim('_')->toString() === 'atom')
            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-6 text-white/80">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-white">Atom</h3>
                    <p class="text-sm text-white/50">Webhooks y condiciones configuradas para envio condicional.</p>
                </div>

                <div class="grid gap-4">
                    <div>
                        <div class="text-sm text-white/50">Token</div>
                        <div class="mt-1 font-mono text-sm">{{ \App\Support\SensitiveValue::mask($integration->tokent) }}</div>
                    </div>

                    <div>
                        <div class="text-sm text-white/50">Body JSON</div>
                        <pre class="mt-1 overflow-x-auto rounded-xl border border-white/10 bg-slate-900/60 p-3 text-xs text-white/80">{{ $integration->body ?: '—' }}</pre>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-white/10">
                        <table class="min-w-full text-sm">
                            <thead class="bg-white/5 text-white/70">
                                <tr>
                                    <th class="text-left px-3 py-2">Webhook</th>
                                    <th class="text-left px-3 py-2">URL</th>
                                    <th class="text-left px-3 py-2">Por defecto</th>
                                    <th class="text-left px-3 py-2">Activo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/10">
                                @forelse($integration->atomWebhooks ?? [] as $webhook)
                                    <tr class="hover:bg-white/5">
                                        <td class="px-3 py-2">{{ $webhook->name }}</td>
                                        <td class="px-3 py-2 break-all">{{ $webhook->url }}</td>
                                        <td class="px-3 py-2">{{ $webhook->is_default ? 'Si' : 'No' }}</td>
                                        <td class="px-3 py-2">{{ $webhook->active ? 'Si' : 'No' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="px-3 py-8 text-center text-white/60" colspan="4">Aun no hay webhooks Atom configurados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-white/10">
                        <table class="min-w-full text-sm">
                            <thead class="bg-white/5 text-white/70">
                                <tr>
                                    <th class="text-left px-3 py-2">Campo Lead</th>
                                    <th class="text-left px-3 py-2">Valor esperado</th>
                                    <th class="text-left px-3 py-2">Webhook</th>
                                    <th class="text-left px-3 py-2">Activa</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/10">
                                @forelse($integration->atomConditions ?? [] as $condition)
                                    <tr class="hover:bg-white/5">
                                        <td class="px-3 py-2 font-mono text-xs">{{ $condition->lead_field }}</td>
                                        <td class="px-3 py-2">{{ $condition->expected_value }}</td>
                                        <td class="px-3 py-2">{{ $condition->webhook->name ?? '—' }}</td>
                                        <td class="px-3 py-2">{{ $condition->active ? 'Si' : 'No' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="px-3 py-8 text-center text-white/60" colspan="4">Aun no hay condiciones Atom configuradas.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        @if (\Illuminate\Support\Str::of((string) optional($integration->integrationtype)->name)->ascii()->lower()->replace([' ', '-'], '_')->replaceMatches('/_+/', '_')->trim('_')->toString() === 'lety')
            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-6 text-white/80">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-white">Lety</h3>
                    <p class="text-sm text-white/50">Webhooks form-urlencoded y condiciones configuradas para envio condicional.</p>
                </div>

                <div class="grid gap-4">
                    <div class="overflow-x-auto rounded-xl border border-white/10">
                        <table class="min-w-full text-sm">
                            <thead class="bg-white/5 text-white/70">
                                <tr>
                                    <th class="text-left px-3 py-2">Webhook</th>
                                    <th class="text-left px-3 py-2">URL</th>
                                    <th class="text-left px-3 py-2">Payload</th>
                                    <th class="text-left px-3 py-2">Activo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/10">
                                @forelse($integration->letyWebhooks ?? [] as $webhook)
                                    <tr class="hover:bg-white/5">
                                        <td class="px-3 py-2">{{ $webhook->name }}</td>
                                        <td class="px-3 py-2 break-all">{{ $webhook->url }}</td>
                                        <td class="px-3 py-2">
                                            <pre class="max-w-xl overflow-x-auto rounded-xl border border-white/10 bg-slate-900/60 p-3 text-xs text-white/80">{{ $webhook->body }}</pre>
                                        </td>
                                        <td class="px-3 py-2">{{ $webhook->active ? 'Si' : 'No' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="px-3 py-8 text-center text-white/60" colspan="4">Aun no hay webhooks Lety configurados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-white/10">
                        <table class="min-w-full text-sm">
                            <thead class="bg-white/5 text-white/70">
                                <tr>
                                    <th class="text-left px-3 py-2">Campo Lead</th>
                                    <th class="text-left px-3 py-2">Valor esperado</th>
                                    <th class="text-left px-3 py-2">Webhook</th>
                                    <th class="text-left px-3 py-2">Activa</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/10">
                                @forelse($integration->letyConditions ?? [] as $condition)
                                    <tr class="hover:bg-white/5">
                                        <td class="px-3 py-2 font-mono text-xs">{{ $condition->lead_field }}</td>
                                        <td class="px-3 py-2">{{ $condition->expected_value }}</td>
                                        <td class="px-3 py-2">{{ $condition->webhook->name ?? '—' }}</td>
                                        <td class="px-3 py-2">{{ $condition->active ? 'Si' : 'No' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="px-3 py-8 text-center text-white/60" colspan="4">Aun no hay condiciones Lety configuradas.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection

@extends('meta.layout')

@section('title', 'CRM States')
@section('subtitle', 'Estados del CRM y asignacion de conversiones')

@section('header_actions')
    <a href="{{ route('google-ads.conversion-jobs.index') }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        Google Ads Jobs
    </a>
    <a href="{{ route('crmstates.create') }}"
       class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">
        + Nuevo
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <div class="md:col-span-3">
                <label class="block mb-1 text-white/70">Buscar</label>
                <input name="q" value="{{ $q ?? request('q') }}"
                       class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
                       placeholder="Buscar por ID o nombre">
            </div>

            <div class="md:col-span-2">
                <label class="block mb-1 text-white/70">Nombre</label>
                <input name="name" value="{{ $name ?? request('name') }}"
                       class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
                       placeholder="Nombre CRM State">
            </div>

            <div class="md:col-span-2">
                <label class="block mb-1 text-white/70">Cliente</label>
                <select name="customer_id" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">Todos</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((string) ($customerId ?? request('customer_id')) === (string) $customer->id)>
                            {{ $customer->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="block mb-1 text-white/70">Tipo</label>
                <select name="integrationtype_id" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">Todos</option>
                    @foreach($types as $type)
                        <option value="{{ $type->id }}" @selected((string) ($typeId ?? request('integrationtype_id')) === (string) $type->id)>
                            {{ $type->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-3">
                <label class="block mb-1 text-white/70">Integration</label>
                <select name="integration_id" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">Todas</option>
                    @foreach($integrations as $integration)
                        <option value="{{ $integration->id }}" @selected((string) ($integrationId ?? request('integration_id')) === (string) $integration->id)>
                            {{ $integration->name }} @if($integration->customer) / {{ $integration->customer->name }} @endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="block mb-1 text-white/70">Sin gestionar</label>
                <select name="unmanaged" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">Todos</option>
                    <option value="1" @selected((string) ($unmanaged ?? request('unmanaged')) === '1')>Si</option>
                    <option value="0" @selected((string) ($unmanaged ?? request('unmanaged')) === '0')>No</option>
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="block mb-1 text-white/70">Qualification</label>
                <select name="qualification" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">Todas</option>
                    @foreach($qualifications as $qualification)
                        <option value="{{ $qualification->id }}" @selected((string) ($qualificationId ?? request('qualification')) === (string) $qualification->id)>
                            {{ $qualification->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="block mb-1 text-white/70">Meta Event</label>
                <select name="meta_event_id" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">Todos</option>
                    <option value="none" @selected((string) ($metaEventId ?? request('meta_event_id')) === 'none')>Sin Meta Event</option>
                    @foreach($metaEvents as $metaEvent)
                        <option value="{{ $metaEvent->id }}" @selected((string) ($metaEventId ?? request('meta_event_id')) === (string) $metaEvent->id)>
                            {{ $metaEvent->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="block mb-1 text-white/70">Google Ads</label>
                <select name="google_ads_conversion_enabled" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">Todos</option>
                    <option value="1" @selected((string) ($googleAdsEnabled ?? request('google_ads_conversion_enabled')) === '1')>Activa</option>
                    <option value="0" @selected((string) ($googleAdsEnabled ?? request('google_ads_conversion_enabled')) === '0')>Inactiva</option>
                </select>
            </div>

            <div class="md:col-span-4 flex gap-2">
                <button class="w-full px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
                    Filtrar
                </button>
                <a href="{{ route('crmstates.index') }}"
                   class="w-full text-center px-4 py-2 rounded-xl bg-zinc-950/25 hover:bg-white/10 text-white border border-white/10">
                    Limpiar
                </a>
            </div>
        </form>

        <div class="overflow-x-auto rounded-xl border border-white/10">
            <table class="min-w-full text-sm">
                <thead class="bg-white/5 text-white/70">
                    <tr>
                        <th class="text-left px-3 py-2">ID</th>
                        <th class="text-left px-3 py-2">Nombre</th>
                        <th class="text-left px-3 py-2">Cliente</th>
                        <th class="text-left px-3 py-2">Tipo</th>
                        <th class="text-left px-3 py-2">Integration</th>
                        <th class="text-left px-3 py-2">Sin gestionar</th>
                        <th class="text-left px-3 py-2">Qualification</th>
                        <th class="text-left px-3 py-2">Meta Event</th>
                        <th class="text-left px-3 py-2">Google Ads</th>
                        <th class="text-left px-3 py-2 w-56">Acciones</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-white/10 text-white/80">
                    @forelse($crmstates as $it)
                        <tr class="hover:bg-white/5">
                            <td class="px-3 py-2">
                                {{ $it->id }}
                                <div class="text-xs text-white/50">
                                    Leads: {{ method_exists($it, 'leads') ? $it->leads()->count() : '--' }}
                                </div>
                            </td>
                            <td class="px-3 py-2">{{ $it->name }}</td>
                            <td class="px-3 py-2">{{ $it->matchedIntegration?->customer?->name ?? '--' }}</td>
                            <td class="px-3 py-2">{{ $it->matchedIntegration?->integrationtype?->name ?? '--' }}</td>
                            <td class="px-3 py-2">
                                {{ $it->matchedIntegration?->name ?? '--' }}
                                <div class="text-xs text-white/50">
                                    Prefix: {{ $it->integration_prefix ?? '--' }}
                                </div>
                            </td>
                            <td class="px-3 py-2">{{ $it->unmanaged ? 'Si' : 'No' }}</td>
                            <td class="px-3 py-2">{{ $it->qualificationModel?->name ?? '--' }}</td>
                            <td class="px-3 py-2">{{ $it->metaEvent?->nombre ?? '--' }}</td>
                            <td class="px-3 py-2">
                                <span class="px-2 py-1 rounded-lg text-xs border {{ $it->google_ads_conversion_enabled ? 'bg-emerald-500/10 border-emerald-300/20 text-emerald-200' : 'bg-white/10 border-white/10 text-white/70' }}">
                                    {{ $it->google_ads_conversion_enabled ? 'Activa' : 'Inactiva' }}
                                </span>
                                <div class="text-xs text-white/50 mt-1">
                                    @if($it->googleAdsConversions->isNotEmpty())
                                        {{ $it->googleAdsConversions->count() }} conversion(es)
                                    @else
                                        {{ $it->google_ads_conversion_action_name ?? '--' }}
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <a class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs"
                                       href="{{ route('crmstates.show', $it) }}">Ver</a>

                                    <a class="px-3 py-1.5 rounded-lg bg-indigo-500/20 hover:bg-indigo-500/30 border border-white/10 text-xs"
                                       href="{{ route('crmstates.edit', $it) }}">Editar</a>

                                    <form action="{{ route('crmstates.destroy', $it) }}" method="POST">
                                        @csrf @method('DELETE')
                                        <button class="px-3 py-1.5 rounded-lg bg-rose-500/20 hover:bg-rose-500/30 border border-rose-300/20 text-xs"
                                                onclick="return confirm('Eliminar CRM State?')">
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-3 py-8 text-center text-white/60">No hay CRM States.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $crmstates->links() }}</div>
    </div>
@endsection

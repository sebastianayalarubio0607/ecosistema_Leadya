@php
    $data = $dashboard;
@endphp

<x-app-layout>
    @vite('resources/js/lead-management.js')

    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-xl font-semibold text-indigo-200">Gestión de leads</h1>
            <a href="{{ route('dashboard.general-leads') }}" class="inline-flex min-h-10 items-center rounded-xl border border-white/10 bg-white/10 px-4 py-2 text-sm text-white/80 hover:bg-white/15">Ver Dashboard General</a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-[1800px] space-y-6 p-4 sm:p-6">
        <section class="grid grid-cols-1 gap-4 xl:grid-cols-12">
            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 p-4 backdrop-blur xl:col-span-3">
                <p class="text-sm text-white/60">Cliente Seleccionado</p>
                <h2 class="mt-2 text-2xl font-bold text-white">{{ $data['header']['customer'] }}</h2>
                <p class="mt-3 text-xs font-semibold text-white/50">Periodo Consultado</p>
                <p class="mt-1 text-sm text-white/80">{{ $data['header']['period'] }}</p>
            </div>

            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 p-4 backdrop-blur xl:col-span-9">
                <h2 class="sr-only">Filtros</h2>
                <form method="GET" action="{{ route('lead-management.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
                    @foreach([
                        ['customer_id', 'Cliente', $data['filters']['customers']],
                        ['integration_id', 'Integración', $data['filters']['integrations']],
                        ['source', 'Source', $data['filters']['sources']],
                        ['campaign_origin', 'Origen', $data['filters']['origins']],
                        ['plataforma', 'Medio', $data['filters']['types']],
                        ['crm_state', 'Estado CRM', $data['filters']['crm_states']],
                        ['qualification', 'Calificación', $data['filters']['qualifications']],
                        ['lenguaje', 'Lenguaje', $data['filters']['languages']],
                        ['geo', 'Geo', $data['filters']['geos']],
                    ] as [$name, $label, $options])
                        <div>
                            <label for="{{ $name }}" class="mb-1 block text-sm text-white/70">{{ $label }}</label>
                            <select id="{{ $name }}" name="{{ $name }}" class="w-full rounded-xl border-white/10 bg-slate-900/60 text-white">
                                @foreach($options as $option)
                                    <option value="{{ $option['value'] }}" @selected($option['selected'])>{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                    <div>
                        <label for="from" class="mb-1 block text-sm text-white/70">Desde</label>
                        <input id="from" type="datetime-local" name="from" value="{{ $data['header']['from'] }}" max="{{ $data['header']['now'] }}" class="w-full rounded-xl border-white/10 bg-slate-900/60 text-white">
                    </div>
                    <div>
                        <label for="to" class="mb-1 block text-sm text-white/70">Hasta</label>
                        <input id="to" type="datetime-local" name="to" value="{{ $data['header']['to'] }}" max="{{ $data['header']['now'] }}" class="w-full rounded-xl border-white/10 bg-slate-900/60 text-white">
                    </div>
                    <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-4 xl:col-span-5">
                        <button class="min-h-11 rounded-xl border border-white/10 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/15">Filtrar</button>
                        <a href="{{ $data['filters']['clear_url'] }}" class="inline-flex min-h-11 items-center rounded-xl border border-white/10 bg-zinc-950/25 px-4 py-2 text-sm font-semibold text-white/80 hover:bg-white/10">Limpiar Filtros</a>
                    </div>
                </form>
            </div>
        </section>

        @if(! $table['show'])
            <section class="rounded-2xl border border-white/10 bg-zinc-950/25 p-6 text-center backdrop-blur">
                <h2 class="text-lg font-semibold text-white">Selecciona los filtros para consultar leads</h2>
                <p class="mt-2 text-sm text-white/60">La tabla se mostrará después de aplicar la configuración de filtros.</p>
            </section>
        @else
            <section class="rounded-2xl border border-white/10 bg-zinc-950/25 p-4 backdrop-blur" data-lead-management-table>
                <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-lg font-semibold text-white">Leads</h2>
                    <p class="text-xs text-white/50">Los cambios de estado CRM y valor se guardan automáticamente.</p>
                </div>

                <div class="overflow-x-auto rounded-xl border border-white/10">
                    <table class="min-w-full text-xs">
                        <thead class="bg-white/5 text-white/70">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Fecha</th>
                                <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">ID</th>
                                <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Nombre Del Customer</th>
                                <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Nombre</th>
                                <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Apellido</th>
                                <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Estado</th>
                                <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Cualificación</th>
                                <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Page URL</th>
                                <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Valor</th>
                                <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Fuente</th>
                                <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Medio</th>
                                <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Campaign Objective</th>
                                <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Guardado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10">
                            @forelse($table['leads'] as $lead)
                                @php
                                    $stateOptions = $lead->crm_state_options ?? collect();
                                    $currentStateExists = $stateOptions->pluck('id')->contains((string) $lead->crm_state);
                                @endphp
                                <tr class="text-white/80 hover:bg-white/5">
                                    <td class="whitespace-nowrap px-3 py-2">{{ optional($lead->created_at)->format('Y-m-d H:i') }}</td>
                                    <td class="whitespace-nowrap px-3 py-2">{{ $lead->id }}</td>
                                    <td class="max-w-56 truncate whitespace-nowrap px-3 py-2" title="{{ $lead->customer_name }}">{{ $lead->customer_name }}</td>
                                    <td class="max-w-44 truncate whitespace-nowrap px-3 py-2" title="{{ $lead->name ?: 'Sin Dato' }}">{{ $lead->name ?: 'Sin Dato' }}</td>
                                    <td class="max-w-44 truncate whitespace-nowrap px-3 py-2" title="{{ $lead->last_name ?: 'Sin Dato' }}">{{ $lead->last_name ?: 'Sin Dato' }}</td>
                                    <td class="min-w-64 px-3 py-2">
                                        <select
                                            class="w-full rounded-xl border-white/10 bg-slate-900/60 text-white"
                                            data-lead-crm-state
                                            data-update-url="{{ route('lead-management.crm-state', $lead) }}"
                                            data-original-value="{{ $lead->crm_state }}"
                                            @disabled($stateOptions->isEmpty())
                                        >
                                            @if($stateOptions->isEmpty())
                                                <option value="">Sin Estados Del Cliente</option>
                                            @elseif(! $currentStateExists)
                                                <option value="" selected disabled>{{ $lead->crm_state_name ?: 'Sin Estado' }}</option>
                                            @endif
                                            @foreach($stateOptions as $state)
                                                <option value="{{ $state->id }}" data-qualification="{{ $state->qualification_name ?: 'Sin Calificacion' }}" @selected((string) $lead->crm_state === (string) $state->id)>
                                                    {{ $state->name ?: $state->id }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="max-w-48 truncate whitespace-nowrap px-3 py-2" data-lead-qualification title="{{ $lead->qualification_name }}">{{ $lead->qualification_name }}</td>
                                    <td class="max-w-xs truncate whitespace-nowrap px-3 py-2" title="{{ $lead->page_url ?: 'Sin Dato' }}">
                                        @if($lead->page_url)
                                            <a href="{{ $lead->page_url }}" target="_blank" rel="noopener noreferrer" class="text-indigo-200 hover:text-indigo-100">{{ $lead->page_url }}</a>
                                        @else
                                            Sin Dato
                                        @endif
                                    </td>
                                    <td class="min-w-40 px-3 py-2">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value="{{ $lead->value }}"
                                            class="w-full rounded-xl border-white/10 bg-slate-900/60 text-white"
                                            data-lead-value
                                            data-update-url="{{ route('lead-management.value', $lead) }}"
                                            data-original-value="{{ $lead->value }}"
                                        >
                                    </td>
                                    <td class="max-w-44 truncate whitespace-nowrap px-3 py-2" title="{{ $lead->source_name }}">{{ $lead->source_name }}</td>
                                    <td class="max-w-44 truncate whitespace-nowrap px-3 py-2" title="{{ $lead->medium_name }}">{{ $lead->medium_name }}</td>
                                    <td class="max-w-56 truncate whitespace-nowrap px-3 py-2" title="{{ $lead->campaign_objective_name }}">{{ $lead->campaign_objective_name }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-white/50" data-row-status>Listo</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="13" class="px-3 py-8 text-center text-white/50">Sin Leads Para Estos Filtros</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $table['leads']->links() }}
                </div>
            </section>
        @endif
    </div>
</x-app-layout>

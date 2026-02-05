<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-indigo-200">
                    Leads: {{ $groupLabel }}
                </h2>
                <div class="text-sm text-white/60">Últimos 7 días</div>
            </div>

            @php
                $exportUrl = route('dashboard.leads.list.export', \Illuminate\Support\Arr::except(request()->query(), ['page']));
            @endphp

            <div class="flex items-center gap-2">
                <a href="{{ $exportUrl }}"
                   class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10 inline-flex items-center gap-2">
                    ⬇ Descargar Excel
                </a>

                <a href="{{ $backUrl }}"
                   class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
                    ← Volver al dashboard
                </a>
            </div>
        </div>
    </x-slot>

    @php
        $phoneOf = fn($lead) => $lead->telefono ?? $lead->phone ?? $lead->phone_number ?? $lead->celular ?? $lead->movil ?? null;
        $firstNameOf = fn($lead) => $lead->nombre ?? $lead->first_name ?? $lead->name ?? $lead->nombres ?? null;
        $lastNameOf = fn($lead) => $lead->apellido ?? $lead->last_name ?? $lead->lastname ?? $lead->apellidos ?? null;
    @endphp

    <div class="p-6 max-w-6xl mx-auto space-y-4">
        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4">
            <div class="text-sm text-white/60">
                Cliente:
                <span class="text-white/85 font-semibold">{{ $selectedCustomer?->name ?? 'Todos' }}</span>
                @if($selectedCustomer)
                    <span class="text-white/40"> (ID {{ $selectedCustomer->id }})</span>
                @endif
            </div>
            <div class="text-xs text-white/50">
                Grupo: {{ $groupType }} / {{ $groupId }}
            </div>
        </div>

        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4">
            <div class="overflow-x-auto rounded-xl border border-white/10">
                <table class="min-w-full text-sm">
                    <thead class="bg-white/5 text-white/70">
                        <tr>
                            <th class="text-left px-3 py-2">Fecha</th>
                            <th class="text-left px-3 py-2">ID</th>
                            <th class="text-left px-3 py-2">Teléfono</th>
                            <th class="text-left px-3 py-2">Nombre</th>
                            <th class="text-left px-3 py-2">Apellido</th>
                            <th class="text-left px-3 py-2">Fuente</th>
                            <th class="text-left px-3 py-2">Medio</th>
                            <th class="text-left px-3 py-2">Estado</th>
                            <th class="text-left px-3 py-2">Cualificación</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-white/10 text-white/80">
                        @forelse($leads as $lead)
                            @php
                                $fuente = $lead->campaign_origin;
                                $medio  = $lead->plataforma;

                                $fuenteLabel = ($fuente === null || $fuente === '') ? 'Sin Fuente' : $fuente;
                                $medioLabel  = ($medio === null || $medio === '') ? 'Sin Medio' : $medio;

                                $phone = $phoneOf($lead);
                                $first = $firstNameOf($lead);
                                $last  = $lastNameOf($lead);
                            @endphp

                            <tr class="hover:bg-white/5">
                                <td class="px-3 py-2 whitespace-nowrap">
                                    {{ optional($lead->created_at)->format('Y-m-d H:i') }}
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap font-semibold">{{ $lead->id }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $phone ?? '-' }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $first ?? '-' }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $last ?? '-' }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $fuenteLabel }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $medioLabel }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $lead->crm_state_name ?? 'Sin Estado' }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $lead->qualification_name ?? 'Sin Cualificación' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-3 py-8 text-center text-white/60">
                                    No hay leads para este grupo en la ventana seleccionada.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $leads->links() }}
            </div>
        </div>
    </div>
</x-app-layout>

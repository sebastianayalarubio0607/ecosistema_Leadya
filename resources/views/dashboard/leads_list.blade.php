<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-indigo-200">
                    Leads: {{ $groupLabel }}
                </h2>
                <div class="text-sm text-white/60">Periodo: {{ $periodLabel }}</div>
            </div>

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

    <div class="p-6 max-w-6xl mx-auto space-y-4 ">
        <div
            class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 flex items-center justify-between gap-3">
            <div>
                <div class="text-sm text-white/60">
                    Cliente:
                    <span class="text-white/85 font-semibold">{{ $selectedCustomer?->name ?? 'Todos' }}</span>
                    @if ($selectedCustomer)
                        <span class="text-white/40"> (ID {{ $selectedCustomer->id }})</span>
                    @endif
                </div>
                <div class="text-xs text-white/50">
                    Grupo: {{ $groupType }} / {{ $groupId }}
                </div>
            </div>

            <div>
                <div class="text-sm text-white/60">
                    valor total:
                    <p class="text-white/85 font-semibold">{{ $totalValueFormatted }}</p>
                   
                </div>
                
            </div>

        </div>



        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4">
            <div class="overflow-x-auto rounded-xl border border-white/10">
                <table class="min-w-full text-sm table-fixed">
                    <thead class="bg-white/5 text-white/70">
                        <tr>
                            <th class="text-left px-3 py-2">Fecha</th>
                            <th class="text-left px-3 py-2">ID</th>
                            <th class="text-left px-3 py-2">Teléfono</th>
                            <th class="text-left px-3 py-2">Nombre</th>
                            <th class="text-left px-3 py-2">Apellido</th>
                            <th class="text-left px-3 py-2">Fuente</th>
                            <th class="text-left px-3 py-2">Medio</th>
                            <th class="text-left px-3 py-2">Campaign Objective</th>
                            <th class="text-left px-3 py-2">Estado</th>
                            <th class="text-left px-3 py-2">Cualificación</th>
                            <th class="text-left px-3 py-2">valor</th>
                            <th class="text-left px-3 py-2 max-w-2xs">URL</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-white/10 text-white/80">
                        @forelse($leads as $row)
                            <tr class="hover:bg-white/5">
                                <td class="px-3 py-2 whitespace-nowrap">{{ $row['created_at'] }}</td>
                                <td class="px-3 py-2 whitespace-nowrap font-semibold">{{ $row['id'] }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $row['phone'] }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $row['first_name'] }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $row['last_name'] }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $row['fuente'] }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $row['medio'] }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $row['campaign_objective'] }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $row['crm_state'] }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $row['qualification'] }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $row['value_formatted'] }}</td>
                                <td class="px-3 py-2 whitespace-nowrap w-64 truncate">{{ $row['page_url'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="px-3 py-8 text-center text-white/60">
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

@extends('meta.layout')

@section('title', 'Detalle Meta Page')
@section('subtitle', 'Información de página, sync y formularios asociados')

@section('header_actions')
    <a href="{{ route('meta.pages.edit', $page) }}" class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">Editar</a>
    <a href="{{ route('meta.pages.status-history.index', ['meta_page_id' => $page->id]) }}" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">Historial estados</a>
    <form method="POST" action="{{ route('meta.pages.statuses.sync', $page) }}">
        @csrf
        <button class="px-4 py-2 rounded-xl bg-sky-500/20 hover:bg-sky-500/30 text-white border border-white/10">Consultar estado</button>
    </form>
    <a href="{{ route('meta.pages.index') }}" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">Volver</a>
@endsection

@section('content')
    <div class="grid gap-4">
        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-6 text-white/80 space-y-4">
            <div><span class="text-white/50">Cliente:</span> {{ $page->customer?->name ?? '—' }}</div>
            <div><span class="text-white/50">Meta Page ID:</span> {{ $page->meta_page_id }}</div>
            <div><span class="text-white/50">Nombre:</span> {{ $page->name }}</div>
            <div><span class="text-white/50">Estado CRM:</span> {{ $page->status ? 'Activa' : 'Inactiva' }}</div>
            <div><span class="text-white/50">Estado Meta:</span> {{ $page->estado_meta ?? 'Sin consultar' }} <span class="text-white/60">{{ $page->estado_meta_nombre ?: 'Sin estado reportado' }}</span></div>
            <div>
                <span class="text-white/50">Leadgen:</span>
                <span class="inline-flex rounded-lg border px-2 py-1 text-xs font-semibold {{ $page->is_leadgen_subscribed ? 'bg-emerald-500/15 border-emerald-300/30 text-emerald-200 shadow-sm shadow-emerald-950/30' : 'bg-rose-500/15 border-rose-300/30 text-rose-200 shadow-sm shadow-rose-950/30' }}">
                    {{ $page->is_leadgen_subscribed ? 'Suscrita' : 'No suscrita' }}
                </span>
            </div>
            <div><span class="text-white/50">Revision suscripcion:</span> {{ optional($page->subscription_checked_at)->format('Y-m-d H:i') ?: 'Sin validar' }}</div>
            <div><span class="text-white/50">Revision estado Meta:</span> {{ optional($page->estado_meta_checked_at)->format('Y-m-d H:i') ?: 'Sin consultar' }}</div>
            <div><span class="text-white/50">Última sync:</span> {{ optional($page->last_synced_at)->format('Y-m-d H:i') ?: '—' }}</div>
            <div><span class="text-white/50">Último refresh token:</span> {{ optional($page->last_token_refresh_at)->format('Y-m-d H:i') ?: '—' }}</div>
            <div>
                <div class="text-white/50 mb-1">Page Access Token</div>
                <div class="rounded-xl border border-white/10 bg-slate-900/60 p-3 break-all text-xs">{{ $page->page_access_token ?: '—' }}</div>
            </div>
            <div>
                <div class="text-white/50 mb-1">Último error</div>
                <div class="rounded-xl border border-white/10 bg-slate-900/60 p-3 text-sm">{{ $page->last_error ?: 'Sin errores registrados' }}</div>
            </div>
            <div>
                <div class="text-white/50 mb-1">leadgen</div>
                <pre class="rounded-xl border border-white/10 bg-slate-900/60 p-3 break-all text-xs whitespace-pre-wrap">{{ $page->leadgen ? json_encode($page->leadgen, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 'Sin respuesta registrada' }}</pre>
            </div>
            <div>
                <div class="text-white/50 mb-1">Ultimo error de estado Meta</div>
                <div class="rounded-xl border border-white/10 bg-slate-900/60 p-3 text-sm break-all">{{ $page->estado_meta_last_error ?: 'Sin errores registrados' }}</div>
            </div>
            <div>
                <div class="text-white/50 mb-1">Ultimo error de suscripcion</div>
                <div class="rounded-xl border border-white/10 bg-slate-900/60 p-3 text-sm break-all">{{ $page->subscription_last_error ?: 'Sin errores registrados' }}</div>
            </div>
        </div>

        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 text-white/80">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-white">Formularios asociados</h3>
                <a href="{{ route('meta.forms.create') }}" class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs">+ Nuevo Form</a>
            </div>
            @if($page->forms->isEmpty())
                <div class="text-white/60">No hay formularios asociados.</div>
            @else
                <div class="space-y-2">
                    @foreach($page->forms as $form)
                        <div class="rounded-xl border border-white/10 p-3 flex items-center justify-between gap-3">
                            <div>
                                <div class="font-medium text-white">{{ $form->name }}</div>
                                <div class="text-xs text-white/50">{{ $form->meta_form_id }} | {{ $form->status ? 'Activo' : 'Inactivo' }}</div>
                            </div>
                            <a href="{{ route('meta.forms.show', $form) }}" class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs">Ver</a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 text-white/80" data-sortable-table-wrap>
            <div class="flex items-center justify-between gap-3 mb-3">
                <h3 class="font-semibold text-white">Historial reciente de estado Meta</h3>
                <a href="{{ route('meta.pages.status-history.index', ['meta_page_id' => $page->id]) }}" class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs">Ver todo</a>
            </div>
            <div class="hidden px-3 py-2 text-xs text-white/50" data-sort-status>Ordenando...</div>
            <div class="w-full max-w-full overflow-x-auto rounded-xl border border-white/10">
                <table class="w-full min-w-[900px] text-sm" data-sortable-table>
                    <thead class="bg-white/5 text-white/70">
                        <tr>
                            <x-sort-header :index="0" label="Fecha" />
                            <x-sort-header :index="1" label="Anterior" />
                            <x-sort-header :index="2" label="Nuevo" />
                            <x-sort-header :index="3" label="Origen" />
                            <x-sort-header :index="4" label="Cambio" />
                            <th class="text-left px-3 py-2 whitespace-nowrap">Error</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10 text-white/80">
                        @forelse($page->statusHistories as $history)
                            <tr class="hover:bg-white/5">
                                <td class="px-3 py-2">{{ optional($history->consulted_at)->format('Y-m-d H:i') }}</td>
                                <td class="px-3 py-2">{{ $history->estado_meta_anterior ?? '-' }} <span class="text-white/50">{{ $history->estado_meta_anterior_nombre }}</span></td>
                                <td class="px-3 py-2">{{ $history->estado_meta_nuevo ?? '-' }} <span class="text-white/50">{{ $history->estado_meta_nuevo_nombre }}</span></td>
                                <td class="px-3 py-2">{{ $history->query_type ?: '-' }}</td>
                                <td class="px-3 py-2">{{ $history->changed ? 'Si' : 'No' }}</td>
                                <td class="px-3 py-2 break-all">{{ $history->error ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-8 text-center text-white/60">Sin historial registrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex gap-2">
            <form action="{{ route('meta.pages.sync-forms', $page) }}" method="POST">
                @csrf
                <button class="px-4 py-2 rounded-xl bg-emerald-500/20 hover:bg-emerald-500/30 border border-white/10 text-white">Sincronizar formularios</button>
            </form>
            <form action="{{ route('meta.pages.destroy', $page) }}" method="POST">
                @csrf
                @method('DELETE')
                <button class="px-4 py-2 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 border border-rose-300/20 text-white" onclick="return confirm('¿Eliminar página Meta?')">Eliminar</button>
            </form>
        </div>
    </div>
@endsection

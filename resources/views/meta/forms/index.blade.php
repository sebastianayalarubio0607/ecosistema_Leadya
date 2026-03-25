@extends('meta.layout')

@section('title', 'Meta Forms')
@section('subtitle', 'Formularios Lead Ads disponibles para sincronización')

@section('header_actions')
    <a href="{{ route('meta.forms.create') }}" class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">+ Nuevo</a>
    <form method="POST" action="{{ route('meta.forms.sync-all') }}">
        @csrf
        <button class="px-4 py-2 rounded-xl bg-emerald-500/20 hover:bg-emerald-500/30 text-white border border-white/10">Sync Forms</button>
    </form>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <div class="md:col-span-4">
                <label class="block mb-1 text-white/70">Buscar</label>
                <input name="search" value="{{ request('search') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40" placeholder="Buscar por ID o nombre">
            </div>
            <div class="md:col-span-4">
                <label class="block mb-1 text-white/70">Página</label>
                <select name="meta_page_id" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">-- Todas --</option>
                    @foreach($pages as $page)
                        <option value="{{ $page->id }}" @selected((string) request('meta_page_id') === (string) $page->id)>{{ $page->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block mb-1 text-white/70">Estado</label>
                <select name="status" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">-- Todos --</option>
                    <option value="1" @selected(request('status') === '1')>Activo</option>
                    <option value="0" @selected(request('status') === '0')>Inactivo</option>
                </select>
            </div>
            <div class="md:col-span-2 flex gap-2">
                <button class="w-full px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">Filtrar</button>
                <a href="{{ route('meta.forms.index') }}" class="w-full text-center px-4 py-2 rounded-xl bg-zinc-950/25 hover:bg-white/10 text-white border border-white/10">Limpiar</a>
            </div>
        </form>

        <div class="overflow-x-auto rounded-xl border border-white/10">
            <table class="min-w-full text-sm">
                <thead class="bg-white/5 text-white/70">
                    <tr>
                        <th class="text-left px-3 py-2">Cliente</th>
                        <th class="text-left px-3 py-2">Página</th>
                        <th class="text-left px-3 py-2">Formulario</th>
                        <th class="text-left px-3 py-2">Estado CRM</th>
                        <th class="text-left px-3 py-2">Meta Status</th>
                        <th class="text-left px-3 py-2">Mappings</th>
                        <th class="text-left px-3 py-2 w-80">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10 text-white/80">
                    @forelse($items as $item)
                        <tr class="hover:bg-white/5">
                            <td class="px-3 py-2">{{ $item->page?->customer?->name ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $item->page?->name ?? '—' }}</td>
                            <td class="px-3 py-2">
                                <div class="font-medium text-white">{{ $item->name }}</div>
                                <div class="text-xs text-white/50">{{ $item->meta_form_id }}</div>
                            </td>
                            <td class="px-3 py-2">{{ $item->status ? 'Activo' : 'Inactivo' }}</td>
                            <td class="px-3 py-2">{{ $item->meta_status ?: '—' }}</td>
                            <td class="px-3 py-2">{{ $item->field_mappings_count }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <a class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs" href="{{ route('meta.forms.show', $item) }}">Ver</a>
                                    <a class="px-3 py-1.5 rounded-lg bg-indigo-500/20 hover:bg-indigo-500/30 border border-white/10 text-xs" href="{{ route('meta.forms.edit', $item) }}">Editar</a>
                                    <a class="px-3 py-1.5 rounded-lg bg-amber-500/20 hover:bg-amber-500/30 border border-white/10 text-xs" href="{{ route('meta.form-field-mappings.index', ['meta_form_id' => $item->id]) }}">Mappings</a>
                                    <form action="{{ route('meta.forms.sync-leads', $item) }}" method="POST">
                                        @csrf
                                        <button class="px-3 py-1.5 rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 border border-white/10 text-xs">Sync Leads</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-8 text-center text-white/60">No hay formularios Meta registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $items->links() }}</div>
    </div>
@endsection

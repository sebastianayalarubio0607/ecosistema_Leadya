@extends('meta.layout')

@section('title', 'Meta Pages')
@section('subtitle', 'Páginas detectadas o cargadas para Meta Lead Ads')

@section('header_actions')
    <a href="{{ route('meta.pages.create') }}" class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">+ Nueva</a>
    <form method="POST" action="{{ route('meta.pages.subscription-jobs.scan') }}">
        @csrf
        <button class="px-4 py-2 rounded-xl bg-emerald-500/20 hover:bg-emerald-500/30 text-white border border-white/10">Revisar leadgen</button>
    </form>
    <a href="{{ route('meta.pages.subscription-jobs.index') }}" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">Jobs</a>
    <form method="POST" action="{{ route('meta.pages.sync-all') }}">
        @csrf
        <button class="px-4 py-2 rounded-xl bg-emerald-500/20 hover:bg-emerald-500/30 text-white border border-white/10">Sync Pages</button>
    </form>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <div class="md:col-span-3">
                <label class="block mb-1 text-white/70">Cliente</label>
                <select name="customer_id" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">-- Todos --</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((string) request('customer_id') === (string) $customer->id)>{{ $customer->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-3">
                <label class="block mb-1 text-white/70">Meta Page ID</label>
                <input name="meta_page_id" value="{{ request('meta_page_id') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40" placeholder="ID de pagina">
            </div>

            <div class="md:col-span-3">
                <label class="block mb-1 text-white/70">Nombre</label>
                <input name="name" value="{{ request('name') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40" placeholder="Nombre">
            </div>

            <div class="md:col-span-3">
                <label class="block mb-1 text-white/70">Estado</label>
                <select name="status" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">-- Todos --</option>
                    <option value="1" @selected(request('status') === '1')>Activa</option>
                    <option value="0" @selected(request('status') === '0')>Inactiva</option>
                </select>
            </div>

            <div class="md:col-span-3">
                <label class="block mb-1 text-white/70">Leadgen</label>
                <select name="leadgen" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">-- Todos --</option>
                    <option value="1" @selected(request('leadgen') === '1')>Suscrita</option>
                    <option value="0" @selected(request('leadgen') === '0')>No suscrita</option>
                </select>
            </div>

            <div class="md:col-span-3">
                <label class="block mb-1 text-white/70">Forms</label>
                <select name="forms" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">-- Todos --</option>
                    <option value="with" @selected(request('forms') === 'with')>Con forms</option>
                    <option value="without" @selected(request('forms') === 'without')>Sin forms</option>
                </select>
            </div>

            <div class="md:col-span-3">
                <label class="block mb-1 text-white/70">Buscar general</label>
                <input name="search" value="{{ request('search') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40" placeholder="ID o nombre">
            </div>

            <div class="md:col-span-3 flex gap-2">
                <button class="w-full px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">Filtrar</button>
                <a href="{{ route('meta.pages.index') }}" class="w-full text-center px-4 py-2 rounded-xl bg-zinc-950/25 hover:bg-white/10 text-white border border-white/10">Limpiar</a>
            </div>
        </form>

        <div class="overflow-x-auto rounded-xl border border-white/10">
            <table class="min-w-full text-sm">
                <thead class="bg-white/5 text-white/70">
                    <tr>
                        <th class="text-left px-3 py-2">Cliente</th>
                        <th class="text-left px-3 py-2">Meta Page ID</th>
                        <th class="text-left px-3 py-2">Nombre</th>
                        <th class="text-left px-3 py-2">Estado CRM</th>
                        <th class="text-left px-3 py-2">Leadgen</th>
                        <th class="text-left px-3 py-2">Forms</th>
                        <th class="text-left px-3 py-2">Última sync</th>
                        <th class="text-left px-3 py-2 w-72">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10 text-white/80">
                    @forelse($items as $item)
                        <tr class="hover:bg-white/5">
                            <td class="px-3 py-2">{{ $item->customer?->name ?? '—' }}</td>
                            <td class="px-3 py-2 font-semibold">{{ $item->meta_page_id }}</td>
                            <td class="px-3 py-2">{{ $item->name }}</td>
                            <td class="px-3 py-2">{{ $item->status ? 'Activa' : 'Inactiva' }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex rounded-lg border px-2 py-1 text-xs font-semibold {{ $item->is_leadgen_subscribed ? 'bg-emerald-500/15 border-emerald-300/30 text-emerald-200 shadow-sm shadow-emerald-950/30' : 'bg-rose-500/15 border-rose-300/30 text-rose-200 shadow-sm shadow-rose-950/30' }}">
                                    {{ $item->is_leadgen_subscribed ? 'Suscrita' : 'No suscrita' }}
                                </span>
                            </td>
                            <td class="px-3 py-2">{{ $item->forms_count }}</td>
                            <td class="px-3 py-2">{{ optional($item->last_synced_at)->format('Y-m-d H:i') ?: '—' }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <a class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs" href="{{ route('meta.pages.show', $item) }}">Ver</a>
                                    <a class="px-3 py-1.5 rounded-lg bg-indigo-500/20 hover:bg-indigo-500/30 border border-white/10 text-xs" href="{{ route('meta.pages.edit', $item) }}">Editar</a>
                                    <form action="{{ route('meta.pages.sync-forms', $item) }}" method="POST">
                                        @csrf
                                        <button class="px-3 py-1.5 rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 border border-white/10 text-xs">Sync Forms</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-8 text-center text-white/60">No hay páginas Meta registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $items->links() }}</div>
    </div>
@endsection

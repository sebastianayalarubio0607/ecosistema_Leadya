@extends('meta.layout')

@section('title', 'Meta Ad Accounts')
@section('subtitle', 'Cuentas publicitarias asociadas a clientes')

@section('header_actions')
    <form method="POST" action="{{ route('meta.ad-accounts.subscription-jobs.scan') }}">
        @csrf
        <button class="px-4 py-2 rounded-xl bg-emerald-500/20 hover:bg-emerald-500/30 text-white border border-white/10">Revisar suscripciones</button>
    </form>
    <form method="POST" action="{{ route('meta.ad-accounts.statuses.sync-all') }}">
        @csrf
        <button class="px-4 py-2 rounded-xl bg-sky-500/20 hover:bg-sky-500/30 text-white border border-white/10">Consultar estados</button>
    </form>
    <a href="{{ route('meta.ad-accounts.status-history.index') }}" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">Historial estados</a>
    <a href="{{ route('meta.ad-accounts.subscription-jobs.index') }}" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">Jobs</a>
    <a href="{{ route('meta.ad-accounts.create') }}" class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">+ Nueva</a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <div class="md:col-span-3">
                <label class="block mb-1 text-white/70">Cliente</label>
                <select name="customer_id"
                        class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">-- Todos --</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}" @selected((string)request('customer_id')===(string)$c->id)>
                            {{ $c->name }} (ID: {{ $c->id }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-3">
                <label class="block mb-1 text-white/70">Meta Account ID</label>
                <input name="meta_account_id" value="{{ request('meta_account_id') }}"
                       class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
                       placeholder="ID de cuenta">
            </div>

            <div class="md:col-span-3">
                <label class="block mb-1 text-white/70">Nombre</label>
                <input name="name" value="{{ request('name') }}"
                       class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
                       placeholder="Nombre">
            </div>

            <div class="md:col-span-3">
                <label class="block mb-1 text-white/70">Estado</label>
                <select name="status" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">-- Todos --</option>
                    <option value="active" @selected(request('status') === 'active')>active</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>inactive</option>
                </select>
            </div>

            <div class="md:col-span-3">
                <label class="block mb-1 text-white/70">Estado Meta</label>
                <input name="estado_meta" value="{{ request('estado_meta') }}"
                       class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
                       placeholder="Codigo Meta">
            </div>

            <div class="md:col-span-3">
                <label class="block mb-1 text-white/70">Suscripcion</label>
                <select name="subscription" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">-- Todas --</option>
                    <option value="1" @selected(request('subscription') === '1')>Suscrita</option>
                    <option value="0" @selected(request('subscription') === '0')>No suscrita</option>
                </select>
            </div>

            <div class="md:col-span-3">
                <label class="block mb-1 text-white/70">Token ve cuenta</label>
                <select name="token_can_view_account" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">-- Todos --</option>
                    <option value="1" @selected(request('token_can_view_account') === '1')>Si</option>
                    <option value="0" @selected(request('token_can_view_account') === '0')>No</option>
                    <option value="unknown" @selected(request('token_can_view_account') === 'unknown')>Sin validar</option>
                </select>
            </div>

            <div class="md:col-span-3">
                <label class="block mb-1 text-white/70">Buscar general</label>
                <input name="search" value="{{ request('search') }}"
                       class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
                       placeholder="ID o nombre">
            </div>

            <div class="md:col-span-3 flex gap-2">
                <button class="w-full px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">Filtrar</button>
                <a href="{{ route('meta.ad-accounts.index') }}" class="w-full text-center px-4 py-2 rounded-xl bg-zinc-950/25 hover:bg-white/10 text-white border border-white/10">Limpiar</a>
            </div>
        </form>

        <div class="w-full max-w-full overflow-x-auto rounded-xl border border-white/10 [scrollbar-gutter:stable]" data-sortable-table-wrap>
            <div class="hidden px-3 py-2 text-xs text-white/50" data-sort-status>Ordenando...</div>
            <table class="w-full min-w-[1100px] text-sm" data-sortable-table>
                <thead class="bg-white/5 text-white/70">
                    <tr>
                        <x-sort-header :index="0" label="Cliente" />
                        <x-sort-header :index="1" label="Meta Account ID" />
                        <x-sort-header :index="2" label="Nombre" />
                        <x-sort-header :index="3" label="Estado interno" />
                        <x-sort-header :index="4" label="Estado Meta" />
                        <x-sort-header :index="5" label="Suscripcion" />
                        <x-sort-header :index="6" label="Token ve cuenta" />
                        <x-sort-header :index="7" label="Ultima consulta" />
                        <th class="text-left px-3 py-2 w-64 whitespace-nowrap">Acciones</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-white/10 text-white/80">
                    @forelse($items as $it)
                        <tr class="hover:bg-white/5">
                            <td class="px-3 py-2">{{ $it->customer?->name ?? '—' }}</td>
                            <td class="px-3 py-2 font-semibold">{{ $it->meta_account_id }}</td>
                            <td class="px-3 py-2">{{ $it->name }}</td>
                            <td class="px-3 py-2">{{ $it->status }}</td>
                            <td class="px-3 py-2">
                                <div class="font-semibold">{{ $it->estado_meta ?? 'Sin consultar' }}</div>
                                <div class="text-xs text-white/50">{{ $it->estado_meta_nombre ?: 'Sin estado Meta' }}</div>
                            </td>
                            <td class="px-3 py-2">
                                <span class="inline-flex rounded-lg border px-2 py-1 text-xs font-semibold {{ $it->is_subscribed_to_meta_app ? 'bg-emerald-500/15 border-emerald-300/30 text-emerald-200 shadow-sm shadow-emerald-950/30' : 'bg-rose-500/15 border-rose-300/30 text-rose-200 shadow-sm shadow-rose-950/30' }}">
                                    {{ $it->is_subscribed_to_meta_app ? 'Suscrita' : 'No suscrita' }}
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                @if(is_null($it->token_can_view_account))
                                    Sin validar
                                @else
                                    {{ $it->token_can_view_account ? 'Si' : 'No' }}
                                @endif
                            </td>
                            <td class="px-3 py-2">{{ optional($it->estado_meta_checked_at)->format('Y-m-d H:i') ?: '-' }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <a class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs"
                                       href="{{ route('meta.ad-accounts.show', $it) }}">Ver</a>
                                    <a class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs"
                                       href="{{ route('meta.ad-accounts.status-history.index', ['meta_ad_account_id' => $it->id]) }}">Historial</a>

                                    <form action="{{ route('meta.ad-accounts.statuses.sync', $it) }}" method="POST">
                                        @csrf
                                        <button class="px-3 py-1.5 rounded-lg bg-sky-500/20 hover:bg-sky-500/30 border border-white/10 text-xs">Consultar estado</button>
                                    </form>

                                    <a class="px-3 py-1.5 rounded-lg bg-indigo-500/20 hover:bg-indigo-500/30 border border-white/10 text-xs"
                                       href="{{ route('meta.ad-accounts.edit', $it) }}">Editar</a>

                                    <form action="{{ route('meta.ad-accounts.destroy', $it) }}" method="POST">
                                        @csrf @method('DELETE')
                                        <button class="px-3 py-1.5 rounded-lg bg-rose-500/20 hover:bg-rose-500/30 border border-rose-300/20 text-xs"
                                                onclick="return confirm('¿Eliminar cuenta?')">
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-3 py-8 text-center text-white/60">
                                No hay cuentas para mostrar.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $items->links() }}
        </div>
    </div>
@endsection

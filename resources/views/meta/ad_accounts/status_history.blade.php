@extends('meta.layout')

@section('title', 'Historial estados Meta Ad Accounts')
@section('subtitle', 'Consultas y cambios de estado reportados por Meta')

@section('header_actions')
    <form method="POST" action="{{ route('meta.ad-accounts.statuses.sync-all') }}">
        @csrf
        <button class="px-4 py-2 rounded-xl bg-sky-500/20 hover:bg-sky-500/30 text-white border border-white/10">Consultar estados</button>
    </form>
    <a href="{{ route('meta.ad-accounts.index') }}" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">Cuentas</a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <div class="md:col-span-3">
                <label class="block mb-1 text-white/70">Cliente</label>
                <select name="customer_id" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">Todos</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((string) request('customer_id') === (string) $customer->id)>{{ $customer->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-3">
                <label class="block mb-1 text-white/70">Cuenta publicitaria</label>
                <select name="meta_ad_account_id" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">Todas</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" @selected((string) request('meta_ad_account_id') === (string) $account->id)>{{ $account->name ?: $account->meta_account_id }} ({{ $account->meta_account_id }})</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="block mb-1 text-white/70">Estado</label>
                <select name="estado_meta" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">Todos</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->estado_meta_nuevo }}" @selected((string) request('estado_meta') === (string) $status->estado_meta_nuevo)>{{ $status->estado_meta_nuevo }} - {{ $status->estado_meta_nuevo_nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="block mb-1 text-white/70">Tipo consulta</label>
                <select name="query_type" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">Todos</option>
                    @foreach($queryTypes as $queryType)
                        <option value="{{ $queryType }}" @selected(request('query_type') === $queryType)>{{ $queryType }}</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-1">
                <label class="block mb-1 text-white/70">Desde</label>
                <input type="date" name="from" value="{{ request('from') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
            </div>

            <div class="md:col-span-1">
                <label class="block mb-1 text-white/70">Hasta</label>
                <input type="date" name="to" value="{{ request('to') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
            </div>

            <div class="md:col-span-12 flex flex-wrap gap-2">
                <button class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">Filtrar</button>
                <a href="{{ route('meta.ad-accounts.status-history.index') }}" class="px-4 py-2 rounded-xl bg-zinc-950/25 hover:bg-white/10 text-white border border-white/10">Limpiar</a>
            </div>
        </form>

        <div class="w-full max-w-full overflow-x-auto rounded-xl border border-white/10 [scrollbar-gutter:stable]" data-sortable-table-wrap>
            <div class="hidden px-3 py-2 text-xs text-white/50" data-sort-status>Ordenando...</div>
            <table class="w-full min-w-[1300px] text-sm" data-sortable-table>
                <thead class="bg-white/5 text-white/70">
                    <tr>
                        <x-sort-header :index="0" label="Fecha" />
                        <x-sort-header :index="1" label="Cliente" />
                        <x-sort-header :index="2" label="Meta Account ID" />
                        <x-sort-header :index="3" label="Anterior" />
                        <x-sort-header :index="4" label="Nuevo" />
                        <x-sort-header :index="5" label="Cambio" />
                        <x-sort-header :index="6" label="Tipo" />
                        <x-sort-header :index="7" label="Webhook" />
                        <th class="text-left px-3 py-2 whitespace-nowrap">Error</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10 text-white/80">
                    @forelse($items as $item)
                        <tr class="hover:bg-white/5">
                            <td class="px-3 py-2 whitespace-nowrap">{{ optional($item->consulted_at)->format('Y-m-d H:i:s') }}</td>
                            <td class="px-3 py-2">{{ $item->customer?->name ?? '-' }}</td>
                            <td class="px-3 py-2">
                                <div class="font-semibold">{{ $item->meta_account_id ?: $item->account?->meta_account_id }}</div>
                                <div class="text-xs text-white/50">{{ $item->account?->name }}</div>
                            </td>
                            <td class="px-3 py-2">{{ $item->estado_meta_anterior ?? '-' }} <span class="text-white/50">{{ $item->estado_meta_anterior_nombre }}</span></td>
                            <td class="px-3 py-2">{{ $item->estado_meta_nuevo ?? '-' }} <span class="text-white/50">{{ $item->estado_meta_nuevo_nombre }}</span></td>
                            <td class="px-3 py-2">{{ $item->changed ? 'Si' : 'No' }}</td>
                            <td class="px-3 py-2">{{ $item->query_type ?: '-' }}</td>
                            <td class="px-3 py-2">{{ $item->webhookEvent ? $item->webhookEvent->object.' / '.$item->webhookEvent->field : '-' }}</td>
                            <td class="px-3 py-2 break-all">{{ $item->error ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-3 py-8 text-center text-white/60">No hay historial para mostrar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $items->links() }}</div>
    </div>
@endsection

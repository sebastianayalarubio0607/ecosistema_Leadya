@extends('meta.layout')

@section('title', 'Meta Ad Accounts')
@section('subtitle', 'Cuentas publicitarias asociadas a clientes')

@section('header_actions')
    <a href="{{ route('meta.ad-accounts.create') }}" class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">+ Nueva</a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <div class="md:col-span-5">
                <label class="block mb-1 text-white/70">Buscar</label>
                <input name="search" value="{{ request('search') }}"
                       class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
                       placeholder="Buscar por ID o nombre">
            </div>

            <div class="md:col-span-5">
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

            <div class="md:col-span-2 flex gap-2">
                <button class="w-full px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">Filtrar</button>
                <a href="{{ route('meta.ad-accounts.index') }}" class="w-full text-center px-4 py-2 rounded-xl bg-zinc-950/25 hover:bg-white/10 text-white border border-white/10">Limpiar</a>
            </div>
        </form>

        <div class="overflow-x-auto rounded-xl border border-white/10">
            <table class="min-w-full text-sm">
                <thead class="bg-white/5 text-white/70">
                    <tr>
                        <th class="text-left px-3 py-2">Cliente</th>
                        <th class="text-left px-3 py-2">Meta Account ID</th>
                        <th class="text-left px-3 py-2">Nombre</th>
                        <th class="text-left px-3 py-2">Estado</th>
                        <th class="text-left px-3 py-2 w-56">Acciones</th>
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
                                <div class="flex items-center gap-2">
                                    <a class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs"
                                       href="{{ route('meta.ad-accounts.show', $it) }}">Ver</a>

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
                            <td colspan="5" class="px-3 py-8 text-center text-white/60">
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

@extends('meta.layout')

@section('title', 'Meta WhatsApp')
@section('subtitle', 'Cuentas WhatsApp Business y suscripciones webhook')

@section('header_actions')
    <form method="POST" action="{{ route('meta.whatsapps.subscription-jobs.scan') }}">
        @csrf
        <button class="px-4 py-2 rounded-xl bg-emerald-500/20 hover:bg-emerald-500/30 text-white border border-white/10">Revisar suscripciones</button>
    </form>
    <a href="{{ route('meta.whatsapps.subscription-jobs.index') }}" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">Jobs</a>
    <a href="{{ route('meta.whatsapps.create') }}" class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">+ Nueva</a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <div class="md:col-span-3">
                <label class="block mb-1 text-white/70">Customer</label>
                <select name="customer_id" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">-- Todos --</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((string) request('customer_id') === (string) $customer->id)>
                            {{ $customer->name }} (ID: {{ $customer->id }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-3">
                <label class="block mb-1 text-white/70">WABA ID</label>
                <input name="waba_id" value="{{ request('waba_id') }}"
                       class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40">
            </div>

            <div class="md:col-span-3">
                <label class="block mb-1 text-white/70">Phone Number ID</label>
                <input name="phone_number_id" value="{{ request('phone_number_id') }}"
                       class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40">
            </div>

            <div class="md:col-span-3">
                <label class="block mb-1 text-white/70">WA ID</label>
                <input name="wa_id" value="{{ request('wa_id') }}"
                       class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40">
            </div>

            <div class="md:col-span-3">
                <label class="block mb-1 text-white/70">Estado</label>
                <select name="status" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">-- Todos --</option>
                    <option value="1" @selected(request('status') === '1')>Activo</option>
                    <option value="0" @selected(request('status') === '0')>Inactivo</option>
                </select>
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
                <label class="block mb-1 text-white/70">Token ve WABA</label>
                <select name="token_can_view_account" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">-- Todos --</option>
                    <option value="1" @selected(request('token_can_view_account') === '1')>Si</option>
                    <option value="0" @selected(request('token_can_view_account') === '0')>No</option>
                    <option value="unknown" @selected(request('token_can_view_account') === 'unknown')>Sin validar</option>
                </select>
            </div>

            <div class="md:col-span-3">
                <label class="block mb-1 text-white/70">Credencial WhatsApp</label>
                <select name="meta_access_token_id" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">-- Todas --</option>
                    @foreach($whatsappAccessTokens as $token)
                        <option value="{{ $token->id }}" @selected((string) request('meta_access_token_id') === (string) $token->id)>
                            #{{ $token->id }} {{ $token->name ?: 'System user' }} / app {{ $token->meta_app_id ?: '-' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-3">
                <label class="block mb-1 text-white/70">Buscar general</label>
                <input name="search" value="{{ request('search') }}"
                       class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40">
            </div>

            <div class="md:col-span-3 flex gap-2">
                <button class="w-full px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">Filtrar</button>
                <a href="{{ route('meta.whatsapps.index') }}" class="w-full text-center px-4 py-2 rounded-xl bg-zinc-950/25 hover:bg-white/10 text-white border border-white/10">Limpiar</a>
            </div>
        </form>

        <div class="w-full max-w-full overflow-x-auto rounded-xl border border-white/10 [scrollbar-gutter:stable]" data-sortable-table-wrap>
            <div class="hidden px-3 py-2 text-xs text-white/50" data-sort-status>Ordenando...</div>
            <table class="w-full min-w-[1450px] text-sm" data-sortable-table>
                <thead class="bg-white/5 text-white/70">
                    <tr>
                        <x-sort-header :index="0" label="Customers" />
                        <x-sort-header :index="1" label="WABA ID" />
                        <x-sort-header :index="2" label="Phone Number ID" />
                        <x-sort-header :index="3" label="WA ID" />
                        <x-sort-header :index="4" label="Estado" />
                        <x-sort-header :index="5" label="Suscripcion" />
                        <x-sort-header :index="6" label="Token ve WABA" />
                        <x-sort-header :index="7" label="Credencial" />
                        <x-sort-header :index="8" label="App validada" />
                        <x-sort-header :index="9" label="Acciones" class="w-56" />
                    </tr>
                </thead>

                <tbody class="divide-y divide-white/10 text-white/80">
                    @forelse($items as $item)
                        <tr class="hover:bg-white/5">
                            <td class="px-3 py-2">
                                {{ $item->customers->pluck('name')->join(', ') ?: '-' }}
                            </td>
                            <td class="px-3 py-2 font-semibold break-all">{{ $item->waba_id }}</td>
                            <td class="px-3 py-2 break-all">{{ $item->phone_number_id ?: '-' }}</td>
                            <td class="px-3 py-2 break-all">{{ $item->wa_id ?: '-' }}</td>
                            <td class="px-3 py-2">{{ $item->status ? 'Activo' : 'Inactivo' }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex rounded-lg border px-2 py-1 text-xs font-semibold {{ $item->is_subscribed_to_meta_app ? 'bg-emerald-500/15 border-emerald-300/30 text-emerald-200 shadow-sm shadow-emerald-950/30' : 'bg-rose-500/15 border-rose-300/30 text-rose-200 shadow-sm shadow-rose-950/30' }}">
                                    {{ $item->is_subscribed_to_meta_app ? 'Suscrita' : 'No suscrita' }}
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                {{ is_null($item->token_can_view_account) ? 'Sin validar' : ($item->token_can_view_account ? 'Si' : 'No') }}
                            </td>
                            <td class="px-3 py-2">
                                {{ $item->metaAccessToken?->name ?: ($item->meta_access_token_id ? '#'.$item->meta_access_token_id : '-') }}
                                @if($item->subscription_token_source)
                                    <div class="text-xs text-white/50">Ultima: {{ $item->subscription_token_source }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2 break-all">{{ $item->subscription_meta_app_id ?: '-' }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <a class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs"
                                       href="{{ route('meta.whatsapps.show', $item) }}">Ver</a>
                                    <a class="px-3 py-1.5 rounded-lg bg-indigo-500/20 hover:bg-indigo-500/30 border border-white/10 text-xs"
                                       href="{{ route('meta.whatsapps.edit', $item) }}">Editar</a>
                                    <form action="{{ route('meta.whatsapps.destroy', $item) }}" method="POST">
                                        @csrf @method('DELETE')
                                        <button class="px-3 py-1.5 rounded-lg bg-rose-500/20 hover:bg-rose-500/30 border border-rose-300/20 text-xs"
                                                onclick="return confirm('Eliminar cuenta WhatsApp?')">
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-3 py-8 text-center text-white/60">No hay cuentas WhatsApp para mostrar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $items->links() }}</div>
    </div>
@endsection

@extends('meta.layout')

@section('title', 'Google Ads Credentials')
@section('subtitle', 'Administración segura de la credencial global de Google Ads')

@section('header_actions')
    @if(!$activeCredential)
        <a href="{{ route('google-ads.credentials.create') }}"
           class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">
            Configurar
        </a>
    @endif
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">
        <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-white/70">
            Esta configuración es global y se reutiliza para todos los clientes. Solo se sincronizan clientes que tengan diligenciado su campo <span class="font-mono text-white">id_Gads</span>.
        </div>

        <div class="overflow-x-auto rounded-xl border border-white/10">
            <table class="min-w-full text-sm">
                <thead class="bg-white/5 text-white/70">
                    <tr>
                        <th class="text-left px-3 py-2">Developer Token</th>
                        <th class="text-left px-3 py-2">Customer ID</th>
                        <th class="text-left px-3 py-2">MCC ID</th>
                        <th class="text-left px-3 py-2">Expira</th>
                        <th class="text-left px-3 py-2">Activo</th>
                        <th class="text-left px-3 py-2">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10 text-white/80">
                    @forelse($items as $item)
                        <tr class="hover:bg-white/5">
                            <td class="px-3 py-2 font-mono text-xs">{{ $item->masked('mcc_developer_token') }}</td>
                            <td class="px-3 py-2 font-mono text-xs">{{ $item->masked('customer_id') }}</td>
                            <td class="px-3 py-2 font-mono text-xs">{{ $item->masked('mcc_id') }}</td>
                            <td class="px-3 py-2">{{ optional($item->access_token_expires_at)->format('Y-m-d H:i') ?: '—' }}</td>
                            <td class="px-3 py-2">
                                <span class="px-2 py-1 rounded-lg text-xs border {{ $item->is_active ? 'bg-emerald-500/10 border-emerald-300/20 text-emerald-200' : 'bg-white/10 border-white/10 text-white/70' }}">
                                    {{ $item->is_active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <a class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs"
                                       href="{{ route('google-ads.credentials.show', $item) }}">
                                        Ver
                                    </a>
                                    <a class="px-3 py-1.5 rounded-lg bg-indigo-500/20 hover:bg-indigo-500/30 border border-white/10 text-xs"
                                       href="{{ route('google-ads.credentials.edit', $item) }}">
                                        Editar
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-3 py-8 text-center text-white/60" colspan="6">No hay credencial global de Google Ads registrada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $items->links() }}</div>
    </div>
@endsection

@extends('meta.layout')

@section('title', 'Plantilla Google Ads')
@section('subtitle', $template->name)

@section('header_actions')
    <a href="{{ route('google-ads.conversion-templates.edit', $template) }}"
       class="rounded-xl border border-white/10 bg-indigo-500/30 px-4 py-2 text-white hover:bg-indigo-500/40">
        Editar
    </a>
    <a href="{{ route('google-ads.conversion-templates.index') }}"
       class="rounded-xl border border-white/10 bg-white/10 px-4 py-2 text-white hover:bg-white/15">
        Volver
    </a>
@endsection

@section('content')
    @php
        $actorLabels = [
            'user' => 'Usuario',
            'job' => 'Job',
            'ai_connector' => 'Conector IA',
            'system' => 'Sistema',
        ];
    @endphp

    <div class="space-y-4">
        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 p-6 text-white/80 backdrop-blur">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <div class="text-sm text-white/50">Estado LQ</div>
                    <div class="mt-1">
                        <span class="rounded-lg border px-2 py-1 text-xs {{ $template->estado_lq ? 'border-emerald-300/20 bg-emerald-500/10 text-emerald-200' : 'border-white/10 bg-white/10 text-white/70' }}">
                            {{ $template->estado_lq ? 'Activo' : 'Inactivo' }}
                        </span>
                    </div>
                </div>
                <div>
                    <div class="text-sm text-white/50">Categoria</div>
                    <div class="mt-1">{{ $template->category }}</div>
                </div>
                <div>
                    <div class="text-sm text-white/50">Tipo</div>
                    <div class="mt-1">{{ $template->type }}</div>
                </div>
                <div>
                    <div class="text-sm text-white/50">Status Google</div>
                    <div class="mt-1">{{ $template->google_status }}</div>
                </div>
                <div>
                    <div class="text-sm text-white/50">Primary for goal</div>
                    <div class="mt-1">{{ $template->primary_for_goal ? 'Si' : 'No' }}</div>
                </div>
                <div>
                    <div class="text-sm text-white/50">Click-through</div>
                    <div class="mt-1">{{ $template->click_through_lookback_window_days }} dias</div>
                </div>
                <div>
                    <div class="text-sm text-white/50">Valor</div>
                    <div class="mt-1">{{ number_format((float) $template->default_value, 2, '.', ',') }}</div>
                </div>
                <div>
                    <div class="text-sm text-white/50">Divisa</div>
                    <div class="mt-1 font-mono">{{ $template->default_currency_code }}</div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 p-4 backdrop-blur">
            <div class="text-sm font-semibold text-white">Payload Google</div>
            <pre class="mt-3 max-h-96 overflow-auto whitespace-pre-wrap rounded-xl bg-slate-900/70 p-3 text-xs text-white/80">{{ json_encode(['create' => $template->toGoogleCreatePayload()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>

        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 p-4 backdrop-blur">
            <div class="mb-3 text-sm font-semibold text-white">Historial reciente</div>
            <div class="overflow-x-auto rounded-xl border border-white/10">
                <table class="w-full min-w-[1100px] text-xs">
                    <thead class="bg-white/5 text-white/70">
                        <tr>
                            <th class="px-3 py-2 text-left">Fecha</th>
                            <th class="px-3 py-2 text-left">Customer</th>
                            <th class="px-3 py-2 text-left">Accion</th>
                            <th class="px-3 py-2 text-left">Origen</th>
                            <th class="px-3 py-2 text-left">Resultado</th>
                            <th class="px-3 py-2 text-left">Request</th>
                            <th class="px-3 py-2 text-left">Error</th>
                            <th class="px-3 py-2 text-left">Payload</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10 text-white/80">
                        @forelse($template->histories as $history)
                            <tr class="align-top">
                                <td class="px-3 py-2 whitespace-nowrap">{{ optional($history->created_at)->format('Y-m-d H:i') }}</td>
                                <td class="px-3 py-2">
                                    {{ $history->customer?->name ?? '—' }}
                                    <div class="text-white/50">{{ $history->google_ads_customer_id ?: '—' }}</div>
                                </td>
                                <td class="px-3 py-2">{{ $history->action }}</td>
                                <td class="px-3 py-2">
                                    {{ $actorLabels[$history->actor_type] ?? $history->actor_type }}
                                    <div class="text-white/50">{{ $history->actor_name ?: '—' }}</div>
                                </td>
                                <td class="px-3 py-2">
                                    <span class="rounded-lg border px-2 py-1 {{ $history->success ? 'border-emerald-300/20 bg-emerald-500/10 text-emerald-200' : 'border-rose-300/20 bg-rose-500/10 text-rose-200' }}">
                                        {{ $history->success ? 'OK' : 'Error' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 break-all">{{ $history->request_id ?: '—' }}</td>
                                <td class="px-3 py-2 max-w-xs break-words">{{ $history->error_message ?: '—' }}</td>
                                <td class="px-3 py-2">
                                    <details>
                                        <summary class="cursor-pointer text-white/60">Ver</summary>
                                        <pre class="mt-2 max-h-56 overflow-auto whitespace-pre-wrap rounded-lg bg-slate-900/70 p-2">{{ json_encode($history->payload ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                    </details>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-8 text-center text-white/60">Sin historial para esta plantilla.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

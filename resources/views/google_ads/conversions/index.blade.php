@extends('meta.layout')

@section('title', 'Google Ads Conversion Jobs')
@section('subtitle', 'Envios server-side uploadClickConversions')

@section('header_actions')
    <a href="{{ route('crmstates.index') }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        CRM States
    </a>
@endsection

@section('content')
    <div class="space-y-4">
        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                <div class="md:col-span-3">
                    <label class="block mb-1 text-white/70">Lead ID</label>
                    <input name="lead_id" value="{{ request('lead_id') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                </div>
                <div class="md:col-span-5">
                    <label class="block mb-1 text-white/70">Customer</label>
                    <select name="customer_id" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                        <option value="">Todos</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((string) request('customer_id') === (string) $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-4 flex gap-2">
                    <button class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">Filtrar</button>
                    <a href="{{ route('google-ads.conversion-jobs.index') }}" class="px-4 py-2 rounded-xl bg-zinc-950/25 hover:bg-white/10 text-white border border-white/10">Limpiar</a>
                </div>
            </form>
        </div>

        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">
            <h3 class="text-white font-semibold">Jobs en proceso</h3>

            <div class="overflow-x-auto rounded-xl border border-white/10">
                <table class="min-w-full text-xs">
                    <thead class="bg-white/5 text-white/70">
                        <tr>
                            <th class="text-left px-3 py-2">ID</th>
                            <th class="text-left px-3 py-2">Lead</th>
                            <th class="text-left px-3 py-2">Customer</th>
                            <th class="text-left px-3 py-2">CrmState</th>
                            <th class="text-left px-3 py-2">Conversion</th>
                            <th class="text-left px-3 py-2">Order ID</th>
                            <th class="text-left px-3 py-2">Click</th>
                            <th class="text-left px-3 py-2">Intentos</th>
                            <th class="text-left px-3 py-2">Resultado</th>
                            <th class="text-left px-3 py-2">Fechas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10 text-white/80">
                        @forelse($jobs as $job)
                            <tr class="hover:bg-white/5 align-top">
                                <td class="px-3 py-2">{{ $job->id }}</td>
                                <td class="px-3 py-2">{{ $job->lead_id }}</td>
                                <td class="px-3 py-2">{{ $job->customer?->name ?? $job->customer_id ?? '--' }}</td>
                                <td class="px-3 py-2">
                                    {{ $job->status ?? $job->crmState?->name ?? '--' }}
                                    <div class="text-white/50">{{ $job->crm_state_id ?? '--' }}</div>
                                </td>
                                <td class="px-3 py-2">
                                    {{ $job->conversion_action_id ?? '--' }}
                                    <div class="text-white/50 max-w-xs truncate">{{ $job->conversion_action_resource_name ?? '--' }}</div>
                                </td>
                                <td class="px-3 py-2">{{ $job->order_id ?? '--' }}</td>
                                <td class="px-3 py-2">
                                    {{ $job->click_identifier_type ?? '--' }}
                                    <div class="text-white/50">{{ \App\Support\SensitiveValue::redact($job->click_identifier_value) }}</div>
                                </td>
                                <td class="px-3 py-2">{{ $job->attempts }}</td>
                                <td class="px-3 py-2">
                                    <div>{{ $job->success ? 'Success' : 'No enviado' }}</div>
                                    <div class="text-white/50">Partial: {{ $job->partial_failure ? 'Si' : 'No' }}</div>
                                    @if($job->error_message)
                                        <div class="text-rose-200 max-w-xs truncate">{{ $job->error_message }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2">
                                    <div>{{ optional($job->created_at)->format('Y-m-d H:i') }}</div>
                                    <div class="text-white/50">{{ optional($job->updated_at)->format('Y-m-d H:i') }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-3 py-8 text-center text-white/60">No hay jobs registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $jobs->links() }}</div>
        </div>

        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">
            <h3 class="text-white font-semibold">Jobs fallidos</h3>

            <div class="overflow-x-auto rounded-xl border border-white/10">
                <table class="min-w-full text-xs">
                    <thead class="bg-white/5 text-white/70">
                        <tr>
                            <th class="text-left px-3 py-2">ID</th>
                            <th class="text-left px-3 py-2">Lead</th>
                            <th class="text-left px-3 py-2">Customer</th>
                            <th class="text-left px-3 py-2">CrmState</th>
                            <th class="text-left px-3 py-2">Motivo</th>
                            <th class="text-left px-3 py-2">Respuesta</th>
                            <th class="text-left px-3 py-2">Intentos</th>
                            <th class="text-left px-3 py-2">Fechas</th>
                            <th class="text-left px-3 py-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10 text-white/80">
                        @forelse($failedJobs as $failed)
                            <tr class="hover:bg-white/5 align-top">
                                <td class="px-3 py-2">{{ $failed->id }}</td>
                                <td class="px-3 py-2">{{ $failed->lead_id }}</td>
                                <td class="px-3 py-2">{{ $failed->customer?->name ?? $failed->customer_id ?? '--' }}</td>
                                <td class="px-3 py-2">
                                    {{ $failed->status ?? $failed->crmState?->name ?? '--' }}
                                    <div class="text-white/50">{{ $failed->crm_state_id ?? '--' }}</div>
                                </td>
                                <td class="px-3 py-2 max-w-sm">
                                    <div class="text-rose-200">{{ $failed->error_message ?? '--' }}</div>
                                    @if($failed->exception)
                                        <details class="mt-1">
                                            <summary class="cursor-pointer text-white/50">Exception</summary>
                                            <pre class="mt-2 whitespace-pre-wrap text-white/60">{{ \Illuminate\Support\Str::limit($failed->exception, 1200) }}</pre>
                                        </details>
                                    @endif
                                </td>
                                <td class="px-3 py-2 max-w-sm">
                                    <pre class="whitespace-pre-wrap text-white/60">{{ \Illuminate\Support\Str::limit($failed->response ?? '--', 800) }}</pre>
                                </td>
                                <td class="px-3 py-2">{{ $failed->attempts }}</td>
                                <td class="px-3 py-2">
                                    <div>Fallo: {{ optional($failed->failed_at)->format('Y-m-d H:i') ?? '--' }}</div>
                                    <div class="text-white/50">Reintento: {{ optional($failed->retried_at)->format('Y-m-d H:i') ?? '--' }}</div>
                                </td>
                                <td class="px-3 py-2">
                                    <form method="POST" action="{{ route('google-ads.failed-jobs.retry', $failed) }}">
                                        @csrf
                                        <button class="px-3 py-1.5 rounded-lg bg-indigo-500/20 hover:bg-indigo-500/30 border border-white/10 text-xs">
                                            Reprocesar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-3 py-8 text-center text-white/60">No hay jobs fallidos.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $failedJobs->links() }}</div>
        </div>
    </div>
@endsection

@extends('meta.layout')

@section('title', 'Detalle Customer')
@section('subtitle', 'Resumen del cliente y credenciales asociadas')

@section('header_actions')
    <a href="{{ route('customers.edit', $customer) }}"
       class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">
        Editar
    </a>
    <a href="{{ route('customers.index') }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
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

    @if (session('created_token'))
        <div id="tokenModalBackdrop" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
            <div class="w-full max-w-lg rounded-2xl border border-white/10 bg-zinc-950/95 p-6 shadow-xl shadow-black/30">
                <h3 class="text-lg font-semibold text-white">
                    Token generado
                </h3>

                <p class="mt-2 text-sm text-white/70">
                    Este es el token real y solo se muestra una vez. Cópialo ahora.
                </p>

                <div class="mt-4">
                    <label class="text-sm text-white/70">Customer ID</label>
                    <input
                        readonly
                        value="{{ $customer->id }}"
                        class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white"
                    />
                </div>

                <div class="mt-4">
                    <label class="text-sm text-white/70">Token</label>
                    <div class="mt-1 flex gap-2">
                        <input
                            id="createdTokenInput"
                            readonly
                            value="{{ session('created_token') }}"
                            class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white"
                        />
                        <button
                            type="button"
                            onclick="copyCreatedToken()"
                            class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10"
                        >
                            Copiar
                        </button>
                    </div>
                    <p id="copyMsg" class="mt-2 hidden text-xs text-emerald-300">Copiado</p>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        onclick="closeTokenModal()"
                        class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10"
                    >
                        Cerrar
                    </button>
                </div>
            </div>
        </div>

        <script>
            function copyCreatedToken() {
                const input = document.getElementById('createdTokenInput');
                input.select();
                input.setSelectionRange(0, 99999);
                navigator.clipboard.writeText(input.value);

                const msg = document.getElementById('copyMsg');
                msg.classList.remove('hidden');
                setTimeout(() => msg.classList.add('hidden'), 1200);
            }

            function closeTokenModal() {
                const backdrop = document.getElementById('tokenModalBackdrop');
                if (backdrop) backdrop.remove();
            }
        </script>
    @endif

    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-6 text-white/80">
        <div class="grid gap-4">
            <div>
                <div class="text-sm text-white/50">Nombre</div>
                <div class="mt-1">{{ $customer->name }}</div>
            </div>

            <div>
                <div class="text-sm text-white/50">Status</div>
                <div class="mt-1">
                    <span class="px-2 py-1 rounded-lg text-xs border {{ (int) $customer->status === 1 ? 'bg-emerald-500/10 border-emerald-300/20 text-emerald-200' : 'bg-white/10 border-white/10 text-white/70' }}">
                        {{ (int) $customer->status === 1 ? 'Activo' : 'Inactivo' }}
                    </span>
                </div>
            </div>

            <div>
                <div class="text-sm text-white/50">Descripción</div>
                <div class="mt-1">{{ $customer->description ?: '—' }}</div>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                <h3 class="text-base font-semibold text-white">Conjuntos de datos</h3>

                <div class="mt-4 grid grid-cols-1 xl:grid-cols-3 gap-4">
                    <section class="rounded-xl border border-white/10 bg-slate-900/40 p-4 space-y-3">
                        <div>
                            <div class="text-sm font-semibold text-white">Conjuntos de datos pixel</div>
                            <div class="mt-1 text-sm text-white/60">Se pondra el pixel de las landing page o Web.</div>
                        </div>

                        <div>
                            <div class="text-sm text-white/50">FB Pixel ID</div>
                            <div class="mt-1 break-all">{{ $customer->fb_pixel_id ?: '—' }}</div>
                        </div>

                        <div>
                            <div class="text-sm text-white/50">FB Access Token</div>
                            <div class="mt-2 text-sm break-all rounded-xl border border-white/10 bg-slate-900/60 p-3">
                                {{ $customer->fb_access_token ?: '—' }}
                            </div>
                        </div>
                    </section>

                    <section class="rounded-xl border border-white/10 bg-slate-900/40 p-4 space-y-3">
                        <div>
                            <div class="text-sm font-semibold text-white">Conjuntos de datos formulario instantaneo</div>
                            <div class="mt-1 text-sm text-white/60">Se pondra el conjunto de datos para medir por separado los formularios instantaneos de meta o de CRM.</div>
                        </div>

                        <div>
                            <div class="text-sm text-white/50">Meta_dataset</div>
                            <div class="mt-1">
                                <span class="px-2 py-1 rounded-lg text-xs border {{ (int) $customer->Meta_dataset === 1 ? 'bg-emerald-500/10 border-emerald-300/20 text-emerald-200' : 'bg-white/10 border-white/10 text-white/70' }}">
                                    {{ (int) $customer->Meta_dataset === 1 ? 'Activo' : 'Inactivo' }}
                                </span>
                            </div>
                        </div>

                        <div>
                            <div class="text-sm text-white/50">Meta_dataset_id</div>
                            <div class="mt-1 break-all">{{ $customer->Meta_dataset_id ?: '—' }}</div>
                        </div>

                        <div>
                            <div class="text-sm text-white/50">Meta_dataset_token</div>
                            <div class="mt-2 text-sm break-all rounded-xl border border-white/10 bg-slate-900/60 p-3">
                                {{ $customer->Meta_dataset_token ?: '—' }}
                            </div>
                        </div>
                    </section>

                    <section class="rounded-xl border border-white/10 bg-slate-900/40 p-4 space-y-3">
                        <div>
                            <div class="text-sm font-semibold text-white">Conjunto de datos WhatsApp</div>
                            <div class="mt-1 text-sm text-white/60">Se usara para medir por separado los leads que llegan desde conversaciones de WhatsApp.</div>
                        </div>

                        <div>
                            <div class="text-sm text-white/50">Meta_dataset WhatsApp</div>
                            <div class="mt-1">
                                <span class="px-2 py-1 rounded-lg text-xs border {{ (int) $customer->Meta_whatsapp_dataset === 1 ? 'bg-emerald-500/10 border-emerald-300/20 text-emerald-200' : 'bg-white/10 border-white/10 text-white/70' }}">
                                    {{ (int) $customer->Meta_whatsapp_dataset === 1 ? 'Activo' : 'Inactivo' }}
                                </span>
                            </div>
                        </div>

                        <div>
                            <div class="text-sm text-white/50">Meta_dataset_id</div>
                            <div class="mt-1 break-all">{{ $customer->Meta_whatsapp_dataset_id ?: '—' }}</div>
                        </div>

                        <div>
                            <div class="text-sm text-white/50">Meta_dataset_token</div>
                            <div class="mt-2 text-sm break-all rounded-xl border border-white/10 bg-slate-900/60 p-3">
                                {{ $customer->Meta_whatsapp_dataset_token ?: '—' }}
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <div>
                <div class="text-sm text-white/50">FB Test Event Code</div>
                <div class="mt-1">{{ $customer->fb_test_event_code ?: '—' }}</div>
            </div>

            <div>
                <livewire:google-ads-customer-account-select :selected-account-id="$customer->id_Gads" :readonly="true" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <div class="text-sm text-white/50">Divisa predeterminada</div>
                    <div class="mt-1">
                        {{ $customer->defaultCurrency?->code ?? 'COP' }}
                        @if($customer->defaultCurrency?->name)
                            - {{ $customer->defaultCurrency->name }}
                        @endif
                    </div>
                </div>

                <div>
                    <div class="text-sm text-white/50">Valor minimo predeterminado</div>
                    <div class="mt-1">{{ number_format((float) ($customer->default_lead_value ?? 100000), 2, '.', ',') }}</div>
                </div>
            </div>

            <div>
                <div class="text-sm text-white/50">Token guardado en BD (hash)</div>
                <div class="mt-2 text-sm break-all rounded-xl border border-white/10 bg-slate-900/60 p-3 font-mono">
                    {{ $customer->token }}
                </div>
            </div>

            <div>
                <div class="text-sm text-white/50">Meta Pages asociadas</div>
                <div class="mt-2 space-y-2">
                    @forelse($customer->metaPages as $metaPage)
                        <div class="text-sm break-all rounded-xl border border-white/10 bg-white/5 p-3">
                            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                                <div class="min-w-0">
                                    <div class="font-semibold text-white break-all">{{ $metaPage->name }}</div>
                                    <div class="text-xs font-mono text-white/60 break-all">{{ $metaPage->meta_page_id }}</div>
                                </div>

                                <span class="inline-flex w-fit rounded-lg border px-2 py-1 text-xs font-semibold {{ $metaPage->is_leadgen_subscribed ? 'bg-emerald-500/15 border-emerald-300/30 text-emerald-200' : 'bg-rose-500/15 border-rose-300/30 text-rose-200' }}">
                                    Leadgen: {{ $metaPage->is_leadgen_subscribed ? 'Suscrita' : 'No suscrita' }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="text-sm break-all rounded-xl border border-white/10 bg-white/5 p-3 text-white/60">
                            Sin páginas asignadas.
                        </div>
                    @endforelse
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between gap-3">
                    <div class="text-sm text-white/50">Meta Ad Accounts asociadas</div>
                    <a href="{{ route('meta.ad-accounts.create', ['customer_id' => $customer->id]) }}"
                       class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs text-white">
                        + Nueva cuenta
                    </a>
                </div>

                <div class="mt-2 space-y-2">
                    @forelse($customer->metaAdAccounts as $account)
                        <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-white break-all">{{ $account->meta_account_id }}</div>
                                    <div class="text-sm text-white/60">{{ $account->name ?: 'Sin nombre' }}</div>
                                    <div class="mt-1 text-xs text-white/50">
                                        Token: {{ is_null($account->token_can_view_account) ? 'Sin validar' : ($account->token_can_view_account ? 'Si' : 'No') }}
                                    </div>
                                    <div class="mt-1 text-xs text-white/50">
                                        Relacionada con: {{ $account->customers?->pluck('name')->implode(', ') ?: $customer->name }}
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex rounded-lg border px-2 py-1 text-xs font-semibold {{ $account->is_subscribed_to_meta_app ? 'bg-emerald-500/15 border-emerald-300/30 text-emerald-200' : 'bg-rose-500/15 border-rose-300/30 text-rose-200' }}">
                                        Meta app: {{ $account->is_subscribed_to_meta_app ? 'Suscrita' : 'No suscrita' }}
                                    </span>

                                    <span class="px-2 py-1 rounded-lg text-xs border {{ $account->status === 'active' ? 'bg-emerald-500/10 border-emerald-300/20 text-emerald-200' : 'bg-white/10 border-white/10 text-white/70' }}">
                                        {{ $account->status }}
                                    </span>

                                    @if((bool) $account->pivot->is_default_for_whatsapp_leads)
                                        <span class="px-2 py-1 rounded-lg text-xs border border-emerald-300/20 bg-emerald-500/10 text-emerald-200">
                                            Default WhatsApp
                                        </span>
                                    @endif

                                    <a href="{{ route('meta.ad-accounts.edit', $account) }}"
                                       class="px-3 py-1.5 rounded-lg bg-indigo-500/20 hover:bg-indigo-500/30 border border-white/10 text-xs text-white">
                                        Editar
                                    </a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-sm break-all rounded-xl border border-white/10 bg-white/5 p-3 text-white/60">
                            Sin Meta Ad Accounts asociadas.
                        </div>
                    @endforelse
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between gap-3">
                    <div class="text-sm text-white/50">Meta WhatsApp asociadas</div>
                    <a href="{{ route('meta.whatsapps.create', ['customer_id' => $customer->id]) }}"
                       class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs text-white">
                        + Nueva WhatsApp
                    </a>
                </div>

                <div class="mt-2 space-y-2">
                    @forelse($customer->metaWhatsapps as $whatsapp)
                        <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-white break-all">{{ $whatsapp->waba_id }}</div>
                                    <div class="text-sm text-white/60 break-all">Phone: {{ $whatsapp->phone_number_id ?: '-' }}</div>
                                    <div class="text-sm text-white/60 break-all">WA ID: {{ $whatsapp->wa_id ?: '-' }}</div>
                                    <div class="mt-1 text-xs text-white/50">
                                        Token: {{ is_null($whatsapp->token_can_view_account) ? 'Sin validar' : ($whatsapp->token_can_view_account ? 'Si' : 'No') }}
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex rounded-lg border px-2 py-1 text-xs font-semibold {{ $whatsapp->is_subscribed_to_meta_app ? 'bg-emerald-500/15 border-emerald-300/30 text-emerald-200' : 'bg-rose-500/15 border-rose-300/30 text-rose-200' }}">
                                        Meta app: {{ $whatsapp->is_subscribed_to_meta_app ? 'Suscrita' : 'No suscrita' }}
                                    </span>

                                    <span class="px-2 py-1 rounded-lg text-xs border {{ $whatsapp->status ? 'bg-emerald-500/10 border-emerald-300/20 text-emerald-200' : 'bg-white/10 border-white/10 text-white/70' }}">
                                        {{ $whatsapp->status ? 'Activo' : 'Inactivo' }}
                                    </span>

                                    <a href="{{ route('meta.whatsapps.edit', $whatsapp) }}"
                                       class="px-3 py-1.5 rounded-lg bg-indigo-500/20 hover:bg-indigo-500/30 border border-white/10 text-xs text-white">
                                        Editar
                                    </a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-sm break-all rounded-xl border border-white/10 bg-white/5 p-3 text-white/60">
                            Sin Meta WhatsApp asociadas.
                        </div>
                    @endforelse
                </div>
            </div>

            <div>
                <div class="text-sm text-white/50">Historial Customer</div>
                <div class="mt-2 overflow-x-auto rounded-xl border border-white/10">
                    <table class="w-full min-w-[900px] text-xs">
                        <thead class="bg-white/5 text-white/70">
                            <tr>
                                <th class="px-3 py-2 text-left">Fecha</th>
                                <th class="px-3 py-2 text-left">Acción</th>
                                <th class="px-3 py-2 text-left">Origen</th>
                                <th class="px-3 py-2 text-left">Anterior</th>
                                <th class="px-3 py-2 text-left">Actualizado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10 text-white/80">
                            @forelse($customer->actionHistories as $history)
                                <tr class="align-top">
                                    <td class="px-3 py-2 whitespace-nowrap">{{ optional($history->created_at)->format('Y-m-d H:i') }}</td>
                                    <td class="px-3 py-2">{{ $history->action }}</td>
                                    <td class="px-3 py-2">
                                        {{ $actorLabels[$history->actor_type] ?? $history->actor_type }}
                                        <div class="text-white/50">{{ $history->actor_name ?: '—' }}</div>
                                    </td>
                                    <td class="px-3 py-2">
                                        <pre class="max-h-40 overflow-auto whitespace-pre-wrap rounded-lg bg-slate-900/60 p-2">{{ json_encode($history->old_values ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                                    </td>
                                    <td class="px-3 py-2">
                                        <pre class="max-h-40 overflow-auto whitespace-pre-wrap rounded-lg bg-slate-900/60 p-2">{{ json_encode($history->new_values ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-6 text-center text-white/60">Sin historial de Customer.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <div class="text-sm text-white/50">Historial plantillas Google Ads</div>
                <div class="mt-2 overflow-x-auto rounded-xl border border-white/10">
                    <table class="w-full min-w-[1000px] text-xs">
                        <thead class="bg-white/5 text-white/70">
                            <tr>
                                <th class="px-3 py-2 text-left">Fecha</th>
                                <th class="px-3 py-2 text-left">Plantilla</th>
                                <th class="px-3 py-2 text-left">Acción</th>
                                <th class="px-3 py-2 text-left">Origen</th>
                                <th class="px-3 py-2 text-left">Resultado</th>
                                <th class="px-3 py-2 text-left">Detalle</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10 text-white/80">
                            @forelse($customer->googleAdsConversionTemplateHistories as $history)
                                <tr class="align-top">
                                    <td class="px-3 py-2 whitespace-nowrap">{{ optional($history->created_at)->format('Y-m-d H:i') }}</td>
                                    <td class="px-3 py-2">{{ $history->template_name ?: '—' }}</td>
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
                                    <td class="px-3 py-2">
                                        @if($history->error_message)
                                            <div class="text-rose-200">{{ $history->error_message }}</div>
                                        @endif
                                        <div class="text-white/50">Google Ads ID: {{ $history->google_ads_customer_id ?: '—' }}</div>
                                        @if($history->request_id)
                                            <div class="text-white/50">Request: {{ $history->request_id }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-3 py-6 text-center text-white/60">Sin historial de plantillas Google Ads.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

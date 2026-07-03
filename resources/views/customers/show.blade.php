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

            <div>
                <div class="text-sm text-white/50">FB Pixel ID</div>
                <div class="mt-1">{{ $customer->fb_pixel_id ?: '—' }}</div>
            </div>

            <div>
                <div class="text-sm text-white/50">FB Access Token</div>
                <div class="mt-2 text-sm break-all rounded-xl border border-white/10 bg-slate-900/60 p-3">
                    {{ $customer->fb_access_token ?: '—' }}
                </div>
            </div>

            <div>
                <div class="text-sm text-white/50">ID Google Ads</div>
                <div class="mt-1">{{ $customer->id_Gads ?: '—' }}</div>
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
                            {{ $metaPage->name }} ({{ $metaPage->meta_page_id }})
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
                                </div>

                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-1 rounded-lg text-xs border {{ $account->status === 'active' ? 'bg-emerald-500/10 border-emerald-300/20 text-emerald-200' : 'bg-white/10 border-white/10 text-white/70' }}">
                                        {{ $account->status }}
                                    </span>

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
        </div>
    </div>
@endsection

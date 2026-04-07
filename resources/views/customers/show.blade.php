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
                    Token generado âœ…
                </h3>

                <p class="mt-2 text-sm text-white/70">
                    Este es el token REAL (solo se muestra una vez). CÃ³pialo ahora.
                </p>

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
                    <p id="copyMsg" class="mt-2 hidden text-xs text-emerald-300">Copiado âœ…</p>
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
                    <span class="px-2 py-1 rounded-lg text-xs border {{ (int)$customer->status === 1 ? 'bg-emerald-500/10 border-emerald-300/20 text-emerald-200' : 'bg-white/10 border-white/10 text-white/70' }}">
                        {{ (int)$customer->status === 1 ? 'Activo' : 'Inactivo' }}
                    </span>
                </div>
            </div>

            <div>
                <div class="text-sm text-white/50">DescripciÃ³n</div>
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
                            Sin pÃ¡ginas asignadas.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection

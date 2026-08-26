<x-app-layout>
    <div class="mx-auto flex min-h-screen max-w-3xl items-center p-4">
        <section class="w-full rounded-2xl border border-white/10 bg-zinc-950/40 p-6 text-white shadow-xl backdrop-blur">
            <p class="text-sm font-semibold text-indigo-200">Conector IA</p>
            <h1 class="mt-2 text-2xl font-bold">Autorizar acceso de solo lectura</h1>
            <p class="mt-3 text-sm text-white/70">
                Claude solicita conectar con <span class="font-semibold text-white">{{ $connector->name }}</span>.
                El conector solo puede consultar datos agregados del dashboard general de leads.
            </p>

            <div class="mt-5 rounded-xl border border-white/10 bg-white/5 p-4">
                <p class="text-sm font-semibold text-white">Permisos solicitados</p>
                <ul class="mt-3 space-y-2 text-sm text-white/75">
                    @foreach($scopes as $scope)
                        <li class="rounded-lg border border-white/10 bg-slate-950/50 px-3 py-2">{{ $scope }}</li>
                    @endforeach
                </ul>
                <p class="mt-3 text-xs text-white/50">
                    No habilita crear, editar, eliminar, listar ni exportar informacion personal de leads.
                </p>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <form method="POST" action="{{ route('ai-connectors.oauth.authorize.approve') }}">
                    @csrf
                    @foreach($query as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <button class="w-full rounded-xl border border-emerald-300/20 bg-emerald-500/20 px-4 py-3 font-semibold text-emerald-100 hover:bg-emerald-500/30">
                        Autorizar
                    </button>
                </form>

                <form method="POST" action="{{ route('ai-connectors.oauth.authorize.deny') }}">
                    @csrf
                    @foreach($query as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <button class="w-full rounded-xl border border-white/10 bg-white/10 px-4 py-3 font-semibold text-white/80 hover:bg-white/15">
                        Cancelar
                    </button>
                </form>
            </div>
        </section>
    </div>
</x-app-layout>

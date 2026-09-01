@extends('meta.layout')

@section('title', 'Plantillas conversiones Google Ads')
@section('subtitle', 'CRUD independiente para conversion actions creadas desde estado LQ')

@section('header_actions')
    <a href="{{ route('google-ads.index') }}"
       class="rounded-xl border border-white/10 bg-white/10 px-4 py-2 text-white hover:bg-white/15">
        Google Ads
    </a>
    <a href="{{ route('google-ads.conversion-templates.create') }}"
       class="rounded-xl border border-white/10 bg-indigo-500/30 px-4 py-2 text-white hover:bg-indigo-500/40">
        + Nueva
    </a>
@endsection

@section('content')
    <div class="space-y-4">
        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 p-4 backdrop-blur">
            <form method="GET" class="grid grid-cols-1 gap-3 md:grid-cols-12 md:items-end">
                <div class="md:col-span-7">
                    <label class="block mb-1 text-white/70">Buscar</label>
                    <input name="q"
                           value="{{ $q }}"
                           class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white placeholder-white/40"
                           placeholder="Nombre, categoria o tipo" />
                </div>

                <div class="md:col-span-3">
                    <label class="block mb-1 text-white/70">Estado LQ</label>
                    <select name="estado_lq" class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white">
                        <option value="">Todos</option>
                        <option value="1" @selected($estadoLq === '1')>Activo</option>
                        <option value="0" @selected($estadoLq === '0')>Inactivo</option>
                    </select>
                </div>

                <div class="md:col-span-2 flex gap-2">
                    <button class="w-full rounded-xl border border-white/10 bg-white/10 px-4 py-2 text-white hover:bg-white/15">
                        Filtrar
                    </button>
                    <a href="{{ route('google-ads.conversion-templates.index') }}"
                       class="w-full rounded-xl border border-white/10 bg-zinc-950/25 px-4 py-2 text-center text-white hover:bg-white/10">
                        Limpiar
                    </a>
                </div>
            </form>
        </div>

        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 p-4 backdrop-blur">
            <form method="POST" action="{{ route('google-ads.conversion-templates.sync-customers') }}" class="grid grid-cols-1 gap-3 md:grid-cols-12 md:items-end">
                @csrf
                <div class="md:col-span-8">
                    <label class="block mb-1 text-white/70">Crear plantillas activas en Google Ads</label>
                    <select name="customer_id" class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white">
                        <option value="">Todos los customers con ID Google Ads</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }} - {{ $customer->id_Gads }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-4">
                    <button class="w-full rounded-xl border border-white/10 bg-emerald-500/20 px-4 py-2 text-emerald-100 hover:bg-emerald-500/30">
                        Enviar a cola tracking
                    </button>
                </div>
            </form>
        </div>

        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 p-4 backdrop-blur">
            <div class="mb-3 flex flex-col gap-2 text-sm text-white/60 sm:flex-row sm:items-center sm:justify-between">
                <div>Mostrando {{ $templates->firstItem() ?? 0 }}-{{ $templates->lastItem() ?? 0 }} de {{ $templates->total() }} plantillas</div>
                <div>{{ $templates->perPage() }} items por pagina</div>
            </div>

            <div class="w-full overflow-x-auto rounded-xl border border-white/10 [scrollbar-gutter:stable]" data-sortable-table-wrap>
                <div class="hidden px-3 py-2 text-xs text-white/50" data-sort-status>Ordenando...</div>
                <table class="w-full min-w-[1300px] table-fixed text-sm" data-sortable-table>
                    <thead class="bg-white/5 text-white/70">
                        <tr>
                            <x-sort-header :index="0" label="Nombre" class="w-72" />
                            <x-sort-header :index="1" label="Estado LQ" class="w-28" />
                            <x-sort-header :index="2" label="Categoria" class="w-44" />
                            <x-sort-header :index="3" label="Tipo" class="w-40" />
                            <x-sort-header :index="4" label="Status" class="w-28" />
                            <x-sort-header :index="5" label="Primary" class="w-28" />
                            <x-sort-header :index="6" label="Lookback" class="w-28" />
                            <x-sort-header :index="7" label="Valor" class="w-32" />
                            <x-sort-header :index="8" label="Divisa" class="w-24" />
                            <x-sort-header :index="9" label="Historial" class="w-28" />
                            <th class="w-64 px-3 py-2 text-left">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10 text-white/80">
                        @forelse($templates as $template)
                            <tr class="align-top hover:bg-white/5">
                                <td class="px-3 py-2 font-semibold text-white">{{ $template->name }}</td>
                                <td class="px-3 py-2">
                                    <span class="rounded-lg border px-2 py-1 text-xs {{ $template->estado_lq ? 'border-emerald-300/20 bg-emerald-500/10 text-emerald-200' : 'border-white/10 bg-white/10 text-white/70' }}">
                                        {{ $template->estado_lq ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2">{{ $template->category }}</td>
                                <td class="px-3 py-2">{{ $template->type }}</td>
                                <td class="px-3 py-2">{{ $template->google_status }}</td>
                                <td class="px-3 py-2">{{ $template->primary_for_goal ? 'Si' : 'No' }}</td>
                                <td class="px-3 py-2">{{ $template->click_through_lookback_window_days }} dias</td>
                                <td class="px-3 py-2">{{ number_format((float) $template->default_value, 2, '.', ',') }}</td>
                                <td class="px-3 py-2 font-mono">{{ $template->default_currency_code }}</td>
                                <td class="px-3 py-2">{{ $template->histories_count }}</td>
                                <td class="px-3 py-2">
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('google-ads.conversion-templates.show', $template) }}"
                                           class="rounded-lg border border-white/10 bg-white/10 px-3 py-1.5 text-xs text-white hover:bg-white/15">
                                            Ver
                                        </a>
                                        <a href="{{ route('google-ads.conversion-templates.edit', $template) }}"
                                           class="rounded-lg border border-white/10 bg-indigo-500/20 px-3 py-1.5 text-xs text-white hover:bg-indigo-500/30">
                                            Editar
                                        </a>
                                        <form method="POST"
                                              action="{{ route('google-ads.conversion-templates.destroy', $template) }}"
                                              onsubmit="return confirm('¿Seguro que deseas eliminar esta plantilla?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="rounded-lg border border-rose-300/20 bg-rose-500/20 px-3 py-1.5 text-xs text-rose-100 hover:bg-rose-500/30">
                                                Eliminar
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-3 py-8 text-center text-white/60">No hay plantillas de conversion.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $templates->links() }}</div>
        </div>
    </div>
@endsection

@extends('meta.layout')

@section('title', 'Detalle Meta Page')
@section('subtitle', 'Información de página, sync y formularios asociados')

@section('header_actions')
    <a href="{{ route('meta.pages.edit', $page) }}" class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">Editar</a>
    <a href="{{ route('meta.pages.index') }}" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">Volver</a>
@endsection

@section('content')
    <div class="grid gap-4">
        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-6 text-white/80 space-y-4">
            <div><span class="text-white/50">Cliente:</span> {{ $page->customer?->name ?? '—' }}</div>
            <div><span class="text-white/50">Meta Page ID:</span> {{ $page->meta_page_id }}</div>
            <div><span class="text-white/50">Nombre:</span> {{ $page->name }}</div>
            <div><span class="text-white/50">Estado CRM:</span> {{ $page->status ? 'Activa' : 'Inactiva' }}</div>
            <div><span class="text-white/50">Última sync:</span> {{ optional($page->last_synced_at)->format('Y-m-d H:i') ?: '—' }}</div>
            <div><span class="text-white/50">Último refresh token:</span> {{ optional($page->last_token_refresh_at)->format('Y-m-d H:i') ?: '—' }}</div>
            <div>
                <div class="text-white/50 mb-1">Page Access Token</div>
                <div class="rounded-xl border border-white/10 bg-slate-900/60 p-3 break-all text-xs">{{ $page->page_access_token ?: '—' }}</div>
            </div>
            <div>
                <div class="text-white/50 mb-1">Último error</div>
                <div class="rounded-xl border border-white/10 bg-slate-900/60 p-3 text-sm">{{ $page->last_error ?: 'Sin errores registrados' }}</div>
            </div>
        </div>

        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 text-white/80">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-white">Formularios asociados</h3>
                <a href="{{ route('meta.forms.create') }}" class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs">+ Nuevo Form</a>
            </div>
            @if($page->forms->isEmpty())
                <div class="text-white/60">No hay formularios asociados.</div>
            @else
                <div class="space-y-2">
                    @foreach($page->forms as $form)
                        <div class="rounded-xl border border-white/10 p-3 flex items-center justify-between gap-3">
                            <div>
                                <div class="font-medium text-white">{{ $form->name }}</div>
                                <div class="text-xs text-white/50">{{ $form->meta_form_id }} | {{ $form->status ? 'Activo' : 'Inactivo' }}</div>
                            </div>
                            <a href="{{ route('meta.forms.show', $form) }}" class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs">Ver</a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="flex gap-2">
            <form action="{{ route('meta.pages.sync-forms', $page) }}" method="POST">
                @csrf
                <button class="px-4 py-2 rounded-xl bg-emerald-500/20 hover:bg-emerald-500/30 border border-white/10 text-white">Sincronizar formularios</button>
            </form>
            <form action="{{ route('meta.pages.destroy', $page) }}" method="POST">
                @csrf
                @method('DELETE')
                <button class="px-4 py-2 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 border border-rose-300/20 text-white" onclick="return confirm('¿Eliminar página Meta?')">Eliminar</button>
            </form>
        </div>
    </div>
@endsection

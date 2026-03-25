@extends('meta.layout')

@section('title', 'Detalle Meta Form')
@section('subtitle', 'Formulario, mappings activos y campos disponibles')

@section('header_actions')
    <a href="{{ route('meta.forms.edit', $form) }}" class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">Editar</a>
    <a href="{{ route('meta.forms.index') }}" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">Volver</a>
@endsection

@section('content')
    <div class="grid gap-4">
        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-6 text-white/80 space-y-4">
            <div><span class="text-white/50">Cliente:</span> {{ $form->page?->customer?->name ?? '—' }}</div>
            <div><span class="text-white/50">Página:</span> {{ $form->page?->name ?? '—' }}</div>
            <div><span class="text-white/50">Meta Form ID:</span> {{ $form->meta_form_id }}</div>
            <div><span class="text-white/50">Nombre:</span> {{ $form->name }}</div>
            <div><span class="text-white/50">Locale:</span> {{ $form->locale ?: '—' }}</div>
            <div><span class="text-white/50">Estado CRM:</span> {{ $form->status ? 'Activo' : 'Inactivo' }}</div>
            <div><span class="text-white/50">Meta Status:</span> {{ $form->meta_status ?: '—' }}</div>
            <div><span class="text-white/50">Última sync:</span> {{ optional($form->last_synced_at)->format('Y-m-d H:i') ?: '—' }}</div>
            <div>
                <div class="text-white/50 mb-1">Último error</div>
                <div class="rounded-xl border border-white/10 bg-slate-900/60 p-3 text-sm">{{ $form->last_error ?: 'Sin errores registrados' }}</div>
            </div>
        </div>

        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 text-white/80">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-white">Mappings de campos</h3>
                <a href="{{ route('meta.form-field-mappings.create', ['meta_form_id' => $form->id]) }}" class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs">+ Nuevo mapping</a>
            </div>
            @if($form->fieldMappings->isEmpty())
                <div class="text-white/60">No hay mappings configurados.</div>
            @else
                <div class="space-y-2">
                    @foreach($form->fieldMappings as $mapping)
                        <div class="rounded-xl border border-white/10 p-3 flex items-center justify-between gap-3">
                            <div>
                                <div class="font-medium text-white">{{ $mapping->meta_field_name }} → {{ $mapping->lead_field_name }}</div>
                                <div class="text-xs text-white/50">{{ $mapping->is_required ? 'Requerido' : 'Opcional' }} | {{ $mapping->is_active ? 'Activo' : 'Inactivo' }}</div>
                            </div>
                            <a href="{{ route('meta.form-field-mappings.show', $mapping) }}" class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs">Ver</a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 text-white/80">
            <h3 class="font-semibold text-white mb-3">Campos detectados en Meta</h3>
            @if(empty($availableMetaFields))
                <div class="text-white/60">No hay preguntas disponibles en el payload guardado.</div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    @foreach($availableMetaFields as $field)
                        <div class="rounded-xl border border-white/10 p-3">
                            <div class="font-medium text-white">{{ $field['label'] }}</div>
                            <div class="text-xs text-white/50">{{ $field['name'] }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 text-white/80">
            <h3 class="font-semibold text-white mb-3">Raw Payload</h3>
            <pre class="rounded-xl border border-white/10 bg-slate-900/60 p-3 overflow-x-auto text-xs">{{ json_encode($form->raw_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>

        <div class="flex gap-2">
            <form action="{{ route('meta.forms.sync-leads', $form) }}" method="POST">
                @csrf
                <button class="px-4 py-2 rounded-xl bg-emerald-500/20 hover:bg-emerald-500/30 border border-white/10 text-white">Sincronizar leads</button>
            </form>
            <form action="{{ route('meta.forms.destroy', $form) }}" method="POST">
                @csrf
                @method('DELETE')
                <button class="px-4 py-2 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 border border-rose-300/20 text-white" onclick="return confirm('¿Eliminar formulario Meta?')">Eliminar</button>
            </form>
        </div>
    </div>
@endsection

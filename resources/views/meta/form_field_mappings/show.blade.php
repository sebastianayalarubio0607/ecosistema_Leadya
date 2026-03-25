@extends('meta.layout')

@section('title', 'Detalle Mapping')
@section('subtitle', 'Regla aplicada al transformar field_data en leads del CRM')

@section('header_actions')
    <a href="{{ route('meta.form-field-mappings.edit', $mapping) }}" class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">Editar</a>
    <a href="{{ route('meta.form-field-mappings.index') }}" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">Volver</a>
@endsection

@section('content')
    <div class="grid gap-4">
        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-6 text-white/80 space-y-4">
            <div><span class="text-white/50">Formulario:</span> {{ $mapping->form?->name ?? '—' }}</div>
            <div><span class="text-white/50">Meta Form ID:</span> {{ $mapping->form?->meta_form_id ?? '—' }}</div>
            <div><span class="text-white/50">Campo Meta:</span> {{ $mapping->meta_field_name ?: '—' }}</div>
            <div><span class="text-white/50">Campo Lead:</span> {{ $mapping->lead_field_name }}</div>
            <div><span class="text-white/50">Valor estático:</span> {{ $mapping->static_value ?: '—' }}</div>
            <div><span class="text-white/50">Requerido:</span> {{ $mapping->is_required ? 'Sí' : 'No' }}</div>
            <div><span class="text-white/50">Activo:</span> {{ $mapping->is_active ? 'Sí' : 'No' }}</div>
        </div>

        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 text-white/80">
            <h3 class="font-semibold text-white mb-3">Campos Meta detectados</h3>
            @if(empty($availableMetaFields))
                <div class="text-white/60">No hay campos detectados en el payload del formulario.</div>
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

        <div class="flex gap-2">
            <form action="{{ route('meta.form-field-mappings.destroy', $mapping) }}" method="POST">
                @csrf
                @method('DELETE')
                <button class="px-4 py-2 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 border border-rose-300/20 text-white" onclick="return confirm('¿Eliminar mapping?')">Eliminar</button>
            </form>
        </div>
    </div>
@endsection

@extends('meta.layout')

@section('title', 'Meta Form Field Mappings')
@section('subtitle', 'Mapeo entre field_data de Meta y columnas del modelo Lead')

@section('header_actions')
    <a href="{{ route('meta.form-field-mappings.create', request()->only('meta_form_id')) }}" class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">+ Nuevo</a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <div class="md:col-span-5">
                <label class="block mb-1 text-white/70">Buscar</label>
                <input name="search" value="{{ request('search') }}" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40" placeholder="Buscar por campo Meta o Lead">
            </div>
            <div class="md:col-span-5">
                <label class="block mb-1 text-white/70">Formulario</label>
                <select name="meta_form_id" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    <option value="">-- Todos --</option>
                    @foreach($forms as $form)
                        <option value="{{ $form->id }}" @selected((string) request('meta_form_id') === (string) $form->id)>{{ $form->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2 flex gap-2">
                <button class="w-full px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">Filtrar</button>
                <a href="{{ route('meta.form-field-mappings.index') }}" class="w-full text-center px-4 py-2 rounded-xl bg-zinc-950/25 hover:bg-white/10 text-white border border-white/10">Limpiar</a>
            </div>
        </form>

        <div class="overflow-x-auto rounded-xl border border-white/10">
            <table class="min-w-full text-sm">
                <thead class="bg-white/5 text-white/70">
                    <tr>
                        <th class="text-left px-3 py-2">Formulario</th>
                        <th class="text-left px-3 py-2">Campo Meta</th>
                        <th class="text-left px-3 py-2">Campo Lead</th>
                        <th class="text-left px-3 py-2">Valor estático</th>
                        <th class="text-left px-3 py-2">Requerido</th>
                        <th class="text-left px-3 py-2">Activo</th>
                        <th class="text-left px-3 py-2 w-56">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10 text-white/80">
                    @forelse($items as $item)
                        <tr class="hover:bg-white/5">
                            <td class="px-3 py-2">
                                <div class="font-medium text-white">{{ $item->form?->name ?? '—' }}</div>
                                <div class="text-xs text-white/50">{{ $item->form?->meta_form_id }}</div>
                            </td>
                            <td class="px-3 py-2">{{ $item->meta_field_name ?: '—' }}</td>
                            <td class="px-3 py-2">{{ $item->lead_field_name }}</td>
                            <td class="px-3 py-2">{{ $item->static_value ?: '—' }}</td>
                            <td class="px-3 py-2">{{ $item->is_required ? 'Sí' : 'No' }}</td>
                            <td class="px-3 py-2">{{ $item->is_active ? 'Sí' : 'No' }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <a class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs" href="{{ route('meta.form-field-mappings.show', $item) }}">Ver</a>
                                    <a class="px-3 py-1.5 rounded-lg bg-indigo-500/20 hover:bg-indigo-500/30 border border-white/10 text-xs" href="{{ route('meta.form-field-mappings.edit', $item) }}">Editar</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-8 text-center text-white/60">No hay mappings registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $items->links() }}</div>
    </div>
@endsection

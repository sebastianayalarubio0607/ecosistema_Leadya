@extends('meta.layout')

@section('title', 'Configurar Board Monday')
@section('subtitle', $integration->name . ' / ' . $board->name)

@section('header_actions')
    <a href="{{ route('integrations.show', $integration) }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        Volver a integracion
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-6 text-white/80">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="text-sm text-white/50">Board Monday</div>
                <div class="font-semibold">{{ $board->name }}</div>
                <div class="text-xs font-mono text-white/50">{{ $board->monday_board_id }}</div>
            </div>

            <form method="POST" action="{{ route('integrations.monday.boards.sync-details', [$integration, $board]) }}">
                @csrf
                <button class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10" type="submit">
                    Sincronizar grupos y columnas
                </button>
            </form>
        </div>

        <form method="POST" action="{{ route('integrations.monday.boards.update', [$integration, $board]) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block mb-1 text-white/70">Activo</label>
                    <select name="status" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" required>
                        <option value="1" @selected((string) old('status', (int) $board->status) === '1')>Si</option>
                        <option value="0" @selected((string) old('status', (int) $board->status) === '0')>No</option>
                    </select>
                    @error('status') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label class="block mb-1 text-white/70">Grupo destino *</label>
                    <select name="monday_group_id" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                        <option value="">Seleccione...</option>
                        @foreach($board->groups as $group)
                            <option value="{{ $group->monday_group_id }}" @selected(old('monday_group_id', $board->monday_group_id) === $group->monday_group_id)>
                                {{ $group->title }} ({{ $group->monday_group_id }})
                            </option>
                        @endforeach
                    </select>
                    @error('monday_group_id') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label class="block mb-1 text-white/70">Campo Lead que activa el board *</label>
                    <select name="condition_lead_field" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                        <option value="">Seleccione...</option>
                        @foreach($leadFields as $field)
                            <option value="{{ $field }}" @selected(old('condition_lead_field', $board->condition_lead_field) === $field)>{{ $field }}</option>
                        @endforeach
                    </select>
                    @error('condition_lead_field') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label class="block mb-1 text-white/70">Valor esperado *</label>
                    <input name="condition_expected_value"
                           value="{{ old('condition_expected_value', $board->condition_expected_value) }}"
                           class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white"
                           placeholder="Ej: Test Drive">
                    @error('condition_expected_value') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
                </div>
            </div>

            <div>
                <div class="mb-2 text-sm text-white/50">Mapeo Lead -> Monday columns</div>

                @error('mappings') <div class="mb-2 text-sm text-rose-300">{{ $message }}</div> @enderror

                <div class="overflow-x-auto rounded-xl border border-white/10">
                    <table class="min-w-full text-sm">
                        <thead class="bg-white/5 text-white/70">
                            <tr>
                                <th class="text-left px-3 py-2">Columna Monday</th>
                                <th class="text-left px-3 py-2">Tipo</th>
                                <th class="text-left px-3 py-2">Origen</th>
                                <th class="text-left px-3 py-2">Valor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10 text-white/80">
                            @forelse($board->columns as $index => $column)
                                @php($sourceType = old("mappings.{$index}.source_type", $column->mapping->source_type ?? ($column->mapping->lead_field_name ? 'lead_field' : 'lead_field')))
                                <tr class="hover:bg-white/5" data-mapping-row>
                                    <td class="px-3 py-2">
                                        <div class="font-semibold">{{ $column->title }}</div>
                                        <div class="text-xs font-mono text-white/50">{{ $column->monday_column_id }}</div>
                                    </td>
                                    <td class="px-3 py-2">{{ $column->type ?: 'sin tipo' }}</td>
                                    <td class="px-3 py-2">
                                        <input type="hidden" name="mappings[{{ $index }}][column_id]" value="{{ $column->id }}">
                                        <select name="mappings[{{ $index }}][source_type]"
                                                class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white"
                                                data-mapping-source>
                                            <option value="lead_field" @selected($sourceType === 'lead_field')>Campo Lead</option>
                                            <option value="fixed_value" @selected($sourceType === 'fixed_value')>Valor fijo</option>
                                        </select>
                                        @error("mappings.$index.source_type") <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
                                    </td>
                                    <td class="px-3 py-2 space-y-2">
                                        <div data-source-panel="lead_field" class="{{ $sourceType === 'fixed_value' ? 'hidden' : '' }}">
                                            <select name="mappings[{{ $index }}][lead_field_name]"
                                                    class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white">
                                                <option value="">-- Sin mapeo --</option>
                                                @foreach($leadFields as $field)
                                                    <option value="{{ $field }}" @selected(old("mappings.{$index}.lead_field_name", $column->mapping->lead_field_name ?? '') === $field)>
                                                        {{ $field }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error("mappings.$index.lead_field_name") <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
                                        </div>

                                        <div data-source-panel="fixed_value" class="{{ $sourceType === 'fixed_value' ? '' : 'hidden' }}">
                                            <input name="mappings[{{ $index }}][static_value]"
                                                   value="{{ old("mappings.{$index}.static_value", $column->mapping->static_value ?? '') }}"
                                                   class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white"
                                                   placeholder="Valor fijo a enviar a Monday">
                                            @error("mappings.$index.static_value") <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-3 py-8 text-center text-white/60" colspan="4">Aun no hay columnas sincronizadas para este board.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex gap-2">
                <button class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10" type="submit">
                    Guardar configuracion
                </button>
            </div>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-mapping-row]').forEach((row) => {
            const sourceSelect = row.querySelector('[data-mapping-source]');
            const panels = row.querySelectorAll('[data-source-panel]');

            const refreshPanels = () => {
                const current = sourceSelect?.value || 'lead_field';
                panels.forEach((panel) => {
                    panel.classList.toggle('hidden', panel.dataset.sourcePanel !== current);
                });
            };

            sourceSelect?.addEventListener('change', refreshPanels);
            refreshPanels();
        });
    });
    </script>
@endsection

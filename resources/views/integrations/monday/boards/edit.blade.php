<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                    Configurar Board Monday
                </h2>
                <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {{ $integration->name }} / {{ $board->name }}
                </div>
            </div>

            <div class="flex gap-2">
                <a href="{{ route('integrations.show', $integration) }}"
                   class="px-4 py-2 rounded bg-gray-200 dark:bg-gray-200">
                    Volver a integracion
                </a>
            </div>
        </div>
    </x-slot>

    <div class="p-6 max-w-6xl mx-auto space-y-6">
        @if (session('success'))
            <div class="p-3 rounded bg-green-100 text-green-800">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white dark:bg-gray-900 rounded shadow p-6 text-gray-800 dark:text-gray-200">
            <div class="flex flex-wrap items-center gap-3 justify-between mb-4">
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Board Monday</div>
                    <div class="font-semibold">{{ $board->name }}</div>
                    <div class="text-xs font-mono text-gray-500 dark:text-gray-400">{{ $board->monday_board_id }}</div>
                </div>

                <form method="POST" action="{{ route('integrations.monday.boards.sync-details', [$integration, $board]) }}">
                    @csrf
                    <button class="px-4 py-2 rounded bg-blue-600 text-white" type="submit">
                        Sincronizar grupos y columnas
                    </button>
                </form>
            </div>

            <form method="POST" action="{{ route('integrations.monday.boards.update', [$integration, $board]) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">Activo</label>
                        <select name="status" class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200" required>
                            <option value="1" @selected((string) old('status', (int) $board->status) === '1')>Si</option>
                            <option value="0" @selected((string) old('status', (int) $board->status) === '0')>No</option>
                        </select>
                        @error('status') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">Grupo destino *</label>
                        <select name="monday_group_id" class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200">
                            <option value="">Seleccione...</option>
                            @foreach($board->groups as $group)
                                <option value="{{ $group->monday_group_id }}" @selected(old('monday_group_id', $board->monday_group_id) === $group->monday_group_id)>
                                    {{ $group->title }} ({{ $group->monday_group_id }})
                                </option>
                            @endforeach
                        </select>
                        @error('monday_group_id') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">Campo Lead que activa el board *</label>
                        <select name="condition_lead_field" class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200">
                            <option value="">Seleccione...</option>
                            @foreach($leadFields as $field)
                                <option value="{{ $field }}" @selected(old('condition_lead_field', $board->condition_lead_field) === $field)>{{ $field }}</option>
                            @endforeach
                        </select>
                        @error('condition_lead_field') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="block text-sm mb-1 text-gray-800 dark:text-gray-200">Valor esperado *</label>
                        <input name="condition_expected_value"
                               value="{{ old('condition_expected_value', $board->condition_expected_value) }}"
                               class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200"
                               placeholder="Ej: Test Drive">
                        @error('condition_expected_value') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400 mb-2">Mapeo Lead -> Monday columns</div>

                    @error('mappings') <div class="mb-2 text-sm text-red-600">{{ $message }}</div> @enderror

                    <div class="overflow-x-auto border rounded dark:border-gray-700">
                        <table class="w-full text-left">
                            <thead class="bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200">
                                <tr>
                                    <th class="p-3">Columna Monday</th>
                                    <th class="p-3">Tipo</th>
                                    <th class="p-3">Origen</th>
                                    <th class="p-3">Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($board->columns as $index => $column)
                                    @php($sourceType = old("mappings.{$index}.source_type", $column->mapping->source_type ?? ($column->mapping->lead_field_name ? 'lead_field' : 'lead_field')))
                                    <tr class="border-t border-gray-200 dark:border-gray-800 text-gray-800 dark:text-gray-200" data-mapping-row>
                                        <td class="p-3">
                                            <div class="font-semibold">{{ $column->title }}</div>
                                            <div class="text-xs font-mono text-gray-500 dark:text-gray-400">{{ $column->monday_column_id }}</div>
                                        </td>
                                        <td class="p-3">{{ $column->type ?: 'sin tipo' }}</td>
                                        <td class="p-3">
                                            <input type="hidden" name="mappings[{{ $index }}][column_id]" value="{{ $column->id }}">
                                            <select name="mappings[{{ $index }}][source_type]"
                                                    class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200"
                                                    data-mapping-source>
                                                <option value="lead_field" @selected($sourceType === 'lead_field')>Campo Lead</option>
                                                <option value="fixed_value" @selected($sourceType === 'fixed_value')>Valor fijo</option>
                                            </select>
                                            @error("mappings.$index.source_type") <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                                        </td>
                                        <td class="p-3 space-y-2">
                                            <div data-source-panel="lead_field" class="{{ $sourceType === 'fixed_value' ? 'hidden' : '' }}">
                                                <select name="mappings[{{ $index }}][lead_field_name]"
                                                        class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200">
                                                    <option value="">-- Sin mapeo --</option>
                                                    @foreach($leadFields as $field)
                                                        <option value="{{ $field }}" @selected(old("mappings.{$index}.lead_field_name", $column->mapping->lead_field_name ?? '') === $field)>
                                                            {{ $field }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error("mappings.$index.lead_field_name") <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                                            </div>

                                            <div data-source-panel="fixed_value" class="{{ $sourceType === 'fixed_value' ? '' : 'hidden' }}">
                                                <input name="mappings[{{ $index }}][static_value]"
                                                       value="{{ old("mappings.{$index}.static_value", $column->mapping->static_value ?? '') }}"
                                                       class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200"
                                                       placeholder="Valor fijo a enviar a Monday">
                                                @error("mappings.$index.static_value") <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="p-3" colspan="4">Aun no hay columnas sincronizadas para este board.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button class="px-4 py-2 rounded bg-green-600 text-white" type="submit">
                        Guardar configuracion
                    </button>
                </div>
            </form>
        </div>
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
</x-app-layout>

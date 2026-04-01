<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                {{ $integration->name }}
            </h2>

            <div class="flex gap-2">
                <a href="{{ route('integrations.edit', $integration) }}"
                   class="px-4 py-2 rounded bg-yellow-900 text-white">
                    Editar
                </a>

                <a href="{{ route('integrations.index') }}"
                   class="px-4 py-2 rounded bg-gray-200 dark:bg-gray-200">
                    Volver
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
            <div class="grid gap-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Cliente</div>
                        <div class="mt-1">{{ $integration->customer->name ?? '—' }}</div>
                    </div>

                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Tipo</div>
                        <div class="mt-1">{{ $integration->integrationtype->name ?? '—' }}</div>
                    </div>
                </div>

                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">URL</div>
                    <div class="mt-1 break-all">{{ $integration->url }}</div>
                </div>

                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Public Key</div>
                    <div class="mt-1 font-mono text-xs break-all">{{ $integration->public_key }}</div>
                </div>

                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Status</div>
                    <div class="mt-1">
                        <span class="px-2 py-1 rounded text-sm {{ (int) $integration->status === 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-900' }}">
                            {{ (int) $integration->status === 1 ? 'Activo' : 'Inactivo' }}
                        </span>
                    </div>
                </div>

                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Descripcion</div>
                    <div class="mt-1">{{ $integration->description ?: '—' }}</div>
                </div>

                <div class="pt-2 flex gap-2">
                    <form method="POST"
                          action="{{ route('integrations.destroy', $integration) }}"
                          onsubmit="return confirm('¿Seguro que deseas eliminar esta integración?');">
                        @csrf
                        @method('DELETE')
                        <button class="px-4 py-2 rounded bg-red-900 text-white" type="submit">
                            Eliminar
                        </button>
                    </form>
                </div>
            </div>
        </div>

        @if (strtolower((string) optional($integration->integrationtype)->name) === 'monday')
            <div class="bg-white dark:bg-gray-900 rounded shadow p-6 text-gray-800 dark:text-gray-200">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div>
                        <h3 class="font-semibold text-lg">Boards Monday</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Sincroniza boards, activa las que deban recibir leads y configura su condición, grupo y mapeos.</p>
                    </div>

                    <form method="POST" action="{{ route('integrations.monday.sync-boards', $integration) }}">
                        @csrf
                        <button class="px-4 py-2 rounded bg-blue-600 text-white" type="submit">
                            Sincronizar boards
                        </button>
                    </form>
                </div>

                <div class="overflow-x-auto border rounded dark:border-gray-700">
                    <table class="w-full text-left">
                        <thead class="bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200">
                            <tr>
                                <th class="p-3">Board</th>
                                <th class="p-3">Activa</th>
                                <th class="p-3">Condicion</th>
                                <th class="p-3">Grupo</th>
                                <th class="p-3">Sync</th>
                                <th class="p-3 w-56">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($integration->mondayBoards ?? [] as $board)
                                <tr class="border-t border-gray-200 dark:border-gray-800 text-gray-800 dark:text-gray-200">
                                    <td class="p-3">
                                        <div class="font-semibold">{{ $board->name }}</div>
                                        <div class="text-xs font-mono text-gray-500 dark:text-gray-400">{{ $board->monday_board_id }}</div>
                                    </td>
                                    <td class="p-3">
                                        <span class="px-2 py-1 rounded text-sm {{ $board->status ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-900' }}">
                                            {{ $board->status ? 'Si' : 'No' }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-sm">
                                        @if($board->condition_lead_field && $board->condition_expected_value)
                                            {{ $board->condition_lead_field }} = {{ $board->condition_expected_value }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="p-3 text-sm">{{ $board->monday_group_id ?: '—' }}</td>
                                    <td class="p-3 text-sm">
                                        <div>Grupos: {{ $board->groups_count ?? 0 }}</div>
                                        <div>Columnas: {{ $board->columns_count ?? 0 }}</div>
                                        <div>Mapeos: {{ $board->column_mappings_count ?? 0 }}</div>
                                    </td>
                                    <td class="p-3 flex gap-2">
                                        <a class="px-3 py-1 rounded bg-yellow-900 text-white"
                                           href="{{ route('integrations.monday.boards.edit', [$integration, $board]) }}">
                                            Configurar
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="p-3" colspan="6">Aun no hay boards sincronizadas para esta integracion.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                Integrations
            </h2>

            <a href="{{ route('integrations.create') }}"
               class="px-4 py-2 rounded bg-green-600 text-white">
                Nuevo
            </a>
        </div>
    </x-slot>

    <div class="p-6 max-w-6xl mx-auto">
        @if (session('success'))
            <div class="mb-4 p-3 rounded bg-green-100 text-green-800">
                {{ session('success') }}
            </div>
        @endif

        <form method="GET" class="mb-4 flex gap-2">
            <input name="q" value="{{ $q ?? request('q') }}"
                   class="w-full rounded border p-2 dark:bg-gray-900 dark:text-gray-200"
                   placeholder="Buscar por nombre, url o public_key..." />
            <button class="px-4 py-2 rounded bg-gray-200 dark:bg-gray-200">
                Buscar
            </button>
        </form>

        <div class="bg-white dark:bg-gray-900 rounded shadow overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200">
                <tr>
                    <th class="p-3">Nombre</th>
                    <th class="p-3">Cliente</th>
                    <th class="p-3">Tipo</th>
                    <th class="p-3">Status</th>
                    <th class="p-3">Public Key</th>
                    <th class="p-3 w-72">Acciones</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($integrations as $integration)
                    <tr class="border-t border-gray-200 dark:border-gray-800 text-gray-800 dark:text-gray-200">
                        <td class="p-3">{{ $integration->name }}</td>
                        <td class="p-3">{{ $integration->customer->name ?? '—' }}</td>
                        <td class="p-3">{{ $integration->integrationtype->name ?? '—' }}</td>
                        <td class="p-3">
                            <span class="px-2 py-1 rounded text-sm
                                {{ (int) $integration->status === 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-900' }}">
                                {{ (int) $integration->status === 1 ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="p-3 font-mono text-xs">
                            {{ $integration->public_key ?? '—' }}
                        </td>

                        <td class="p-3 flex gap-2">
                            <a class="px-3 py-1 rounded bg-gray-200 dark:bg-gray-800"
                               href="{{ route('integrations.show', $integration) }}">
                                Ver
                            </a>

                            <a class="px-3 py-1 rounded bg-yellow-900 text-white"
                               href="{{ route('integrations.edit', $integration) }}">
                                Editar
                            </a>

                            <form method="POST"
                                  action="{{ route('integrations.destroy', $integration) }}"
                                  onsubmit="return confirm('¿Seguro que deseas eliminar esta integración?');">
                                @csrf
                                @method('DELETE')
                                <button class="px-3 py-1 rounded bg-red-900 text-white" type="submit">
                                    Eliminar
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td class="p-3" colspan="6">No hay integraciones.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $integrations->links() }}
        </div>
    </div>
</x-app-layout>

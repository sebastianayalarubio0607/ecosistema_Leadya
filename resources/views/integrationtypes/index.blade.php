<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                Integration Types
            </h2>

            <a href="{{ route('integrationtypes.create') }}"
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
                   placeholder="Buscar por nombre o descripción..." />
            <button class="px-4 py-2 rounded bg-gray-200 dark:bg-gray-200">
                Buscar
            </button>
        </form>

        <div class="bg-white dark:bg-gray-900 rounded shadow overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200">
                <tr>
                    <th class="p-3">Nombre</th>
                    <th class="p-3">Status</th>
                    <th class="p-3">Descripción</th>
                    <th class="p-3 w-72">Acciones</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($types as $type)
                    <tr class="border-t border-gray-200 dark:border-gray-800 text-gray-800 dark:text-gray-200">
                        <td class="p-3">{{ $type->name }}</td>
                        <td class="p-3">
                            <span class="px-2 py-1 rounded text-sm
                                {{ (int) $type->status === 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-900' }}">
                                {{ (int) $type->status === 1 ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="p-3">
                            <span class="text-sm text-gray-700 dark:text-gray-200">
                                {{ $type->description ?: '—' }}
                            </span>
                        </td>

                        <td class="p-3 flex gap-2">
                            <a class="px-3 py-1 rounded bg-gray-200 dark:bg-gray-800"
                               href="{{ route('integrationtypes.show', $type) }}">
                                Ver
                            </a>

                            <a class="px-3 py-1 rounded bg-yellow-900 text-white"
                               href="{{ route('integrationtypes.edit', $type) }}">
                                Editar
                            </a>

                            <form method="POST"
                                  action="{{ route('integrationtypes.destroy', $type) }}"
                                  onsubmit="return confirm('¿Seguro que deseas eliminar este Integration Type?');">
                                @csrf
                                @method('DELETE')
                                <button class="px-3 py-1 rounded bg-red-900 text-white" type="submit">
                                    Eliminar
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td class="p-3" colspan="4">No hay Integration Types.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $types->links() }}
        </div>
    </div>
</x-app-layout>

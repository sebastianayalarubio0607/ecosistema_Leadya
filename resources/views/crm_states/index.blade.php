<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                CRM States
            </h2>

            <a href="{{ route('crmstates.create') }}"
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
                   placeholder="Buscar por ID (ej: 1-123) o nombre..." />
            <button class="px-4 py-2 rounded bg-gray-200 dark:bg-gray-200">
                Buscar
            </button>
        </form>

        <div class="bg-white dark:bg-gray-900 rounded shadow overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200">
                <tr>
                    <th class="p-3">ID</th>
                    <th class="p-3">Nombre</th>
                    <th class="p-3">Qualification</th>
                    <th class="p-3 w-72">Acciones</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($crmstates as $crmstate)
                    <tr class="border-t border-gray-200 dark:border-gray-800 text-gray-800 dark:text-gray-200">
                        <td class="p-3 font-mono text-xs">{{ $crmstate->id }}</td>
                        <td class="p-3">{{ $crmstate->name }}</td>
                        <td class="p-3">
                            {{ $crmstate->qualificationModel->name ?? '—' }}
                        </td>
                        <td class="p-3 flex gap-2">
                            <a class="px-3 py-1 rounded bg-gray-200 dark:bg-gray-800"
                               href="{{ route('crmstates.show', $crmstate) }}">
                                Ver
                            </a>

                            <a class="px-3 py-1 rounded bg-yellow-900 text-white"
                               href="{{ route('crmstates.edit', $crmstate) }}">
                                Editar
                            </a>

                            <form method="POST"
                                  action="{{ route('crmstates.destroy', $crmstate) }}"
                                  onsubmit="return confirm('¿Seguro que deseas eliminar este CRM State?');">
                                @csrf
                                @method('DELETE')
                                <button class="px-3 py-1 rounded bg-red-900 text-white" type="submit">
                                    Eliminar
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td class="p-3" colspan="4">No hay CRM States.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $crmstates->links() }}
        </div>
    </div>
</x-app-layout>

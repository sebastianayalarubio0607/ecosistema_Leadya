<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                Customers
            </h2>

            <a href="{{ route('customers.create') }}"
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
            <input name="q" value="{{ $q }}"
                   class="w-full rounded border p-2 dark:bg-gray-900  dark:text-gray-200"
                   placeholder="Buscar por nombre o pixel id..." />
            <button class="px-4 py-2 rounded bg-gray-200 dark:bg-gray-200">
                Buscar
            </button>
        </form>

        <div class="bg-white dark:bg-gray-900 rounded shadow overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-gray-100 dark:bg-gray-800  text-gray-800 dark:text-gray-200">
                    <tr>
                        <th class="p-3">Nombre</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">FB Pixel ID</th>
                        <th class="p-3 w-72">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        <tr class="border-t border-gray-200 dark:border-gray-800  text-gray-800 dark:text-gray-200">
                            <td class="p-3">{{ $customer->name }}</td>
                            <td class="p-3">
<span class="px-2 py-1 rounded text-sm
    {{ (int) $customer->status === 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-900' }}">
    {{ (int) $customer->status === 1 ? 'Activo' : 'Inactivo' }}
</span>

                            </td>
                            <td class="p-3">{{ $customer->fb_pixel_id }}</td>
                            <td class="p-3 flex gap-2">
                                <a class="px-3 py-1 rounded bg-gray-200 dark:bg-gray-800"
                                   href="{{ route('customers.show', $customer) }}">
                                    Ver
                                </a>

                                <a class="px-3 py-1 rounded bg-yellow-900 text-white"
                                   href="{{ route('customers.edit', $customer) }}">
                                    Editar
                                </a>

                                <form method="POST"
                                      action="{{ route('customers.destroy', $customer) }}"
                                      onsubmit="return confirm('¿Seguro que deseas eliminar este customer?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="px-3 py-1 rounded bg-red-900 text-white" type="submit">
                                        Eliminar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="p-3" colspan="4">No hay customers.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $customers->links() }}
        </div>
    </div>
</x-app-layout>

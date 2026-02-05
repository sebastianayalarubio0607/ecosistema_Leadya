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

    <div class="p-6 max-w-6xl mx-auto">
        @if (session('success'))
            <div class="mb-4 p-3 rounded bg-green-100 text-green-800">
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
                        <span class="px-2 py-1 rounded text-sm
                            {{ (int) $integration->status === 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-900' }}">
                            {{ (int) $integration->status === 1 ? 'Activo' : 'Inactivo' }}
                        </span>
                    </div>
                </div>

                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Descripción</div>
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
    </div>
</x-app-layout>

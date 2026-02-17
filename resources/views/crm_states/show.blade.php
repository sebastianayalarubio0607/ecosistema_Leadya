<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                {{ $crmstate->name }}
            </h2>

            <div class="flex gap-2">
                <a href="{{ route('crmstates.edit', $crmstate) }}"
                   class="px-4 py-2 rounded bg-yellow-900 text-white">
                    Editar
                </a>

                <a href="{{ route('crmstates.index') }}"
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
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">ID</div>
                    <div class="mt-1 font-mono text-xs break-all">{{ $crmstate->id }}</div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Integration ID</div>
                        <div class="mt-1 font-mono">{{ $integrationId }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $integration? ($integration->customer->name ?? '—') . ' - ' . $integration->name : '—' }}
                        </div>
                    </div>

                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">CRM status_id</div>
                        <div class="mt-1 font-mono">{{ $externalId }}</div>
                    </div>
                </div>

                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Qualification</div>
                    <div class="mt-1">{{ $crmstate->qualificationModel->name ?? '—' }}</div>
                </div>

                <div class="pt-2 flex gap-2">
                    <form method="POST"
                          action="{{ route('crmstates.destroy', $crmstate) }}"
                          onsubmit="return confirm('¿Seguro que deseas eliminar este CRM State?');">
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

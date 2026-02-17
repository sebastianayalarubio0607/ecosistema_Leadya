<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                {{ $qualification->name }}
            </h2>

            <div class="flex gap-2">
                <a href="{{ route('qualifications.edit', $qualification) }}"
                   class="px-4 py-2 rounded bg-yellow-900 text-white">
                    Editar
                </a>

                <a href="{{ route('qualifications.index') }}"
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
                    <div class="mt-1">{{ $qualification->id }}</div>
                </div>

                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Nombre</div>
                    <div class="mt-1">{{ $qualification->name }}</div>
                </div>

                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Funnel</div>
                    <div class="mt-1">
                        @if($qualification->funnel)
                            <a class="underline" href="{{ route('funnels.show', $qualification->funnel) }}">
                                {{ $qualification->funnel->name }} (ID: {{ $qualification->funnel->id }})
                            </a>
                        @else
                            -
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Creado</div>
                        <div class="mt-1">{{ optional($qualification->created_at)->format('Y-m-d H:i') }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Actualizado</div>
                        <div class="mt-1">{{ optional($qualification->updated_at)->format('Y-m-d H:i') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                {{ $funnel->name }}
            </h2>

            <div class="flex gap-2">
                <a href="{{ route('funnels.edit', $funnel) }}"
                   class="px-4 py-2 rounded bg-yellow-900 text-white">
                    Editar
                </a>

                <a href="{{ route('funnels.index') }}"
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
                    <div class="mt-1">{{ $funnel->id }}</div>
                </div>

                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Nombre</div>
                    <div class="mt-1">{{ $funnel->name }}</div>
                </div>

                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Descripción</div>
                    <div class="mt-1">{{ $funnel->description ?: '-' }}</div>
                </div>

                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Estado</div>
                    <div class="mt-1">{{ $funnel->status }}</div>
                </div>

                {{-- ✅ MetaEvent --}}
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Meta Event</div>
                    <div class="mt-1">
                        @if($funnel->meta_event_id)
                            @php
                                $metaLabel = optional($funnel->metaEvent)->name
                                    ?? optional($funnel->metaEvent)->event_name
                                    ?? optional($funnel->metaEvent)->title
                                    ?? null;
                            @endphp

                            <span class="px-2 py-1 rounded bg-gray-200 dark:bg-gray-800">
                                {{ $metaLabel ?: ('ID: ' . $funnel->meta_event_id) }}
                            </span>
                        @else
                            <span class="text-gray-500 dark:text-gray-400">—</span>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Creado</div>
                        <div class="mt-1">{{ optional($funnel->created_at)->format('Y-m-d H:i') }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Actualizado</div>
                        <div class="mt-1">{{ optional($funnel->updated_at)->format('Y-m-d H:i') }}</div>
                    </div>
                </div>

                <div class="pt-2">
                    <div class="text-sm text-gray-500 dark:text-gray-400 mb-2">Qualifications asociadas</div>

                    @if($funnel->qualifications->isEmpty())
                        <div class="text-sm text-gray-600 dark:text-gray-300">Sin qualifications.</div>
                    @else
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach($funnel->qualifications as $qualification)
                                <li class="text-sm">
                                    <a class="underline" href="{{ route('qualifications.show', $qualification) }}">
                                        {{ $qualification->name }} (ID: {{ $qualification->id }})
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="pt-3 flex gap-2">
                    <form method="POST"
                          action="{{ route('funnels.destroy', $funnel) }}"
                          onsubmit="return confirm('¿Seguro que deseas eliminar este funnel?');">
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
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                Editar Integration Type
            </h2>

            <a href="{{ route('integrationtypes.index') }}"
               class="px-4 py-2 rounded bg-gray-200 dark:bg-gray-200">
                Volver
            </a>
        </div>
    </x-slot>

    <div class="p-6 max-w-6xl mx-auto">
        @if ($errors->any())
            <div class="mb-4 p-3 rounded bg-red-100 text-red-900">
                Revisa los campos marcados.
            </div>
        @endif

        <div class="bg-white dark:bg-gray-900 rounded shadow p-6">
            <form method="POST" action="{{ route('integrationtypes.update', $type) }}">
                @csrf
                @method('PUT')
                @include('integrationtypes._form', ['type' => $type])
            </form>
        </div>
    </div>
</x-app-layout>

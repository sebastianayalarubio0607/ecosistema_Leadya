<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                Editar Qualification
            </h2>

            <a href="{{ route('qualifications.index') }}"
               class="px-4 py-2 rounded bg-gray-200 dark:bg-gray-200">
                Volver
            </a>
        </div>
    </x-slot>

    <div class="p-6 max-w-6xl mx-auto">
        <div class="bg-white dark:bg-gray-900 rounded shadow p-6">
            <form method="POST" action="{{ route('qualifications.update', $qualification) }}">
                @csrf
                @method('PUT')
                @include('qualifications._form', ['qualification' => $qualification, 'funnels' => $funnels])
            </form>
        </div>
    </div>
</x-app-layout>

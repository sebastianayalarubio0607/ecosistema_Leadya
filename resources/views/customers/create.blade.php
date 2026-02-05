<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
            Crear Customer
        </h2>
    </x-slot>

    <div class="p-6 max-w-3xl mx-auto">
        <form method="POST" action="{{ route('customers.store') }}" class="space-y-4">
            @csrf

            @include('customers.partials.form', ['customer' => null])

            <div class="flex gap-2">
                <button class="px-4 py-2 rounded bg-black text-white">Guardar</button>
                <a href="{{ route('customers.index') }}" class="px-4 py-2 rounded bg-gray-200 dark:bg-gray-700">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</x-app-layout>

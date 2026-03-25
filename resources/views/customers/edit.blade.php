<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
            Editar Customer
        </h2>
    </x-slot>

    <div class="p-6 max-w-3xl mx-auto  dark:text-gray-200  ">
        <form method="POST" action="{{ route('customers.update', $customer) }}" class="space-y-4">
            @csrf
            @method('PUT')

            @include('customers.partials.form', ['customer' => $customer, 'metaPages' => $metaPages, 'selectedMetaPageIds' => $selectedMetaPageIds])

            <div class="bg-white dark:bg-gray-900 rounded shadow p-4  ">
                <p class="font-semibold mb-2 ">Token actual</p>
                <div class="text-sm break-all p-2 rounded bg-gray-100 dark:bg-gray-800">
                    {{ $customer->token }}
                </div>

                <label class="inline-flex items-center gap-2 mt-3">
                    <input type="checkbox" name="regenerate_token" value="1">
                    <span>Regenerar token</span>
                </label>
            </div>

            <div class="flex gap-2">
                <button class="px-4 py-2 rounded bg-blue-600 text-white">Actualizar</button>
                <a href="{{ route('customers.index') }}" class="px-4 py-2 rounded bg-gray-200 dark:bg-gray-700">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</x-app-layout>

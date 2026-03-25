<x-app-layout>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
            Detalle Customer
        </h2>
    </x-slot>

    {{-- MODAL: solo aparece si existe created_token --}}
    @if (session('created_token'))
        <div id="tokenModalBackdrop" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-xl w-full max-w-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Token generado ✅
                </h3>

                <p class="text-sm text-gray-700 dark:text-gray-300 mt-2">
                    Este es el token REAL (solo se muestra una vez). Cópialo ahora.
                </p>

                <div class="mt-4">
                    <label class="text-sm text-gray-700 dark:text-gray-300">Token</label>
                    <div class="flex gap-2 mt-1">
                        <input
                            id="createdTokenInput"
                            readonly
                            value="{{ session('created_token') }}"
                            class="w-full rounded border p-2 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white border-gray-300 dark:border-gray-700"
                        />
                        <button
                            type="button"
                            onclick="copyCreatedToken()"
                            class="px-4 py-2 rounded bg-green-600 text-white hover:bg-green-700"
                        >
                            Copiar
                        </button>
                    </div>
                    <p id="copyMsg" class="text-xs text-green-600 mt-2 hidden">Copiado ✅</p>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        onclick="closeTokenModal()"
                        class="px-4 py-2 rounded bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white"
                    >
                        Cerrar
                    </button>
                </div>
            </div>
        </div>

        <script>
            function copyCreatedToken() {
                const input = document.getElementById('createdTokenInput');
                input.select();
                input.setSelectionRange(0, 99999);
                navigator.clipboard.writeText(input.value);

                const msg = document.getElementById('copyMsg');
                msg.classList.remove('hidden');
                setTimeout(() => msg.classList.add('hidden'), 1200);
            }

            function closeTokenModal() {
                const backdrop = document.getElementById('tokenModalBackdrop');
                if (backdrop) backdrop.remove();
            }
        </script>
    @endif

    <div class="p-6 max-w-3xl mx-auto space-y-4">

        @if (session('success'))
            <div class="mb-4 p-3 rounded bg-green-100 text-green-800">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white dark:bg-gray-900 rounded shadow p-4 space-y-3 text-gray-900 dark:text-white">
            <div><strong>Nombre:</strong> {{ $customer->name }}</div>
            <div><strong>Status:</strong> {{ (int)$customer->status === 1 ? 'Activo' : 'Inactivo' }}</div>
            <div><strong>Descripción:</strong> {{ $customer->description }}</div>
            <div><strong>FB Pixel ID:</strong> {{ $customer->fb_pixel_id }}</div>

            <div>
                <strong>FB Access Token:</strong>
                <div class="text-sm break-all p-2 rounded bg-gray-100 dark:bg-gray-800">
                    {{ $customer->fb_access_token }}
                </div>
            </div>

            <div>
                <strong>Token guardado en BD (hash):</strong>
                <div class="text-sm break-all p-2 rounded bg-gray-100 dark:bg-gray-800">
                    {{ $customer->token }}
                </div>
            </div>

            <div>
                <strong>Meta Pages asociadas:</strong>
                <div class="mt-2 space-y-2">
                    @forelse($customer->metaPages as $metaPage)
                        <div class="text-sm break-all p-2 rounded bg-gray-100 dark:bg-gray-800">
                            {{ $metaPage->name }} ({{ $metaPage->meta_page_id }})
                        </div>
                    @empty
                        <div class="text-sm break-all p-2 rounded bg-gray-100 dark:bg-gray-800">
                            Sin páginas asignadas.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="flex gap-2">
            <a href="{{ route('customers.edit', $customer) }}" class="px-4 py-2 rounded bg-blue-600 text-white">
                Editar
            </a>
            <a href="{{ route('customers.index') }}" class="px-4 py-2 rounded bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white">
                Volver
            </a>
        </div>

    </div>

</x-app-layout>

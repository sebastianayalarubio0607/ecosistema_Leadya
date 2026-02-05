<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-indigo-200">@yield('title')</h2>
                @hasSection('subtitle')
                    <div class="text-sm text-white/60">@yield('subtitle')</div>
                @endif
            </div>

            <div class="flex items-center gap-2">
                @yield('header_actions')
            </div>
        </div>
    </x-slot>

    <div class="p-6 max-w-7xl mx-auto space-y-4">
        @if(session('success'))
            <div class="rounded-2xl border border-white/10 bg-emerald-500/10 text-emerald-100 px-4 py-3">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-2xl border border-white/10 bg-rose-500/10 text-rose-100 px-4 py-3">
                <div class="font-semibold mb-2">Errores:</div>
                <ul class="list-disc ml-5 space-y-1">
                    @foreach($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </div>
</x-app-layout>

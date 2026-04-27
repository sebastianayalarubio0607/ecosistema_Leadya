<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    
    <style>
        /* Fondo Degradado según Manual de Marca y Referencia */
        .bg-leadsya-gradient {
            background: linear-gradient(135deg, #65327c 0%, #7023c8 40%, #00A99D 100%);
        }

    </style>

    <body class="font-sans antialiased">
        <div class="min-h-screen flex bg-gradient-to-br bg-leadsya-gradient">
            {{-- Sidebar --}}
            <livewire:layout.navigation />

            {{-- Content --}}
            <div class="flex-1 min-w-0 flex flex-col">
                {{-- Page Heading --}}
                @if (isset($header))
                    <header class="sticky top-0 z-10 bg-white/ backdrop-blur border-b border-white/10">
                        <div class="px-6 py-4">
                            {{ $header }}
                        </div>
                    </header>
                @endif

                {{-- Page Content --}}
                <main class="flex-1">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>

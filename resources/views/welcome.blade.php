<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>LeadsYa - Bienvenido</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* Fondo Degradado según Manual de Marca y Referencia */
        .bg-leadsya-gradient {
            background: linear-gradient(135deg, #a06bd9 0%, #6b23c8 20%, #00A99D 100%);
        }

        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>

<body class="antialiased selection:bg-[#00A99D] selection:text-white">
    <div class="relative sm:flex sm:justify-center sm:items-center min-h-screen bg-leadsya-gradient">
        @if (Route::has('login'))
            <div class="sm:fixed sm:top-0 sm:right-0 p-6 text-right z-10">
                @auth
                    <a href="{{ url('/dashboard') }}"
                        class="font-semibold text-white hover:text-[#00A99D] focus:outline focus:outline-2 focus:rounded-sm focus:outline-[#00A99D]">Dashboard</a>
                @else
                    <a href="{{ route('login') }}"
                        class="font-semibold text-white hover:text-[#00A99D] focus:outline focus:outline-2 focus:rounded-sm focus:outline-[#00A99D]">Log
                        in</a>
                 

                    @if (Route::has('register'))
                        <a href="{{ route('register') }}"
                            class="ml-4 font-semibold text-white hover:text-[#00A99D] focus:outline focus:outline-2 focus:rounded-sm focus:outline-[#00A99D]">Register</a>
                    @endif
                @endauth
                   <a href="{{ route('generate-url') }}"
                        class="ml-4 font-semibold text-white hover:text-[#00A99D] focus:outline focus:outline-2 focus:rounded-sm focus:outline-[#00A99D]">Generar
                        URL</a>
            </div>
        @endif

        <div class="max-w-7xl mx-auto p-6 lg:p-8 text-center">
            <div class="flex justify-center mb-8">
                <x-application-logo class="w-96 h-auto fill-current text-white" />
            </div>

            <div class="glass-effect p-12 rounded-3xl shadow-2xl">
                <h1 class="text-5xl font-extrabold text-white mb-4">Optimiza la Calidad de tus Conversiones</h1>
                <p class="text-white/80 text-xl max-w-2xl mx-auto">
                    Leads Quality es la herramienta definitiva para filtrar, analizar y potenciar tus oportunidades de negocio. Eleva el estándar de tu pipeline comercial con inteligencia de datos.
                </p>

                <div class="mt-10 flex justify-center gap-6">
                    <a href="{{ route('login') }}"
                        class="px-8 py-3 bg-[#00A99D] text-white font-bold rounded-full hover:bg-[#48c3a1] transition-all transform hover:scale-105 shadow-lg">
                        Empezar ahora
                    </a>
                    <a href="#"
                        class="px-8 py-3 border-2 border-white text-white font-bold rounded-full hover:bg-white hover:text-[#a06bd9] transition-all transform hover:scale-105">
                        Saber más
                    </a>
                </div>
            </div>

            <div class="flex justify-center mt-16 px-0 sm:items-center sm:justify-between">
                <div class="text-center text-sm text-white/60 sm:text-left">
                    <div class="flex items-center gap-4">
                        <a href="https://leadsya.com"
                            class="group inline-flex items-center hover:text-white focus:outline focus:outline-2 focus:rounded-sm focus:outline-[#00A99D]">
                            LeadsYa &copy; 2026
                        </a>
                    </div>
                </div>

                <div class="ml-4 text-center text-sm text-white/60 sm:text-right sm:ml-0">
                   Leads Quality v 1.2.9
                </div>
            </div>
        </div>
    </div>
</body>

</html>

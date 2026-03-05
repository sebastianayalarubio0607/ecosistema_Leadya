<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Leads Quality | Leads Ya</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="antialiased font-sans bg-slate-50 text-slate-900">
    <div class="relative min-h-screen flex flex-col">
        
        <header class="fixed w-full z-50 bg-white/80 backdrop-blur-md shadow-sm">
            <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="bg-violet-600 p-2 rounded-lg">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <span class="text-xl font-bold tracking-tight text-slate-800">
                        LEADS<span class="text-violet-600">QUALITY</span>
                    </span>
                </div>

                @if (Route::has('login'))
                    <livewire:welcome.navigation />
                @endif
            </div>
        </header>

        <main class="flex-grow flex items-center justify-center pt-20">
            <section class="max-w-7xl mx-auto px-6 grid lg:grid-cols-2 gap-12 items-center">
                
                <div class="space-y-8 text-center lg:text-left">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-violet-100 text-violet-700 text-sm font-medium">
                        <span class="relative flex h-2 w-2">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-violet-400 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-2 w-2 bg-violet-500"></span>
                        </span>
                        Una solución de Leads Ya
                    </div>
                    
                    <h1 class="text-5xl lg:text-6xl font-extrabold text-slate-900 leading-tight">
                        Optimiza la <span class="text-violet-600">Calidad</span> de tus Conversiones
                    </h1>
                    
                    <p class="text-lg text-slate-600 max-w-xl mx-auto lg:mx-0">
                        Leads Quality es la herramienta definitiva para filtrar, analizar y potenciar tus oportunidades de negocio. Eleva el estándar de tu pipeline comercial con inteligencia de datos.
                    </p>

                    <div class="flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-4">
                        <a href="{{ route('login') }}" class="w-full sm:w-auto px-8 py-4 bg-violet-600 hover:bg-violet-700 text-white font-semibold rounded-xl transition-all shadow-lg shadow-violet-200 flex items-center justify-center gap-2">
                            Comenzar ahora
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                        </a>
                        <a href="https://leadsya.com/" target="_blank" class="w-full sm:w-auto px-8 py-4 bg-white border border-slate-200 text-slate-700 font-semibold rounded-xl hover:bg-slate-50 transition-all flex items-center justify-center">
                            Saber más
                        </a>
                    </div>
                </div>

                <div class="relative hidden lg:block">
                    <div class="absolute inset-0 bg-violet-500/10 rounded-3xl rotate-3"></div>
                    <div class="relative bg-white p-8 rounded-3xl shadow-2xl border border-slate-100">
                        <div class="space-y-6">
                            <div class="flex items-center justify-between">
                                <div class="h-4 w-32 bg-slate-100 rounded"></div>
                                <div class="h-8 w-8 bg-violet-100 rounded-full"></div>
                            </div>
                            <div class="space-y-3">
                                <div class="h-3 w-full bg-slate-50 rounded"></div>
                                <div class="h-3 w-5/6 bg-slate-50 rounded"></div>
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <div class="h-20 bg-violet-50 rounded-xl border border-violet-100 flex flex-col items-center justify-center">
                                    <span class="text-violet-600 font-bold text-xl">98%</span>
                                    <span class="text-[10px] text-violet-400 font-medium">Calidad</span>
                                </div>
                                <div class="h-20 bg-slate-50 rounded-xl flex flex-col items-center justify-center">
                                    <span class="text-slate-700 font-bold text-xl">+2.4k</span>
                                    <span class="text-[10px] text-slate-400 font-medium">Leads</span>
                                </div>
                                <div class="h-20 bg-slate-50 rounded-xl flex flex-col items-center justify-center">
                                    <span class="text-slate-700 font-bold text-xl">12h</span>
                                    <span class="text-[10px] text-slate-400 font-medium">Tiempo</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="py-8 text-center text-sm text-slate-400">
            &copy; {{ date('Y') }} Leads Quality by <a href="https://leadsya.com/" class="hover:text-violet-600 underline">Leads Ya</a>. Todos los derechos reservados. v1.03.02
        </footer>
    </div>
</body>
</html>
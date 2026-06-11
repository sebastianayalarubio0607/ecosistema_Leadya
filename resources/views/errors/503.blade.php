<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Mantenimiento temporal programado | LeadsYa</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        .bg-leadsya-gradient {
            background:
                radial-gradient(circle at top left, rgba(255, 255, 255, 0.2), transparent 28rem),
                linear-gradient(135deg, #a06bd9 0%, #6b23c8 22%, #00A99D 100%);
        }

        .glass-effect {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(18px);
            border: 1px solid rgba(255, 255, 255, 0.24);
        }

        .maintenance-ring {
            animation: ring-spin 16s linear infinite;
            transform-origin: center;
        }

        .maintenance-core {
            animation: calm-pulse 2.6s ease-in-out infinite;
            transform-origin: center;
        }

        .status-dot {
            animation: dot-pulse 1.8s ease-in-out infinite;
        }

        @keyframes ring-spin {
            to {
                transform: rotate(360deg);
            }
        }

        @keyframes calm-pulse {
            0%,
            100% {
                transform: scale(0.96);
                opacity: 0.72;
            }

            50% {
                transform: scale(1.04);
                opacity: 1;
            }
        }

        @keyframes dot-pulse {
            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(0, 169, 157, 0.42);
            }

            50% {
                box-shadow: 0 0 0 10px rgba(0, 169, 157, 0);
            }
        }
    </style>
</head>

<body class="antialiased selection:bg-[#00A99D] selection:text-white">
    <main class="bg-leadsya-gradient min-h-screen overflow-x-hidden px-5 py-6 text-white sm:px-8 lg:grid lg:place-items-center lg:overflow-hidden">
        <section class="mx-auto flex min-h-[calc(100vh-3rem)] w-full max-w-5xl flex-col items-center justify-between gap-8 text-center lg:min-h-0 lg:justify-center">
            <header class="flex w-full justify-center pt-2 lg:pt-0">
                <img
                    src="/images/leadsya-logo-placeholder.svg"
                    alt="LeadsYa"
                    class="h-12 w-auto max-w-[220px] object-contain sm:h-14"
                >
            </header>

            <div class="glass-effect w-full max-w-3xl rounded-[2rem] px-6 py-8 shadow-2xl shadow-[#2f1260]/20 sm:px-10 sm:py-10 lg:px-14">
                <div class="mx-auto mb-6 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/12 px-4 py-2 text-sm font-semibold text-white/90 shadow-lg shadow-[#2f1260]/10">
                    <span class="status-dot h-2.5 w-2.5 rounded-full bg-[#00A99D]"></span>
                    Mantenimiento temporal programado
                </div>

                <div class="mx-auto mb-7 flex h-28 w-28 items-center justify-center rounded-full border border-white/15 bg-white/10 shadow-xl shadow-[#2f1260]/20 sm:h-32 sm:w-32">
                    <svg class="h-20 w-20 text-white sm:h-24 sm:w-24" viewBox="0 0 120 120" fill="none" aria-hidden="true">
                        <circle cx="60" cy="60" r="40" stroke="currentColor" stroke-opacity="0.18" stroke-width="10" />
                        <g class="maintenance-ring">
                            <path d="M60 16v12M60 92v12M16 60h12M92 60h12M28.9 28.9l8.5 8.5M82.6 82.6l8.5 8.5M91.1 28.9l-8.5 8.5M37.4 82.6l-8.5 8.5" stroke="#FFFFFF" stroke-opacity="0.62" stroke-width="7" stroke-linecap="round" />
                        </g>
                        <circle class="maintenance-core" cx="60" cy="60" r="25" fill="#00A99D" fill-opacity="0.96" />
                        <path d="M47 61.5l8.3 8.3L74 51" stroke="#FFFFFF" stroke-width="7" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>

                <h1 class="mx-auto max-w-2xl text-3xl font-extrabold leading-tight tracking-normal text-white sm:text-5xl">
                    Estamos optimizando tu plataforma de leads
                </h1>

                <p class="mx-auto mt-5 max-w-2xl text-base font-medium leading-7 text-white/78 sm:text-lg">
                    Estamos realizando una mejora temporal para que tu experiencia, tus métricas y tus flujos de seguimiento funcionen cada vez mejor.
                </p>

                <div class="mx-auto mt-7 max-w-2xl rounded-2xl border border-[#00A99D]/35 bg-[#00A99D]/14 px-5 py-4 text-left shadow-lg shadow-[#2f1260]/10 sm:px-6">
                    <div class="flex gap-4">
                        <div class="mt-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#00A99D] text-white shadow-lg shadow-[#00A99D]/25">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M20 7L9 18l-5-5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <p class="text-sm font-semibold leading-6 text-white sm:text-base">
                            ¡No te preocupes! Tus campañas activas, landing pages siguen funcionando con total normalidad
                        </p>
                    </div>
                </div>

                <div class="mt-8 flex justify-center">
                    <a
                        href="https://leadsya.com"
                        class="group inline-flex min-h-12 items-center justify-center rounded-full bg-white px-7 py-3 text-sm font-bold text-[#6b23c8] shadow-xl shadow-[#2f1260]/20 transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#00A99D] hover:text-white focus:outline focus:outline-2 focus:outline-offset-4 focus:outline-white sm:px-8 sm:text-base"
                    >
                        Ir a LeadsYa.com
                        <svg class="ml-2 h-4 w-4 transition-transform duration-200 group-hover:translate-x-1" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M7.5 4.5L13 10l-5.5 5.5" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </a>
                </div>
            </div>

            <footer class="pb-2 text-sm font-medium text-white/65 lg:pb-0">
                LeadsYa &copy; {{ now()->year }}. Todos los derechos reservados.
            </footer>
        </section>
    </main>
</body>

</html>

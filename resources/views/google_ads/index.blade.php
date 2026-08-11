@extends('meta.layout')

@section('title', 'Google Ads')
@section('subtitle', 'Centro de navegacion para credenciales, metricas y conversiones de Google Ads')

@section('content')
    @php
        $sections = [
            [
                'title' => 'Credenciales',
                'description' => 'Administra la credencial global, tokens y configuracion MCC.',
                'route' => 'google-ads.credentials.index',
                'label' => 'Abrir credenciales',
                'icon' => 'credentials',
            ],
            [
                'title' => 'Campanas',
                'description' => 'Consulta metricas sincronizadas por campana y cliente.',
                'route' => 'google-ads.campaigns.index',
                'label' => 'Abrir campanas',
                'icon' => 'campaigns',
            ],
            [
                'title' => 'Grupos de anuncios',
                'description' => 'Revisa resultados por ad group y fecha de reporte.',
                'route' => 'google-ads.ad-groups.index',
                'label' => 'Abrir grupos',
                'icon' => 'ad_groups',
            ],
            [
                'title' => 'Anuncios',
                'description' => 'Analiza el rendimiento individual de los anuncios sincronizados.',
                'route' => 'google-ads.ads.index',
                'label' => 'Abrir anuncios',
                'icon' => 'ads',
            ],
            [
                'title' => 'Conversion actions',
                'description' => 'Consulta las acciones de conversion disponibles en Google Ads.',
                'route' => 'google-ads.conversion-actions.index',
                'label' => 'Abrir acciones',
                'icon' => 'conversion_actions',
            ],
            [
                'title' => 'Conversion jobs',
                'description' => 'Monitorea envios, fallos y reintentos de conversiones offline.',
                'route' => 'google-ads.conversion-jobs.index',
                'label' => 'Abrir jobs',
                'icon' => 'conversion_jobs',
            ],
        ];
    @endphp

    <section class="space-y-4">
        <div>
            <h2 class="text-2xl font-bold text-white">Menu De Google Ads</h2>
            <p class="mt-1 text-sm text-white/60">Selecciona la vista que quieres administrar.</p>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach($sections as $section)
                <a href="{{ route($section['route']) }}"
                   class="group rounded-2xl border border-white/10 bg-zinc-950/25 p-5 backdrop-blur transition hover:bg-white/5">
                    <div class="inline-flex h-12 items-center gap-2 rounded-xl bg-white/10 px-3 text-white/80">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                            <path fill="#4285F4" d="M10.6 4.2a2.4 2.4 0 0 1 4.1.9l5.2 12.1a2.4 2.4 0 0 1-4.4 1.9L10.3 7a2.4 2.4 0 0 1 .3-2.8z" />
                            <path fill="#34A853" d="M9.8 5.1a2.4 2.4 0 0 1 4.2 2.4L8.5 17.9a2.4 2.4 0 1 1-4.2-2.3L9.8 5.1z" />
                            <circle cx="6.4" cy="17.1" r="3" fill="#FBBC04" />
                        </svg>
                        <span class="text-white/40">-&gt;</span>
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/10">
                            @switch($section['icon'])
                                @case('credentials')
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a5 5 0 00-4.55 7.08L2.7 12.83A1 1 0 002.4 13.5V16a1 1 0 001 1H6a1 1 0 001-1v-1h1a1 1 0 001-1v-1h.5A5 5 0 1010 3zm2 5a1 1 0 102 0 1 1 0 00-2 0z" clip-rule="evenodd" /></svg>
                                    @break
                                @case('campaigns')
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a1 1 0 011-1h1.6a7 7 0 014.1 1.33l.6.44A5 5 0 0013.23 5H16a1 1 0 011 1v7a1 1 0 01-1 1h-2.77a7 7 0 01-4.1-1.33l-.6-.44A5 5 0 005.6 11H5v5a1 1 0 11-2 0V4z" /></svg>
                                    @break
                                @case('ad_groups')
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M7 8a3 3 0 116 0 3 3 0 01-6 0z" /><path fill-rule="evenodd" d="M2.5 15.5A5.5 5.5 0 0110 13.46a5.5 5.5 0 017.5 2.04A1 1 0 0116.62 17H3.38a1 1 0 01-.88-1.5z" clip-rule="evenodd" /><path d="M3.5 8.5a2 2 0 113.73 1 4.96 4.96 0 00-2.85 2.28A2 2 0 013.5 8.5z" /><path d="M14.5 8.5a2 2 0 113.73 1 2 2 0 01-.88 2.28 4.96 4.96 0 00-2.85-2.28z" /></svg>
                                    @break
                                @case('ads')
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M15.5 3.25A1.5 1.5 0 0117 4.75v10.5a1.5 1.5 0 01-2.25 1.3L10 13.8H6a3 3 0 010-6h4l4.75-2.75a1.5 1.5 0 01.75-.2z" /><path d="M6.5 15H8l.7 2.1A1 1 0 017.75 18H6.8a1 1 0 01-.95-.68L5 14.75c.47.16.97.25 1.5.25z" /></svg>
                                    @break
                                @case('conversion_actions')
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1.07A6.02 6.02 0 0115.93 9H17a1 1 0 110 2h-1.07A6.02 6.02 0 0111 15.93V17a1 1 0 11-2 0v-1.07A6.02 6.02 0 014.07 11H3a1 1 0 110-2h1.07A6.02 6.02 0 019 4.07V3a1 1 0 011-1zm0 4a4 4 0 100 8 4 4 0 000-8zm0 2a2 2 0 100 4 2 2 0 000-4z" clip-rule="evenodd" /></svg>
                                    @break
                                @case('conversion_jobs')
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 2a2 2 0 00-2 2v10a2 2 0 002 2h5.59l-1.3 1.3a1 1 0 101.42 1.4l3-3a1 1 0 000-1.4l-3-3a1 1 0 10-1.42 1.4L9.59 14H4V4h12v3a1 1 0 102 0V4a2 2 0 00-2-2H4zm10.3 8.3a1 1 0 011.4 0l1.3 1.29V10a1 1 0 112 0v4a1 1 0 01-1 1h-4a1 1 0 110-2h1.59l-1.3-1.3a1 1 0 010-1.4z" clip-rule="evenodd" /></svg>
                                    @break
                            @endswitch
                        </span>
                    </div>

                    <h3 class="mt-4 text-lg font-semibold text-white">{{ $section['title'] }}</h3>
                    <p class="mt-2 min-h-10 text-sm text-white/60">{{ $section['description'] }}</p>
                    <div class="mt-5 inline-flex min-h-10 items-center rounded-xl border border-white/10 bg-white/10 px-4 py-2 text-sm text-white/80 group-hover:bg-white/15">
                        {{ $section['label'] }}
                    </div>
                </a>
            @endforeach
        </div>
    </section>
@endsection

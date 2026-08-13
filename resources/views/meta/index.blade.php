@extends('meta.layout')

@section('title', 'Meta')
@section('subtitle', 'Centro de navegacion para cuentas, campanas, anuncios, formularios e integraciones de Meta')

@section('content')
    @php
        $sections = [
            [
                'title' => 'Cuentas publicitarias',
                'description' => 'Administra cuentas, clientes, estado de suscripcion y jobs asociados.',
                'route' => 'meta.ad-accounts.index',
                'label' => 'Abrir cuentas',
                'icon' => 'accounts',
            ],
            [
                'title' => 'WhatsApp',
                'description' => 'Administra WABAs, clientes, suscripciones webhook y jobs asociados.',
                'route' => 'meta.whatsapps.index',
                'label' => 'Abrir WhatsApp',
                'icon' => 'messages',
            ],
            [
                'title' => 'Campanas',
                'description' => 'Consulta y mantiene las campanas importadas desde Meta Ads.',
                'route' => 'meta.campaigns.index',
                'label' => 'Abrir campanas',
                'icon' => 'campaigns',
            ],
            [
                'title' => 'Conjuntos de anuncios',
                'description' => 'Revisa la estructura de ad sets y su relacion con campanas.',
                'route' => 'meta.ad-sets.index',
                'label' => 'Abrir conjuntos',
                'icon' => 'ad_sets',
            ],
            [
                'title' => 'Anuncios',
                'description' => 'Gestiona anuncios, nombres, estados y relaciones de pauta.',
                'route' => 'meta.ads.index',
                'label' => 'Abrir anuncios',
                'icon' => 'ads',
            ],
            [
                'title' => 'Insights',
                'description' => 'Consulta metricas sincronizadas y ejecuta consultas de resultados.',
                'route' => 'meta.insights.index',
                'label' => 'Abrir insights',
                'icon' => 'insights',
            ],
            [
                'title' => 'Access tokens',
                'description' => 'Administra tokens, refrescos y sincronizacion de paginas.',
                'route' => 'meta.access-tokens.index',
                'label' => 'Abrir tokens',
                'icon' => 'tokens',
            ],
            [
                'title' => 'Paginas',
                'description' => 'Gestiona paginas conectadas, formularios leadgen y suscripciones.',
                'route' => 'meta.pages.index',
                'label' => 'Abrir paginas',
                'icon' => 'pages',
            ],
            [
                'title' => 'Formularios',
                'description' => 'Sincroniza formularios Lead Ads y dispara la carga de leads.',
                'route' => 'meta.forms.index',
                'label' => 'Abrir formularios',
                'icon' => 'forms',
            ],
            [
                'title' => 'Mapeos de campos',
                'description' => 'Relaciona campos recibidos desde Meta con campos internos del CRM.',
                'route' => 'meta.form-field-mappings.index',
                'label' => 'Abrir mapeos',
                'icon' => 'mappings',
            ],
            [
                'title' => 'Meta events',
                'description' => 'Configura eventos enviados o usados en integraciones de conversiones.',
                'route' => 'meta.meta-events.index',
                'label' => 'Abrir eventos',
                'icon' => 'events',
            ],
        ];
    @endphp

    <section class="space-y-4">
        <div>
            <h2 class="text-2xl font-bold text-white">Menu De Meta</h2>
            <p class="mt-1 text-sm text-white/60">Selecciona la vista que quieres administrar.</p>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach($sections as $section)
                <a href="{{ route($section['route']) }}"
                   class="group rounded-2xl border border-white/10 bg-zinc-950/25 p-5 backdrop-blur transition hover:bg-white/5">
                    <div class="inline-flex h-12 items-center gap-2 rounded-xl bg-white/10 px-3 text-white/80">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 14.1C3 10.35 5.03 7 7.6 7c1.88 0 3.1 1.5 4.4 3.72C13.3 8.5 14.52 7 16.4 7 18.97 7 21 10.35 21 14.1c0 2.05-.9 3.4-2.28 3.4-1.45 0-2.55-1.45-3.96-3.95L12 8.86l-2.76 4.69C7.83 16.05 6.73 17.5 5.28 17.5 3.9 17.5 3 16.15 3 14.1Z" />
                        </svg>
                        <span class="text-white/40">-&gt;</span>
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/10">
                            @switch($section['icon'])
                                @case('accounts')
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M13 7a3 3 0 11-6 0 3 3 0 016 0z" /><path fill-rule="evenodd" d="M5 14a4 4 0 018 0v1a1 1 0 11-2 0v-1a2 2 0 10-4 0v1a1 1 0 11-2 0v-1z" clip-rule="evenodd" /><path d="M16 7a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                                    @break
                                @case('messages')
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2 5a3 3 0 013-3h10a3 3 0 013 3v6a3 3 0 01-3 3H8.4l-3.7 2.78A1 1 0 013 15.98V14H5a1 1 0 100-2 1 1 0 01-1-1V5zm4 1a1 1 0 100 2h8a1 1 0 100-2H6zm0 4a1 1 0 100 2h5a1 1 0 100-2H6z" clip-rule="evenodd" /></svg>
                                    @break
                                @case('campaigns')
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a1 1 0 011-1h1.6a7 7 0 014.1 1.33l.6.44A5 5 0 0013.23 5H16a1 1 0 011 1v7a1 1 0 01-1 1h-2.77a7 7 0 01-4.1-1.33l-.6-.44A5 5 0 005.6 11H5v5a1 1 0 11-2 0V4z" /></svg>
                                    @break
                                @case('ad_sets')
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M4 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H4zM14 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2h-2zM4 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H4zM14 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2h-2z" /></svg>
                                    @break
                                @case('ads')
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M15.5 3.25A1.5 1.5 0 0117 4.75v10.5a1.5 1.5 0 01-2.25 1.3L10 13.8H6a3 3 0 010-6h4l4.75-2.75a1.5 1.5 0 01.75-.2z" /><path d="M6.5 15H8l.7 2.1A1 1 0 017.75 18H6.8a1 1 0 01-.95-.68L5 14.75c.47.16.97.25 1.5.25z" /></svg>
                                    @break
                                @case('insights')
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M4 3a1 1 0 00-1 1v12a1 1 0 001 1h12a1 1 0 100-2H5V4a1 1 0 00-1-1z" /><path d="M15.293 5.293a1 1 0 011.414 1.414l-3.5 3.5a1 1 0 01-1.414 0L10 8.414l-2.293 2.293a1 1 0 01-1.414-1.414l3-3a1 1 0 011.414 0L12.5 8.086l2.793-2.793z" /></svg>
                                    @break
                                @case('tokens')
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a5 5 0 00-4.55 7.08L2.7 12.83A1 1 0 002.4 13.5V16a1 1 0 001 1H6a1 1 0 001-1v-1h1a1 1 0 001-1v-1h.5A5 5 0 1010 3zm2 5a1 1 0 102 0 1 1 0 00-2 0z" clip-rule="evenodd" /></svg>
                                    @break
                                @case('pages')
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 2a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7.5a1 1 0 00-.3-.71l-4.5-4.5A1 1 0 0011.5 2H5zm7 1.8V7h3.2L12 3.8zM7 10a1 1 0 100 2h6a1 1 0 100-2H7zm0 4a1 1 0 100 2h4a1 1 0 100-2H7z" clip-rule="evenodd" /></svg>
                                    @break
                                @case('forms')
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7 2a2 2 0 00-2 2H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1a2 2 0 00-2-2H7zm0 2h6v2H7V4zm-1 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm0 4a1 1 0 011-1h4a1 1 0 110 2H7a1 1 0 01-1-1z" clip-rule="evenodd" /></svg>
                                    @break
                                @case('mappings')
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 114 0 2 2 0 01-4 0zm0 12a2 2 0 114 0 2 2 0 01-4 0zm8-6a2 2 0 114 0 2 2 0 01-4 0zM7.7 5.7a1 1 0 011.4 0L12 8.6a1 1 0 01-1.4 1.4L7.7 7.1a1 1 0 010-1.4zm2.9 4.3a1 1 0 011.4 1.4l-2.9 2.9a1 1 0 11-1.4-1.4l2.9-2.9z" clip-rule="evenodd" /></svg>
                                    @break
                                @case('events')
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 011 1v1h6V3a1 1 0 112 0v1h1a2 2 0 012 2v2H2V6a2 2 0 012-2h1V3a1 1 0 011-1zm12 8H2v6a2 2 0 002 2h12a2 2 0 002-2v-6zm-8.12 1.35a1 1 0 011.74 0l.63 1.15 1.29.25a1 1 0 01.55 1.68l-.9.96.16 1.3a1 1 0 01-1.41 1.04L10.75 17l-1.19.73a1 1 0 01-1.41-1.04l.16-1.3-.9-.96a1 1 0 01.55-1.68l1.29-.25.63-1.15z" clip-rule="evenodd" /></svg>
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

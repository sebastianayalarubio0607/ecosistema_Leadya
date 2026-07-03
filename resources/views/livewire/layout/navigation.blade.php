<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component {
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

@php
    $navItems = [
        [
            'label' => 'Dashboard Leads',
            'route' => 'dashboard.leads',
            'active' => ['dashboard.leads'],
            'icon' => 'chart',
        ],

        [
            'label' => 'Customers',
            'route' => 'customers.index',
            'active' => ['customers.*'],
            'icon' => 'users',
        ],
        [
            'label' => 'Divisas',
            'route' => 'currencies.index',
            'active' => ['currencies.*'],
            'icon' => 'currency',
        ],
        [
            'label' => 'Meta Ad Accounts',
            'route' => 'meta.ad-accounts.index',
            'active' => ['meta.ad-accounts.*'],
            'icon' => 'ad-accounts',
        ],

        // ✅ META ADS (CRUD)
        [
            'label' => 'insights',
            'route' => 'meta.insights.index',
            'active' => ['meta.insights.*'],
            'icon' => 'insights',
        ],
                [
            'label' => 'Meta Ads',
            'route' => 'meta.ads.index',
            'active' => ['meta.ads.*'],
            'icon' => 'ads',
        ],
                [
            'label' => 'Meta Ad Sets',
            'route' => 'meta.ad-sets.index',
            'active' => ['meta.ad-sets.*'],
            'icon' => 'ad-sets',
        ],

        [
            'label' => 'Meta Campaigns',
            'route' => 'meta.campaigns.index',
            'active' => ['meta.campaigns.*'],
            'icon' => 'campaigns',
        ],
        [
            'label' => 'GAds Credentials',
            'route' => 'google-ads.credentials.index',
            'active' => ['google-ads.credentials.*'],
            'icon' => 'credentials',
        ],
        [
            'label' => 'GAds Campaigns',
            'route' => 'google-ads.campaigns.index',
            'active' => ['google-ads.campaigns.*'],
            'icon' => 'gads-campaigns',
        ],
        [
            'label' => 'GAds Ad Groups',
            'route' => 'google-ads.ad-groups.index',
            'active' => ['google-ads.ad-groups.*'],
            'icon' => 'ad-groups',
        ],
        [
            'label' => 'GAds Ads',
            'route' => 'google-ads.ads.index',
            'active' => ['google-ads.ads.*'],
            'icon' => 'ads',
        ],
        [
            'label' => 'Integration Types',
            'route' => 'integrationtypes.index',
            'active' => ['integrationtypes.*'],
            'icon' => 'puzzle',
        ],
        [
            'label' => 'Integrations',
            'route' => 'integrations.index',
            'active' => ['integrations.*'],
            'icon' => 'link',
        ],
        [
            'label' => 'Qualifications',
            'route' => 'qualifications.index',
            'active' => ['qualifications.*'],
            'icon' => 'tag',
        ],
        [
            'label' => 'CRM States',
            'route' => 'crmstates.index',
            'active' => ['crmstates.*'],
            'icon' => 'crm-states',
        ],
        [
            'label' => 'funnels',
            'route' => 'funnels.index',
            'active' => ['funnels.*'],
            'icon' => 'funnel',
        ],

        [
            'label' => 'meta_events',
            'route' => 'meta.meta-events.index',
            'active' => ['meta.meta-events.*'],
            'icon' => 'meta-events',
        ],

        /**
         * ✅ Estas son las nuevas rutas que añadiste, las he puesto al final para que se vean claramente, pero puedes ordenarlas como quieras
         */
                [
            'label' => 'access-tokens',
            'route' => 'meta.access-tokens.index',
            'active' => ['meta.access-tokens.*'],
            'icon' => 'access-tokens',
        ],
                [
            'label' => 'meta_forms',
            'route' => 'meta.forms.index',
            'active' => ['meta.forms.*'],
            'icon' => 'forms',
        ],
                [
            'label' => 'form-field-mappings',
            'route' => 'meta.form-field-mappings.index',
            'active' => ['meta.form-field-mappings.*'],
            'icon' => 'form-field-mappings',
        ],
                        [
            'label' => 'meta_pages',
            'route' => 'meta.pages.index',
            'active' => ['meta.pages.*'],
            'icon' => 'pages',
        ],

        [
            'label' => 'platforms',
            'route' => 'platforms.index',
            'active' => ['platforms.*'],
            'icon' => 'platforms',
        ],
        [
            'label' => 'Geos',
            'route' => 'geos.index',
            'active' => ['geos.*'],
            'icon' => 'Geos',
        ],
        [
            'label' => 'languages',
            'route' => 'languages.index',
            'active' => ['languages.*'],
            'icon' => 'languages',
        ],
        [
            'label' => 'origins',
            'route' => 'origins.index',
            'active' => ['origins.*'],
            'icon' => 'origins',
        ],
        [
            'label' => 'sources',
            'route' => 'sources.index',
            'active' => ['sources.*'],
            'icon' => 'sources',
        ],
                [
            'label' => 'campaign objectives',
            'route' => 'campaign_objectives.index',
            'active' => ['campaign_objectives.*'],
            'icon' => 'campaign_objectives',
        ],
    ];

    // ✅ Esta variable es la que te falta y por eso cae el error
    $isActive = function (array $patterns): bool {
        foreach ($patterns as $p) {
            if (request()->routeIs($p)) {
                return true;
            }
        }
        return false;
    };

    $userName = auth()->user()?->name ?? 'Usuario';
    $userEmail = auth()->user()?->email ?? '';
    $userInitial = strtoupper(mb_substr($userName, 0, 1));
@endphp

<aside x-data="{ collapsed: true }" :class="collapsed ? 'w-16' : 'w-64'"
    class="shrink-0 min-h-screen sticky top-0 flex flex-col bg-slate-950/40 backdrop-blur border-r border-white/10">
    {{-- Top / Logo --}}
    <div class="h-16 flex items-center gap-2 px-3 border-b border-white/10">
        <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2">
            <x-application-logo class="block w-10 fill-current text-gray-100" />
            <span x-show="!collapsed" x-cloak class="font-semibold tracking-wide">Leadsya</span>
        </a>

        <button type="button" @click="collapsed = !collapsed"
            class="ml-auto inline-flex items-center justify-center h-9 w-9 rounded-xl hover:bg-white/10 text-white/80"
            title="Expandir/Colapsar">
            <svg x-show="collapsed" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                fill="currentColor">
                <path fill-rule="evenodd"
                    d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                    clip-rule="evenodd" />
            </svg>
            <svg x-show="!collapsed" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                fill="currentColor">
                <path fill-rule="evenodd"
                    d="M12.707 14.707a1 1 0 010-1.414L9.414 10l3.293-3.293a1 1 0 00-1.414-1.414l-4 4a1 1 0 000 1.414l4 4a1 1 0 001.414 0z"
                    clip-rule="evenodd" />
            </svg>
        </button>
    </div>

    {{-- Nav --}}
    <nav class="flex-1 px-2 py-4 space-y-1">
        @foreach ($navItems as $item)
            @php $active = $isActive($item['active']); @endphp

            <a href="{{ route($item['route']) }}" wire:navigate
                class="group flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition
                    {{ $active ? 'bg-white/10 text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                title="{{ $item['label'] }}">
                {{-- Icon --}}
                <span
                    class="shrink-0 inline-flex items-center justify-center h-9 w-9 rounded-xl
                    {{ $active ? 'bg-white/10' : 'bg-white/5 group-hover:bg-white/10' }}">
                    @if ($item['icon'] === 'home')
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path
                                d="M10.707 1.293a1 1 0 00-1.414 0l-7 7A1 1 0 003 9h1v8a1 1 0 001 1h4a1 1 0 001-1v-4h2v4a1 1 0 001 1h4a1 1 0 001-1V9h1a1 1 0 00.707-1.707l-7-7z" />
                        </svg>
                    @elseif ($item['icon'] === 'chart')
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path
                                d="M3 3a1 1 0 011-1h1a1 1 0 010 2H5v12h12v-1a1 1 0 112 0v1a1 1 0 01-1 1H4a1 1 0 01-1-1V3z" />
                            <path
                                d="M7 12a1 1 0 012 0v2a1 1 0 11-2 0v-2zM11 8a1 1 0 112 0v6a1 1 0 11-2 0V8zM15 10a1 1 0 112 0v4a1 1 0 11-2 0v-4z" />
                        </svg>
                    @elseif ($item['icon'] === 'users')
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M13 7a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path fill-rule="evenodd"
                                d="M5 14a4 4 0 018 0v1a1 1 0 11-2 0v-1a2 2 0 10-4 0v1a1 1 0 11-2 0v-1z"
                                clip-rule="evenodd" />
                            <path d="M16 7a2 2 0 11-4 0 2 2 0 014 0z" />
                            <path d="M14 14a3 3 0 016 0v1a1 1 0 11-2 0v-1a1 1 0 10-2 0v1a1 1 0 11-2 0v-1z" />
                        </svg>
                    @elseif ($item['icon'] === 'puzzle')
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path
                                d="M6 2a2 2 0 00-2 2v2H3a1 1 0 000 2h1v2H3a1 1 0 000 2h1v2a2 2 0 002 2h2v-1a1 1 0 112 0v1h2a2 2 0 002-2v-2h1a1 1 0 100-2h-1V8h1a1 1 0 100-2h-1V4a2 2 0 00-2-2h-2v1a1 1 0 11-2 0V2H6z" />
                        </svg>
                    @elseif ($item['icon'] === 'link')
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M12.586 2.586a2 2 0 112.828 2.828l-2 2a2 2 0 11-2.828-2.828l2-2z" />
                            <path d="M7.414 17.414a2 2 0 11-2.828-2.828l2-2a2 2 0 112.828 2.828l-2 2z" />
                            <path fill-rule="evenodd"
                                d="M8.707 11.293a1 1 0 010-1.414l2.586-2.586a1 1 0 111.414 1.414l-2.586 2.586a1 1 0 01-1.414 0z"
                                clip-rule="evenodd" />
                        </svg>
                    @elseif ($item['icon'] === 'funnel')
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="size-6">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" />
                        </svg>
                    @elseif ($item['icon'] === 'tag')
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path
                                d="M17.707 10.293l-7-7A1 1 0 0010 3H4a1 1 0 00-1 1v6a1 1 0 00.293.707l7 7a1 1 0 001.414 0l6-6a1 1 0 000-1.414zM6 7a1 1 0 112 0 1 1 0 01-2 0z" />
                        </svg>
                    @elseif ($item['icon'] === 'ad-accounts')
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M3 4a2 2 0 012-2h10a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V4zm4.5 5a2.5 2.5 0 100-5 2.5 2.5 0 000 5zm-3.2 5.4A4.2 4.2 0 017.5 13a4.2 4.2 0 013.2 1.4.75.75 0 01-.58 1.22H4.88a.75.75 0 01-.58-1.22zM12 6a1 1 0 011-1h2a1 1 0 110 2h-2a1 1 0 01-1-1zm0 4a1 1 0 011-1h2a1 1 0 110 2h-2a1 1 0 01-1-1z"
                                clip-rule="evenodd" />
                        </svg>
                    @elseif ($item['icon'] === 'insights')
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path
                                d="M4 3a1 1 0 00-1 1v12a1 1 0 001 1h12a1 1 0 100-2H5V4a1 1 0 00-1-1z" />
                            <path
                                d="M15.293 5.293a1 1 0 011.414 1.414l-3.5 3.5a1 1 0 01-1.414 0L10 8.414l-2.293 2.293a1 1 0 01-1.414-1.414l3-3a1 1 0 011.414 0L12.5 8.086l2.793-2.793z" />
                        </svg>
                    @elseif ($item['icon'] === 'ads')
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path
                                d="M15.5 3.25A1.5 1.5 0 0117 4.75v10.5a1.5 1.5 0 01-2.25 1.3L10 13.8H6a3 3 0 010-6h4l4.75-2.75a1.5 1.5 0 01.75-.2z" />
                            <path d="M6.5 15H8l.7 2.1A1 1 0 017.75 18H6.8a1 1 0 01-.95-.68L5 14.75c.47.16.97.25 1.5.25z" />
                        </svg>
                    @elseif ($item['icon'] === 'ad-sets')
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M4 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H4z" />
                            <path d="M14 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2h-2z" />
                            <path d="M4 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H4z" />
                            <path d="M14 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2h-2z" />
                        </svg>
                    @elseif ($item['icon'] === 'campaigns')
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path
                                d="M3 4a1 1 0 011-1h1.6a7 7 0 014.1 1.33l.6.44A5 5 0 0013.23 5H16a1 1 0 011 1v7a1 1 0 01-1 1h-2.77a7 7 0 01-4.1-1.33l-.6-.44A5 5 0 005.6 11H5v5a1 1 0 11-2 0V4z" />
                        </svg>
                    @elseif ($item['icon'] === 'credentials')
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M10 3a5 5 0 00-4.55 7.08L2.7 12.83A1 1 0 002.4 13.5V16a1 1 0 001 1H6a1 1 0 001-1v-1h1a1 1 0 001-1v-1h.5A5 5 0 1010 3zm2 5a1 1 0 102 0 1 1 0 00-2 0z"
                                clip-rule="evenodd" />
                        </svg>
                    @elseif ($item['icon'] === 'gads-campaigns')
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M4 13a1 1 0 011-1h1a1 1 0 011 1v4H4v-4z" />
                            <path d="M9 9a1 1 0 011-1h1a1 1 0 011 1v8H9V9z" />
                            <path d="M14 5a1 1 0 011-1h1a1 1 0 011 1v12h-3V5z" />
                            <path fill-rule="evenodd"
                                d="M3 17a1 1 0 001 1h13a1 1 0 100-2H4a1 1 0 00-1 1z"
                                clip-rule="evenodd" />
                        </svg>
                    @elseif ($item['icon'] === 'ad-groups')
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M7 8a3 3 0 116 0 3 3 0 01-6 0z" />
                            <path fill-rule="evenodd"
                                d="M2.5 15.5A5.5 5.5 0 0110 13.46a5.5 5.5 0 017.5 2.04A1 1 0 0116.62 17H3.38a1 1 0 01-.88-1.5z"
                                clip-rule="evenodd" />
                            <path d="M3.5 8.5a2 2 0 113.73 1 4.96 4.96 0 00-2.85 2.28A2 2 0 013.5 8.5z" />
                            <path d="M14.5 8.5a2 2 0 113.73 1 2 2 0 01-.88 2.28 4.96 4.96 0 00-2.85-2.28z" />
                        </svg>
                    @elseif ($item['icon'] === 'crm-states')
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M3 4a2 2 0 012-2h1a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V4z" />
                            <path d="M12 2a2 2 0 00-2 2v7a2 2 0 002 2h1a2 2 0 002-2V4a2 2 0 00-2-2h-1z" />
                            <path d="M16 6a1 1 0 011 1v9a2 2 0 01-2 2h-1a1 1 0 110-2h1V7a1 1 0 011-1z" />
                        </svg>
                    @elseif ($item['icon'] === 'meta-events')
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M6 2a1 1 0 011 1v1h6V3a1 1 0 112 0v1h1a2 2 0 012 2v2H2V6a2 2 0 012-2h1V3a1 1 0 011-1zm12 8H2v6a2 2 0 002 2h12a2 2 0 002-2v-6zm-8.12 1.35a1 1 0 011.74 0l.63 1.15 1.29.25a1 1 0 01.55 1.68l-.9.96.16 1.3a1 1 0 01-1.41 1.04L10.75 17l-1.19.73a1 1 0 01-1.41-1.04l.16-1.3-.9-.96a1 1 0 01.55-1.68l1.29-.25.63-1.15z"
                                clip-rule="evenodd" />
                        </svg>
                    @elseif ($item['icon'] === 'access-tokens')
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M9.5 4a4.5 4.5 0 104.06 6.44l.73.73a1 1 0 00.71.29H16v1a1 1 0 001 1h1v2h-2a1 1 0 00-1 1v1h-2v-2a1 1 0 00-.29-.7l-1.98-1.99A4.5 4.5 0 009.5 4zm-2 4.5a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0z"
                                clip-rule="evenodd" />
                        </svg>
                    @elseif ($item['icon'] === 'forms')
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M7 2a2 2 0 00-2 2H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1a2 2 0 00-2-2H7zm0 2h6v2H7V4zm-1 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm0 4a1 1 0 011-1h4a1 1 0 110 2H7a1 1 0 01-1-1z"
                                clip-rule="evenodd" />
                        </svg>
                    @elseif ($item['icon'] === 'form-field-mappings')
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4 4a2 2 0 114 0 2 2 0 01-4 0zm0 12a2 2 0 114 0 2 2 0 01-4 0zm8-6a2 2 0 114 0 2 2 0 01-4 0zM7.7 5.7a1 1 0 011.4 0L12 8.6a1 1 0 01-1.4 1.4L7.7 7.1a1 1 0 010-1.4zm2.9 4.3a1 1 0 011.4 1.4l-2.9 2.9a1 1 0 11-1.4-1.4l2.9-2.9z"
                                clip-rule="evenodd" />
                        </svg>
                    @elseif ($item['icon'] === 'pages')
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M5 2a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7.5a1 1 0 00-.3-.71l-4.5-4.5A1 1 0 0011.5 2H5zm7 1.8V7h3.2L12 3.8zM7 10a1 1 0 100 2h6a1 1 0 100-2H7zm0 4a1 1 0 100 2h4a1 1 0 100-2H7z"
                                clip-rule="evenodd" />
                        </svg>
                    @elseif ($item['icon'] === 'platforms')
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M3 4a2 2 0 012-2h10a2 2 0 012 2v7a2 2 0 01-2 2h-4v2h2a1 1 0 110 2H7a1 1 0 110-2h2v-2H5a2 2 0 01-2-2V4zm2 0v7h10V4H5z"
                                clip-rule="evenodd" />
                        </svg>
                    @elseif ($item['icon'] === 'Geos')
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.47-9h2.46a6.02 6.02 0 00-4.5-4.8A10.2 10.2 0 0113.47 9zM10 4.07A8.22 8.22 0 0011.45 9h-2.9A8.22 8.22 0 0010 4.07zM4.07 11h2.46c.24 1.79.96 3.46 2.04 4.8A6.02 6.02 0 014.07 11zm2.46-2H4.07a6.02 6.02 0 014.5-4.8A10.2 10.2 0 006.53 9zm2.02 2h2.9A8.22 8.22 0 0110 15.93 8.22 8.22 0 018.55 11zm2.88 4.8a10.2 10.2 0 002.04-4.8h2.46a6.02 6.02 0 01-4.5 4.8z"
                                clip-rule="evenodd" />
                        </svg>
                    @elseif ($item['icon'] === 'languages')
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path
                                d="M4 3a1 1 0 00-1 1v9a1 1 0 001 1h1v2a1 1 0 001.6.8L10.33 14H16a1 1 0 001-1V4a1 1 0 00-1-1H4zm3 3a1 1 0 011-1h4a1 1 0 010 2h-1.14a7.97 7.97 0 01-.87 2.04c.5.39 1.05.7 1.65.91a1 1 0 01-.68 1.88 7.04 7.04 0 01-2.18-1.21 7.15 7.15 0 01-2.14 1.21 1 1 0 11-.7-1.87 5.02 5.02 0 001.52-.83A7.11 7.11 0 016.61 8.2 1 1 0 018.4 7.3c.1.2.22.39.35.58.16-.28.3-.57.42-.88H8a1 1 0 01-1-1z" />
                        </svg>
                    @elseif ($item['icon'] === 'origins')
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M10 18s6-5.2 6-10A6 6 0 104 8c0 4.8 6 10 6 10zm0-7a3 3 0 100-6 3 3 0 000 6z"
                                clip-rule="evenodd" />
                        </svg>
                    @elseif ($item['icon'] === 'sources')
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M5 7a3 3 0 100-6 3 3 0 000 6z" />
                            <path d="M15 12a3 3 0 100-6 3 3 0 000 6z" />
                            <path d="M6 19a3 3 0 100-6 3 3 0 000 6z" />
                            <path fill-rule="evenodd"
                                d="M7.7 5.58a1 1 0 011.32-.5l3.2 1.44a1 1 0 11-.82 1.82L8.2 6.9a1 1 0 01-.5-1.32zm4.58 6.08a1 1 0 01-.44 1.34l-3.1 1.55a1 1 0 11-.9-1.78l3.1-1.55a1 1 0 011.34.44z"
                                clip-rule="evenodd" />
                        </svg>
                    @elseif ($item['icon'] === 'campaign_objectives')
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M10 2a1 1 0 011 1v1.07A6.02 6.02 0 0115.93 9H17a1 1 0 110 2h-1.07A6.02 6.02 0 0111 15.93V17a1 1 0 11-2 0v-1.07A6.02 6.02 0 014.07 11H3a1 1 0 110-2h1.07A6.02 6.02 0 019 4.07V3a1 1 0 011-1zm0 4a4 4 0 100 8 4 4 0 000-8zm0 2a2 2 0 100 4 2 2 0 000-4z"
                                clip-rule="evenodd" />
                        </svg>
                    @else
                        {{-- list --}}
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4 6a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zm0 4a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zm1 3a1 1 0 100 2h10a1 1 0 100-2H5z"
                                clip-rule="evenodd" />
                        </svg>
                    @endif

                </span>

                {{-- Label --}}
                <span x-show="!collapsed" x-cloak class="truncate">
                    {{ $item['label'] }}
                </span>
            </a>
        @endforeach
    </nav>

    {{-- User (SIN dropdown): inicial -> perfil + botón cerrar sesión --}}
    <div class="border-t border-white/10 p-2">
        <div class="flex flex-col gap-2">
            {{-- Avatar: click -> Perfil --}}
            <a href="{{ route('profile') }}" wire:navigate
                class="w-full flex items-center gap-3 rounded-xl px-3 py-2 hover:bg-white/5 transition" title="Perfil">
                <span class="h-9 w-9 rounded-xl bg-white/10 flex items-center justify-center font-semibold">
                    {{ $userInitial }}
                </span>

                <div x-show="!collapsed" x-cloak class="min-w-0">
                    <div class="text-sm font-medium truncate">{{ $userName }}</div>
                    <div class="text-xs text-white/50 truncate">{{ $userEmail }}</div>
                </div>
            </a>

            {{-- Botón Cerrar sesión --}}
            <button type="button" wire:click="logout"
                class="w-full flex items-center gap-3 rounded-xl px-3 py-2 bg-red-600/20 hover:bg-red-600/30 text-red-100 transition"
                title="Cerrar sesión">
                <span class="h-9 w-9 rounded-xl bg-red-600/20 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M3 4a1 1 0 011-1h6a1 1 0 110 2H5v10h5a1 1 0 110 2H4a1 1 0 01-1-1V4zm10.293 3.293a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 01-1.414-1.414L14.586 11H8a1 1 0 110-2h6.586l-1.293-1.293a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </span>

                <span x-show="!collapsed" x-cloak class="text-sm font-medium">
                    Cerrar sesión
                </span>
            </button>
        </div>
    </div>
</aside>

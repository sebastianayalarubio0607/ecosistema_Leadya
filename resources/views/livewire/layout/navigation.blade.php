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
            'label' => 'Meta Ad Accounts',
            'route' => 'meta.ad-accounts.index',
            'active' => ['meta.ad-accounts.*'],
            'icon' => 'users',
        ],

        // ✅ META ADS (CRUD)
        [
            'label' => 'insights',
            'route' => 'meta.insights.index',
            'active' => ['meta.insights.*'],
            'icon' => 'list',
        ],
                [
            'label' => 'Meta Ads',
            'route' => 'meta.ads.index',
            'active' => ['meta.ads.*'],
            'icon' => 'list',
        ],
                [
            'label' => 'Meta Ad Sets',
            'route' => 'meta.ad-sets.index',
            'active' => ['meta.ad-sets.*'],
            'icon' => 'list',
        ],

        [
            'label' => 'Meta Campaigns',
            'route' => 'meta.campaigns.index',
            'active' => ['meta.campaigns.*'],
            'icon' => 'list',
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
            'icon' => 'list',
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
            'icon' => 'funnel',
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

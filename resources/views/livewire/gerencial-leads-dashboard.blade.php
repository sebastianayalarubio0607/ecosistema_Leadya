    <div class="p-6 mx-auto space-y-6">
        <div class="grid grid-cols-12 md:grid-cols-12 gap-4">
            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 col-span-3">
                <div class="text-sm text-white/60">Cliente seleccionado</div>
                <div class="text-lg font-semibold text-white">{{ $ui['header']['selected_customer_name'] }}</div>
                @if (!empty($ui['header']['selected_customer_id']))
                    <div class="text-xs text-white/50">customer_id: {{ $ui['header']['selected_customer_id'] }}</div>
                @endif
            </div>

            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 col-span-9">
                <form wire:submit.prevent="applyFilters"
                    class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                    @if (!empty($ui['filters']['integration_id']))
                        <input type="hidden" wire:model="integrationId">
                    @endif

                    <div class="md:col-span-4">
                        <label class="block mb-1 text-white/70">Cliente</label>
                        <select wire:model="customerId"
                            class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                            <option value="">-- Todos los clientes --</option>
                            @foreach ($ui['filters']['customer_options'] as $option)
                                <option value="{{ $option['value'] }}" @selected($option['selected'])>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-4">
                        <label class="block mb-1 text-white/70">Desde</label>
                        <input type="datetime-local" wire:model="from"
                            max="{{ $ui['filters']['now_max'] }}"
                            class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    </div>

                    <div class="md:col-span-4">
                        <label class="block mb-1 text-white/70">Hasta</label>
                        <input type="datetime-local" wire:model="to"
                            max="{{ $ui['filters']['now_max'] }}"
                            class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                    </div>

                    <div class="md:col-span-3">
                        <label class="block mb-1 text-white/70">Source</label>
                        <select wire:model="source"
                            class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                            <option value="">Todos</option>
                            @foreach ($ui['filters']['source_options'] as $option)
                                <option value="{{ $option['value'] }}" @selected($option['selected'])>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-3">
                        <label class="block mb-1 text-white/70">Origen</label>
                        <select wire:model="campaignOrigin"
                            class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                            <option value="">Todos</option>
                            @foreach ($ui['filters']['origin_options'] as $option)
                                <option value="{{ $option['value'] }}" @selected($option['selected'])>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-3 flex gap-2">
                        <button
                            class="w-full px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
                            <span wire:loading.remove>Consultar</span>
                            <span wire:loading>Consultando...</span>
                        </button>

                        <button type="button" wire:click="clearFilters"
                            class="w-full text-center px-4 py-2 rounded-xl bg-zinc-950/25 hover:bg-white/10 text-white border border-white/10">
                            Limpiar
                        </button>
                    </div>
                    <div wire:dirty class="md:col-span-12 text-sm font-semibold text-amber-100/80">
                        Filtros modificados. Presiona Consultar para actualizar el dashboard.
                    </div>
                </form>
            </div>
        </div>

        @unless($hasData)
            <section class="rounded-2xl border border-white/10 bg-zinc-950/25 p-8 text-center backdrop-blur">
                <h2 class="text-xl font-semibold text-white">Selecciona filtros para consultar el dashboard</h2>
                <p class="mx-auto mt-2 max-w-2xl text-sm text-white/60">La información gerencial, gráficas, funnels y pauta se cargará cuando presiones Consultar.</p>
            </section>
        @else

        <h2 class="text-2xl text-white font-bold">Resumen - {{ $ui['header']['selected_customer_name'] }}</h2>

        <div class="grid grid-cols-1 md:grid-cols-7 gap-2">
            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 col-span-1">
                <div class="text-sm text-white/60">Leads en LQ en el periodo seleccionado</div>
                <div class="text-3xl font-bold text-white">{{ $ui['summary']['count'] }}</div>
                <span class="text-3xl font-bold text-white">Periodo:</span> <br>
                <span class="text-xs text-white/80 font-semibold">{{ $ui['summary']['period_label'] }}</span>
            </div>

            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 col-span-1">
                <div class="text-sm text-white/60">Leads en LQ gestionados</div>
                <div class="text-3xl font-bold text-white">{{ $ui['summary']['managed'] }}</div>
            </div>

            <a href="{{ $ui['summary']['pending_url'] }}"
                class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 col-span-1 hover:bg-white/5 transition block">
                <div class="text-sm text-white/60">Por gestionar</div>
                <div class="text-3xl font-bold text-white">{{ $ui['summary']['pending'] }}</div>
                <div
                    class="mt-3 inline-flex items-center justify-between w-full px-3 py-2 rounded-xl bg-white/10 text-white/80 text-sm border border-white/10">
                    <span>Ver lista</span><span class="text-white/50">&rsaquo;</span>
                </div>
            </a>

            @foreach ($ui['summary_cards'] as $card)
                @include('dashboard.partials.summary-action-card', ['card' => $card])
            @endforeach

            <livewire:general-leads-costs
                :query="$filtersQuery"
                mode="gerencial-summary"
                :key="'gerencial-costs-summary-'.md5(json_encode($filtersQuery))"
            />
        </div>

        <h2 class="text-2xl text-white font-bold">Desglose - {{ $ui['header']['selected_customer_name'] }}</h2>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @include('dashboard.partials.donut-card', [
                'title' => 'Por Source',
                'donut' => $ui['donuts']['sources'],
                'canvasId' => 'donutSources',
                'legendId' => 'legendSources',
                'cardClass' => 'col-span-1',
            ])

            @include('dashboard.partials.donut-card', [
                'title' => 'Por Medio',
                'donut' => $ui['donuts']['platforms'],
                'canvasId' => 'donutPlatforms',
                'legendId' => 'legendPlatforms',
                'cardClass' => 'col-span-1',
            ])

            <div class="col-span-1 lg:col-span-2 grid grid-cols-1 gap-4">
            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 col-span-1">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <div class="text-sm text-white/60">Desglose</div>
                        <h3 class="text-white font-semibold">Totales Por Source</h3>
                    </div>
                    <div class="text-xs text-white/50">
                        Total: <span class="text-white/80 font-semibold">{{ $ui['donuts']['sources']['total'] ?? 0 }}</span>
                    </div>
                </div>

                <div class="py-12 grid grid-cols-12 gap-4 items-center">
                    <div class="col-span-12 md:col-span-6">
                        <div class="relative h-60">
                            <canvas id="donutSourceTotals"
                                data-breakdown-rows='@json($ui['donuts']['sources']['breakdown_rows'] ?? [])'
                                data-dimension-title="Source"></canvas>
                        </div>
                    </div>

                    <div class="col-span-12 md:col-span-6">
                        <div class="text-xs text-white/50 mb-2">Tabla de totales</div>
                        <div id="legendSourceTotals" class="overflow-x-auto"></div>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 col-span-1">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <div class="text-sm text-white/60">Desglose</div>
                        <h3 class="text-white font-semibold">Totales Por Medio</h3>
                    </div>
                    <div class="text-xs text-white/50">
                        Total: <span class="text-white/80 font-semibold">{{ $ui['donuts']['platforms']['total'] ?? 0 }}</span>
                    </div>
                </div>

                <div class="py-12 grid grid-cols-12 gap-4 items-center">
                    <div class="col-span-12 md:col-span-6">
                        <div class="relative h-60">
                            <canvas id="donutPlatformTotals"
                                data-breakdown-rows='@json($ui['donuts']['platforms']['breakdown_rows'] ?? [])'
                                data-dimension-title="Medio"></canvas>
                        </div>
                    </div>

                    <div class="col-span-12 md:col-span-6">
                        <div class="text-xs text-white/50 mb-2">Tabla de totales</div>
                        <div id="legendPlatformTotals" class="overflow-x-auto"></div>
                    </div>
                </div>
            </div>
        </div>
        </div>
        <h2 class="text-2xl text-white font-bold">Funnel - {{ $ui['header']['selected_customer_name'] }}</h2>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @include('dashboard.partials.funnel-stack', [
                'title' => 'Estado actual de Leads en LQ por Funnel',
                'cards' => $ui['cards']['funnels'],
                'totalLabel' => 'Total',
                'totalValue' => $ui['totals']['total_leads'],
                'stackGap' => 'space-y-2',
                'cardPadding' => 'p-4',
                'variant' => 'default',
                'cardClass' => 'col-span-1',
            ])

            @include('dashboard.partials.funnel-stack', [
                'title' => 'Historico Leads en LQ en el Funnel',
                'cards' => $ui['cards']['funnels_history'],
                'totalLabel' => 'Total',
                'totalValue' => $ui['totals']['total_leads'],
                'stackGap' => 'space-y-4',
                'cardPadding' => 'p-2',
                'variant' => 'history',
                'cardClass' => 'col-span-1',
            ])
        </div>

        @php($historyDailyChart = $ui['charts']['funnels_history_daily'] ?? ['labels' => [], 'datasets' => []])
        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 w-full">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-white font-semibold">Historico Leads en LQ en el Funnel por dia</h3>
                <div class="text-xs text-white/50">
                    Total:
                    <span class="text-white/80 font-semibold">{{ $ui['totals']['total_leads'] }}</span>
                </div>
            </div>
            <div class="h-80 w-full">
                <canvas id="funnelHistoryDailyChart" data-labels='@json($historyDailyChart['labels'])'
                    data-datasets='@json($historyDailyChart['datasets'])'></canvas>
            </div>
        </div>


        <h2 class="text-2xl text-white font-bold">Datos de Meta - {{ $ui['header']['selected_customer_name'] }}</h2>

        @foreach (['meta_campaigns', 'meta_ad_sets', 'meta_ads'] as $section)
            <livewire:gerencial-leads-ads-table
                :section="$section"
                :query="$filtersQuery"
                :customer-name="$ui['header']['selected_customer_name']"
                :period-label="$ui['summary']['period_label']"
                :key="'gerencial-ads-'.$section.'-'.md5(json_encode($filtersQuery))"
            />
        @endforeach

        <h2 class="text-2xl text-white font-bold">Datos de Google - {{ $ui['header']['selected_customer_name'] }}</h2>

        @foreach (['google_campaigns', 'google_ad_groups', 'google_ads'] as $section)
            <livewire:gerencial-leads-ads-table
                :section="$section"
                :query="$filtersQuery"
                :customer-name="$ui['header']['selected_customer_name']"
                :period-label="$ui['summary']['period_label']"
                :key="'gerencial-ads-'.$section.'-'.md5(json_encode($filtersQuery))"
            />
        @endforeach
        @endunless
    </div>

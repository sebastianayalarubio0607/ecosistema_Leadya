<div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 col-span-3">
    <div class="flex items-center justify-between mb-3">
        <div>
            <div class="text-sm text-white/60">Desglose</div>
            <h3 class="text-white font-semibold">{{ $title }}</h3>
        </div>
        <div class="text-xs text-white/50">
            Total: <span class="text-white/80 font-semibold">{{ $donut['total'] }}</span>
        </div>
    </div>

    @if (empty($donut['pairs']))
        <div class="text-sm text-white/60">Sin datos.</div>
    @else
        <div class="flex items-center">
            <div class="py-12 grid grid-cols-12 gap-4 items-center">
                <div class="col-span-12 md:col-span-6">
                    <div class="relative h-60">
                        <canvas id="{{ $canvasId }}" data-labels='@json($donut['labels'])'
                            data-values='@json($donut['values'])' data-keys='@json($donut['keys'])'
                            data-base-url='@json($donut['base_url'])' data-group-type="{{ $donut['group_type'] }}"></canvas>
                    </div>
                </div>

                <div class="col-span-12 md:col-span-6">
                    <div class="text-xs text-white/50 mb-2">Leyenda (clic)</div>
                    <div id="{{ $legendId }}" class="space-y-2"></div>
                </div>
            </div>
        </div>
    @endif
</div>

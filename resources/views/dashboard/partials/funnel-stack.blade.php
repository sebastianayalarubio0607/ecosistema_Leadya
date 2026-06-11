@php($widths = ['w-full', 'w-11/12', 'w-10/12', 'w-9/12', 'w-8/12'])

<div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 {{ $cardClass ?? 'col-span-3' }}">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-white font-semibold">{{ $title }}</h3>
        <div class="text-xs text-white/50">
            @if (!empty($totalLabel))
                {{ $totalLabel }}:
                <span class="text-white/80 font-semibold">{{ $totalValue }}</span>
            @endif
        </div>
    </div>

    <div class="relative max-w-xl mx-auto py-2">
        <div class="absolute left-4 top-1 bottom-4 w-px bg-white/10"></div>

        <div class="{{ $stackGap ?? 'space-y-2' }}">
            @foreach ($cards as $card)
                <div class="relative flex justify-center">
                    <div class="absolute left-4 top-1/2 -translate-y-1/2 flex items-center">
                        <div class="h-2.5 w-2.5 rounded-full bg-white/30"></div>
                        <div class="h-px w-6 bg-white/10"></div>
                    </div>
                    <a href="{{ $card['url'] }}" class="{{ $widths[$loop->index] ?? end($widths) }} block">
                        <div
                            class="rounded-2xl border border-white/10 bg-zinc-950/25 {{ $cardPadding ?? 'p-4' }} hover:bg-white/5 transition text-center">
                            @if (($variant ?? 'default') === 'history')
                                <div class="text-2xl font-extrabold text-white leading-none">
                                    {{ $card['count'] }} - {{ $card['pct'] }}%
                                </div>
                                <div class="text-sm text-white/80 font-semibold truncate">{{ $card['name'] }}</div>
                            @else
                                <span class="text-sm text-white/80 font-semibold truncate">
                                    {{ $card['name'] }}:
                                    <span class="text-2xl font-extrabold text-white leading-none">
                                        {{ $card['count'] }} - {{ $card['pct'] }}%
                                    </span>
                                </span>
                            @endif
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</div>

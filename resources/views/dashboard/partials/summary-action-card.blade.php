<div class="col-span-1">
    @if (!empty($card['url']))
        <a href="{{ $card['url'] }}"
            class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 hover:bg-white/5 transition block">
            <div class="text-sm text-white/60">{{ $card['title'] }}</div>
            <div class="text-3xl font-bold text-white">{{ $card['value'] }}</div>
            @if (!empty($card['extra_label']))
                <div class="text-sm text-white/60">{{ $card['extra_label'] }}</div>
                <div class="text-3xl font-bold text-white">{{ $card['extra_value'] }}</div>
            @endif
            <div
                class="mt-3 inline-flex items-center justify-between w-full px-3 py-2 rounded-xl bg-white/10 text-white/80 text-sm border border-white/10">
                <span>{{ $card['cta'] ?? 'Ver lista' }}</span><span class="text-white/50">&rsaquo;</span>
            </div>
        </a>
    @else
        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4">
            <div class="text-sm text-white/60">{{ $card['title'] }}</div>
            <div class="text-3xl font-bold text-white">{{ $card['value'] }}</div>
            @if (!empty($card['extra_label']))
                <div class="text-sm text-white/60">{{ $card['extra_label'] }}</div>
                <div class="text-3xl font-bold text-white">{{ $card['extra_value'] }}</div>
            @endif
            <div class="text-xs text-white/50 mt-1">{{ $card['missing'] ?? '' }}</div>
        </div>
    @endif
</div>

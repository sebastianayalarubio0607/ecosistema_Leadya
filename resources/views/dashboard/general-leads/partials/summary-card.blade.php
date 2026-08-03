@php
    $href = $href ?? null;
@endphp

@if($href)
    <a href="{{ $href }}" class="block rounded-2xl border border-white/10 bg-zinc-950/25 p-4 backdrop-blur transition hover:bg-white/5">
@else
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 p-4 backdrop-blur">
@endif
        <div class="text-sm text-white/60">{{ $label }}</div>
        <div class="mt-2 text-3xl font-bold text-white">{{ $value }}</div>
        @isset($secondaryLabel)
            <div class="mt-3 text-xs font-semibold text-white/50">{{ $secondaryLabel }}</div>
        @endisset
        @isset($secondaryValue)
            <div class="mt-1 text-base font-semibold text-white">{{ $secondaryValue }}</div>
        @endisset
        @if($href)
            <div class="mt-4 inline-flex min-h-10 w-full items-center justify-between rounded-xl border border-white/10 bg-white/10 px-3 py-2 text-sm text-white/80">
                <span>Ver Lista</span>
                <span class="text-white/50">&rsaquo;</span>
            </div>
        @endif
@if($href)
    </a>
@else
    </div>
@endif

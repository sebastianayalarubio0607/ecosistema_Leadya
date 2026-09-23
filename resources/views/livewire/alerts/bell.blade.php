<div @if($available) wire:poll.60s="refreshCount" @endif>
    @if ($available)
        <a wire:navigate href="{{ route('alerts.index') }}" title="Mis alertas: {{ $count }} pendientes" aria-label="Mis alertas: {{ $count }} pendientes"
           class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-white/80 hover:bg-white/10">
            <span class="relative shrink-0 inline-flex items-center justify-center h-9 w-9 rounded-xl bg-white/10">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
                @if ($count)<span class="absolute -top-1 -right-2 rounded-full bg-amber-400 px-1.5 text-xs font-bold text-slate-950" aria-live="polite">{{ $count > 99 ? '99+' : $count }}</span>@endif
            </span>
            <span x-show="!collapsed" x-cloak>Mis alertas</span>
        </a>
    @endif
</div>

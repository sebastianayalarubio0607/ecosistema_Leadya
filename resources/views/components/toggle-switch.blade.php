@props([
    'label' => null,
    'description' => null,
])

<label class="flex items-start justify-between gap-4 rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white/80 transition hover:bg-white/[0.07]">
    <span class="space-y-1">
        @if($label)
            <span class="block text-sm font-medium text-white">{{ $label }}</span>
        @endif

        @if($description)
            <span class="block text-xs text-white/60">{{ $description }}</span>
        @endif

        @if(trim((string) $slot) !== '')
            <span class="block text-sm text-white/70">{{ $slot }}</span>
        @endif
    </span>

    <span class="relative inline-flex shrink-0 items-center">
        <input {{ $attributes->merge(['type' => 'checkbox', 'class' => 'peer sr-only']) }}>
        <span class="h-6 w-11 rounded-full border border-white/15 bg-slate-900/80 transition peer-checked:bg-emerald-500/80 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 peer-focus-visible:outline-none peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-300/70 peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-zinc-950"></span>
        <span class="pointer-events-none absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition peer-checked:translate-x-5 peer-disabled:opacity-70"></span>
    </span>
</label>

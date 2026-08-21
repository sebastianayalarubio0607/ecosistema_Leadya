@props(['index', 'label', 'align' => 'left'])

@php
    $alignClass = [
        'center' => 'text-center',
        'right' => 'text-right',
    ][$align] ?? 'text-left';

    $buttonClass = [
        'center' => 'mx-auto justify-center',
        'right' => 'ml-auto justify-end',
    ][$align] ?? 'justify-start';
@endphp

<th {{ $attributes->class([$alignClass, 'px-3 py-2 whitespace-nowrap']) }} aria-sort="none">
    <button type="button"
            class="inline-flex items-center gap-1 text-left hover:text-white {{ $buttonClass }}"
            data-sort-header
            data-column-index="{{ $index }}"
            data-sort-direction="none">
        <span>{{ $label }}</span>
        <span data-sort-icon class="text-[10px] uppercase text-white/40">sort</span>
    </button>
</th>

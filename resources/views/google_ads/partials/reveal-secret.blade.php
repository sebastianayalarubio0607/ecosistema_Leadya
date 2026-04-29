@php
    $elementId = 'secret_'.md5($credential->id.'_'.$field.'_'.uniqid('', true));
    $masked = $masked ?? $credential->masked($field);
@endphp

<div class="space-y-2">
    <div class="text-sm text-white/50">{{ $label }}</div>
    <div class="flex gap-2 items-start">
        <textarea id="{{ $elementId }}" rows="{{ $rows ?? 2 }}" readonly
                  class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white text-sm">{{ $masked }}</textarea>
        <button type="button"
                class="shrink-0 px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10"
                onclick="revealSecret{{ $elementId }}()">
            Ver
        </button>
    </div>
</div>

<script>
    function revealSecret{{ $elementId }}() {
        const field = @json($field);
        const masked = @json($masked);
        const input = document.getElementById(@json($elementId));
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        if (!input || !csrf) {
            return;
        }

        input.value = 'Cargando...';

        fetch(@json(route('google-ads.credentials.reveal-secret', $credential)), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ field }),
        })
        .then(response => response.json())
        .then(data => {
            input.value = data.value || masked;

            setTimeout(() => {
                input.value = masked;
            }, 5000);
        })
        .catch(() => {
            input.value = masked;
        });
    }
</script>

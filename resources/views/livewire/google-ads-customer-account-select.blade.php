<div class="space-y-3" wire:init="loadAccounts">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <label class="block text-white/70">Cuenta publicitaria Google Ads</label>
        <button type="button"
                wire:click="loadAccounts"
                wire:loading.attr="disabled"
                wire:target="loadAccounts"
                class="inline-flex w-fit items-center justify-center rounded-xl border border-white/10 bg-white/10 px-3 py-2 text-sm text-white hover:bg-white/15 disabled:opacity-60">
            <span wire:loading.remove wire:target="loadAccounts">Reconsultar cuentas</span>
            <span wire:loading wire:target="loadAccounts">Consultando...</span>
        </button>
    </div>

    @if($readonly)
        <div class="rounded-xl border border-white/10 bg-slate-900/60 p-3 text-sm text-white/80">
            <div class="grid gap-3 md:grid-cols-2">
                <div>
                    <div class="text-xs text-white/50">ID Google Ads</div>
                    <div class="mt-1 break-all font-mono text-white">{{ $selectedAccountId !== '' ? $selectedAccountId : '—' }}</div>
                </div>
                <div>
                    <div class="text-xs text-white/50">Nombre cuenta</div>
                    <div class="mt-1 break-words text-white">{{ $selectedAccount['descriptive_name'] ?? '—' }}</div>
                </div>
            </div>

            @if($selectedAccount)
                <div class="mt-3 flex flex-wrap gap-2 text-xs text-white/60">
                    @if(!empty($selectedAccount['currency_code']))
                        <span class="rounded-lg border border-white/10 bg-white/5 px-2 py-1">{{ $selectedAccount['currency_code'] }}</span>
                    @endif
                    @if(!empty($selectedAccount['time_zone']))
                        <span class="rounded-lg border border-white/10 bg-white/5 px-2 py-1">{{ $selectedAccount['time_zone'] }}</span>
                    @endif
                    @if(!empty($selectedAccount['manager']))
                        <span class="rounded-lg border border-amber-300/20 bg-amber-500/10 px-2 py-1 text-amber-200">MCC</span>
                    @endif
                </div>
            @endif
        </div>
    @elseif($hasLoadedAccounts)
        <select name="id_Gads"
                wire:model.live="selectedAccountId"
                class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white">
            <option value="">Sin cuenta Google Ads</option>
            @foreach($accounts as $account)
                <option value="{{ $account['id'] }}">
                    {{ $account['descriptive_name'] ?: 'Sin nombre' }} - {{ $account['id'] }}
                    @if(!empty($account['currency_code']))
                        ({{ $account['currency_code'] }})
                    @endif
                </option>
            @endforeach
        </select>
    @else
        <input name="id_Gads"
               wire:model.live="selectedAccountId"
               class="w-full rounded-xl border border-white/10 bg-slate-900/60 p-2 text-white placeholder-white/40"
               placeholder="Consulta cuentas o escribe el ID sin guiones. Ej: 1234567890" />
    @endif

    @if($selectedAccount)
        <div class="rounded-xl border border-white/10 bg-white/5 p-3 text-xs text-white/60">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <span class="block text-white/40">ID</span>
                    <span class="mt-1 block break-all font-mono text-white/80">{{ $selectedAccount['id'] }}</span>
                </div>
                <div>
                    <span class="block text-white/40">Nombre</span>
                    <span class="mt-1 block break-words text-white/80">{{ $selectedAccount['descriptive_name'] ?: 'Sin nombre' }}</span>
                </div>
                <div>
                    <span class="block text-white/40">Divisa</span>
                    <span class="mt-1 block text-white/80">{{ $selectedAccount['currency_code'] ?: '—' }}</span>
                </div>
                <div>
                    <span class="block text-white/40">Zona horaria</span>
                    <span class="mt-1 block break-words text-white/80">{{ $selectedAccount['time_zone'] ?: '—' }}</span>
                </div>
            </div>
        </div>
    @endif

    @if($loadedAt)
        <p class="text-xs text-emerald-300">Consulta completada: {{ $loadedAt }}</p>
    @endif

    @if($errorMessage)
        <p class="text-sm text-rose-300">{{ $errorMessage }}</p>
    @endif
</div>

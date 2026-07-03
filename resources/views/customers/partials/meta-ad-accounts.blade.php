@php
    $metaAdAccounts = $customer?->metaAdAccounts ?? collect();
    $newMetaAdAccount = old('new_meta_ad_account', []);
@endphp

<div>
    <div class="mb-2 flex items-center justify-between gap-3">
        <label class="block text-white/70">Meta Ad Accounts asociadas</label>

        @if(isset($customer) && $customer)
            <a href="{{ route('meta.ad-accounts.create', ['customer_id' => $customer->id]) }}"
               class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs text-white">
                + Nueva cuenta
            </a>
        @endif
    </div>

    <div class="rounded-2xl border border-white/10 bg-white/5 p-3 space-y-3">
        @if(isset($customer) && $customer)
            @forelse($metaAdAccounts as $account)
                <div class="rounded-xl border border-white/10 bg-slate-900/40 p-3">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div class="min-w-0">
                            <div class="font-semibold text-white break-all">
                                {{ $account->meta_account_id }}
                            </div>
                            <div class="text-sm text-white/60">
                                {{ $account->name ?: 'Sin nombre' }}
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="px-2 py-1 rounded-lg text-xs border {{ $account->status === 'active' ? 'bg-emerald-500/10 border-emerald-300/20 text-emerald-200' : 'bg-white/10 border-white/10 text-white/70' }}">
                                {{ $account->status }}
                            </span>

                            <a href="{{ route('meta.ad-accounts.edit', $account) }}"
                               class="px-3 py-1.5 rounded-lg bg-indigo-500/20 hover:bg-indigo-500/30 border border-white/10 text-xs text-white">
                                Editar
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-white/50">Sin Meta Ad Accounts asociadas.</p>
            @endforelse
        @endif

        <div class="rounded-xl border border-white/10 bg-slate-900/40 p-3 space-y-3">
            <div class="text-sm font-semibold text-white/80">Agregar Meta Ad Account</div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block mb-1 text-white/70">Meta Account ID</label>
                    <input name="new_meta_ad_account[meta_account_id]"
                           value="{{ $newMetaAdAccount['meta_account_id'] ?? '' }}"
                           class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40">
                    @error('new_meta_ad_account.meta_account_id') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block mb-1 text-white/70">Nombre</label>
                    <input name="new_meta_ad_account[name]"
                           value="{{ $newMetaAdAccount['name'] ?? '' }}"
                           class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40">
                    @error('new_meta_ad_account.name') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block mb-1 text-white/70">Estado</label>
                    @php($newStatus = $newMetaAdAccount['status'] ?? 'active')
                    <select name="new_meta_ad_account[status]"
                            class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                        <option value="active" @selected($newStatus === 'active')>active</option>
                        <option value="inactive" @selected($newStatus === 'inactive')>inactive</option>
                    </select>
                    @error('new_meta_ad_account.status') <p class="mt-1 text-sm text-rose-300">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
    </div>
</div>

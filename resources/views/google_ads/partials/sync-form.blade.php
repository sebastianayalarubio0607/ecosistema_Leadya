<div class="rounded-2xl border border-white/10 bg-white/5 p-4 space-y-3">
    <div class="text-white/80 font-medium">Sincronización manual</div>

    <form method="POST" action="{{ route('google-ads.sync.manual') }}" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
        @csrf

        <div class="md:col-span-4">
            <label class="block mb-1 text-white/70">Fecha</label>
            <input type="date"
                   name="report_date"
                   value="{{ old('report_date', $syncDate ?? now()->subDay()->toDateString()) }}"
                   class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white" />
        </div>

        <div class="md:col-span-6">
            <label class="block mb-1 text-white/70">Cliente</label>
            <select name="customer_id" class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white">
                <option value="">Todos los clientes con id_Gads</option>
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" @selected((string) old('customer_id', $syncCustomerId ?? request('customer_id')) === (string) $customer->id)>
                        {{ $customer->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="md:col-span-2">
            <button class="w-full px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">
                Sincronizar
            </button>
        </div>
    </form>
</div>

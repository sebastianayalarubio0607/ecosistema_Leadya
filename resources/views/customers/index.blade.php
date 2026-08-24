@extends('meta.layout')

@section('title', 'Customers')
@section('subtitle', 'Administración de clientes, páginas y cuentas Meta asociadas')

@section('header_actions')
    <a href="{{ route('customers.create') }}"
       class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">
        + Nuevo
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-4 space-y-4">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <div class="md:col-span-10">
                <label class="block mb-1 text-white/70">Buscar</label>
                <input name="q" value="{{ $q }}"
                       class="w-full rounded-xl border border-white/10 p-2 bg-slate-900/60 text-white placeholder-white/40"
                       placeholder="Buscar por nombre, pixel id, dataset CRM, Google Ads id, Meta Account o WABA..." />
            </div>

            <div class="md:col-span-2 flex gap-2">
                <button class="w-full px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
                    Buscar
                </button>
                <a href="{{ route('customers.index') }}"
                   class="w-full text-center px-4 py-2 rounded-xl bg-zinc-950/25 hover:bg-white/10 text-white border border-white/10">
                    Limpiar
                </a>
            </div>
        </form>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between text-sm text-white/60">
            <div>
                Mostrando {{ $customers->firstItem() ?? 0 }}-{{ $customers->lastItem() ?? 0 }} de {{ $customers->total() }} customers
            </div>
            <div>50 items por pagina</div>
        </div>

        <div class="w-full max-w-full overflow-x-auto rounded-xl border border-white/10 [scrollbar-gutter:stable]" data-sortable-table-wrap>
            <div class="hidden px-3 py-2 text-xs text-white/50" data-sort-status>Ordenando...</div>
            <table class="w-full min-w-[2500px] table-fixed text-sm" data-sortable-table>
                <thead class="bg-white/5 text-white/70">
                    <tr>
                        <x-sort-header :index="0" label="Nombre" class="w-48" />
                        <x-sort-header :index="1" label="Status" class="w-28" />
                        <x-sort-header :index="2" label="FB Pixel ID" class="w-44" />
                        <x-sort-header :index="3" label="Meta_dataset" class="w-32" />
                        <x-sort-header :index="4" label="Meta_dataset_id" class="w-48" />
                        <x-sort-header :index="5" label="Meta_dataset_token" class="w-72" />
                        <x-sort-header :index="6" label="WA Meta_dataset" class="w-36" />
                        <x-sort-header :index="7" label="WA Meta_dataset_id" class="w-52" />
                        <x-sort-header :index="8" label="WA Meta_dataset_token" class="w-72" />
                        <x-sort-header :index="9" label="FB Test Event Code" class="w-44" />
                        <x-sort-header :index="10" label="ID Google Ads" class="w-40" />
                        <x-sort-header :index="11" label="Divisa" class="w-24" />
                        <x-sort-header :index="12" label="Valor minimo" class="w-36" />
                        <x-sort-header :index="13" label="Meta Account ID" class="w-56" />
                        <x-sort-header :index="14" label="Meta WhatsApp" class="w-72" />
                        <x-sort-header :index="15" label="Meta Pages asociadas" class="w-72" />
                        <th class="w-72 text-left px-3 py-2 whitespace-nowrap">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10 text-white/80">
                    @forelse ($customers as $customer)
                        <tr class="align-top hover:bg-white/5">
                            <td class="px-3 py-2 font-medium text-white break-words">{{ $customer->name }}</td>
                            <td class="px-3 py-2">
                                <span class="px-2 py-1 rounded-lg text-xs border {{ (int) $customer->status === 1 ? 'bg-emerald-500/10 border-emerald-300/20 text-emerald-200' : 'bg-white/10 border-white/10 text-white/70' }}">
                                    {{ (int) $customer->status === 1 ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-3 py-2 break-all">{{ $customer->fb_pixel_id ?: '—' }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex px-2 py-1 rounded-lg text-xs border {{ (int) $customer->Meta_dataset === 1 ? 'bg-emerald-500/10 border-emerald-300/20 text-emerald-200' : 'bg-white/10 border-white/10 text-white/70' }}">
                                    {{ (int) $customer->Meta_dataset === 1 ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                <div class="text-xs break-all rounded-lg border border-white/10 bg-white/5 px-2 py-1">
                                    {{ $customer->Meta_dataset_id ?: '—' }}
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                <div class="text-xs break-all rounded-lg border border-white/10 bg-white/5 px-2 py-1">
                                    {{ $customer->Meta_dataset_token ?: '—' }}
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                <span class="inline-flex px-2 py-1 rounded-lg text-xs border {{ (int) $customer->Meta_whatsapp_dataset === 1 ? 'bg-emerald-500/10 border-emerald-300/20 text-emerald-200' : 'bg-white/10 border-white/10 text-white/70' }}">
                                    {{ (int) $customer->Meta_whatsapp_dataset === 1 ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                <div class="text-xs break-all rounded-lg border border-white/10 bg-white/5 px-2 py-1">
                                    {{ $customer->Meta_whatsapp_dataset_id ?: '—' }}
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                <div class="text-xs break-all rounded-lg border border-white/10 bg-white/5 px-2 py-1">
                                    {{ $customer->Meta_whatsapp_dataset_token ?: '—' }}
                                </div>
                            </td>
                            <td class="px-3 py-2">{{ $customer->fb_test_event_code ?: '—' }}</td>
                            <td class="px-3 py-2 break-all">{{ $customer->id_Gads ?: '—' }}</td>
                            <td class="px-3 py-2 font-mono">{{ $customer->defaultCurrency?->code ?? 'COP' }}</td>
                            <td class="px-3 py-2">{{ number_format((float) ($customer->default_lead_value ?? 100000), 2, '.', ',') }}</td>
                            <td class="px-3 py-2">
                                <div class="space-y-1">
                                    @forelse($customer->metaAdAccounts as $account)
                                        <div class="rounded-lg border border-white/10 bg-white/5 px-2 py-1">
                                            <div class="text-xs break-all font-mono text-white">{{ $account->meta_account_id }}</div>
                                            <div class="mt-1">
                                                <span class="inline-flex rounded-lg border px-2 py-0.5 text-[11px] font-semibold {{ $account->is_subscribed_to_meta_app ? 'bg-emerald-500/15 border-emerald-300/30 text-emerald-200' : 'bg-rose-500/15 border-rose-300/30 text-rose-200' }}">
                                                    Meta app: {{ $account->is_subscribed_to_meta_app ? 'Suscrita' : 'No suscrita' }}
                                                </span>
                                            </div>
                                        </div>
                                    @empty
                                        <span class="text-white/50">-</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                <div class="space-y-1">
                                    @forelse($customer->metaWhatsapps as $whatsapp)
                                        <div class="rounded-lg border border-white/10 bg-white/5 px-2 py-1">
                                            <div class="text-xs break-all font-mono text-white">{{ $whatsapp->waba_id }}</div>
                                            <div class="text-[11px] break-all text-white/50">Phone: {{ $whatsapp->phone_number_id ?: '-' }}</div>
                                            <div class="mt-1">
                                                <span class="inline-flex rounded-lg border px-2 py-0.5 text-[11px] font-semibold {{ $whatsapp->is_subscribed_to_meta_app ? 'bg-emerald-500/15 border-emerald-300/30 text-emerald-200' : 'bg-rose-500/15 border-rose-300/30 text-rose-200' }}">
                                                    Meta app: {{ $whatsapp->is_subscribed_to_meta_app ? 'Suscrita' : 'No suscrita' }}
                                                </span>
                                            </div>
                                        </div>
                                    @empty
                                        <span class="text-white/50">-</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                <div class="space-y-1">
                                    @forelse($customer->metaPages as $metaPage)
                                        <div class="rounded-lg border border-white/10 bg-white/5 px-2 py-1">
                                            <div class="text-xs break-all text-white">
                                                {{ $metaPage->name }} ({{ $metaPage->meta_page_id }})
                                            </div>
                                            <div class="mt-1">
                                                <span class="inline-flex rounded-lg border px-2 py-0.5 text-[11px] font-semibold {{ $metaPage->is_leadgen_subscribed ? 'bg-emerald-500/15 border-emerald-300/30 text-emerald-200' : 'bg-rose-500/15 border-rose-300/30 text-rose-200' }}">
                                                    Leadgen: {{ $metaPage->is_leadgen_subscribed ? 'Suscrita' : 'No suscrita' }}
                                                </span>
                                            </div>
                                        </div>
                                    @empty
                                        <span class="text-white/50">-</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-xs"
                                       href="{{ route('customers.show', $customer) }}">
                                        Ver
                                    </a>

                                    <a class="px-3 py-1.5 rounded-lg bg-indigo-500/20 hover:bg-indigo-500/30 border border-white/10 text-xs"
                                       href="{{ route('customers.edit', $customer) }}">
                                        Editar
                                    </a>

                                    <form method="POST"
                                          action="{{ route('customers.destroy', $customer) }}"
                                          onsubmit="return confirm('¿Seguro que deseas eliminar este customer?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="px-3 py-1.5 rounded-lg bg-rose-500/20 hover:bg-rose-500/30 border border-rose-300/20 text-xs" type="submit">
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-3 py-8 text-center text-white/60" colspan="17">No hay customers.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $customers->links() }}</div>
    </div>
@endsection

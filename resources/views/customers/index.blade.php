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
                       placeholder="Buscar por nombre, pixel id, dataset CRM, Google Ads id o Meta Account..." />
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

        <div class="w-full max-w-full overflow-x-auto rounded-xl border border-white/10 [scrollbar-gutter:stable]">
            <table class="w-full min-w-[1800px] table-fixed text-sm">
                <thead class="bg-white/5 text-white/70">
                    <tr>
                        <th class="w-48 text-left px-3 py-2 whitespace-nowrap">Nombre</th>
                        <th class="w-28 text-left px-3 py-2 whitespace-nowrap">Status</th>
                        <th class="w-44 text-left px-3 py-2 whitespace-nowrap">FB Pixel ID</th>
                        <th class="w-32 text-left px-3 py-2 whitespace-nowrap">Meta_dataset</th>
                        <th class="w-48 text-left px-3 py-2 whitespace-nowrap">Meta_dataset_id</th>
                        <th class="w-72 text-left px-3 py-2 whitespace-nowrap">Meta_dataset_token</th>
                        <th class="w-44 text-left px-3 py-2 whitespace-nowrap">FB Test Event Code</th>
                        <th class="w-40 text-left px-3 py-2 whitespace-nowrap">ID Google Ads</th>
                        <th class="w-24 text-left px-3 py-2 whitespace-nowrap">Divisa</th>
                        <th class="w-36 text-left px-3 py-2 whitespace-nowrap">Valor minimo</th>
                        <th class="w-56 text-left px-3 py-2 whitespace-nowrap">Meta Account ID</th>
                        <th class="w-72 text-left px-3 py-2 whitespace-nowrap">Meta Pages asociadas</th>
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
                            <td class="px-3 py-2">{{ $customer->fb_test_event_code ?: '—' }}</td>
                            <td class="px-3 py-2 break-all">{{ $customer->id_Gads ?: '—' }}</td>
                            <td class="px-3 py-2 font-mono">{{ $customer->defaultCurrency?->code ?? 'COP' }}</td>
                            <td class="px-3 py-2">{{ number_format((float) ($customer->default_lead_value ?? 100000), 2, '.', ',') }}</td>
                            <td class="px-3 py-2">
                                <div class="space-y-1">
                                    @forelse($customer->metaAdAccounts as $account)
                                        <div class="text-xs break-all rounded-lg border border-white/10 bg-white/5 px-2 py-1">
                                            {{ $account->meta_account_id }}
                                        </div>
                                    @empty
                                        <span class="text-white/50">-</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                <div class="space-y-1">
                                    @forelse($customer->metaPages as $metaPage)
                                        <div class="text-xs break-all rounded-lg border border-white/10 bg-white/5 px-2 py-1">
                                            {{ $metaPage->name }} ({{ $metaPage->meta_page_id }})
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
                            <td class="px-3 py-8 text-center text-white/60" colspan="13">No hay customers.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $customers->links() }}</div>
    </div>
@endsection

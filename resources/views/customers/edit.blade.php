@extends('meta.layout')

@section('title', 'Editar Customer')
@section('subtitle', 'Actualiza datos del cliente sin alterar su configuración funcional')

@section('header_actions')
    <a href="{{ route('customers.index') }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        Volver
    </a>
@endsection

@section('content')
    <div class="space-y-4">
        <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-6">
            <form method="POST" action="{{ route('customers.update', $customer) }}" class="space-y-4">
                @csrf
                @method('PUT')

                @include('customers.partials.form', ['customer' => $customer, 'metaPages' => $metaPages, 'selectedMetaPageIds' => $selectedMetaPageIds])

                <div class="rounded-2xl border border-white/10 bg-white/5 p-4 space-y-3 text-white/80">
                    <div>
                        <p class="text-sm text-white/50">Token actual</p>
                        <div class="mt-2 text-sm break-all rounded-xl border border-white/10 bg-slate-900/60 p-3 font-mono text-white/80">
                            {{ $customer->token }}
                        </div>
                    </div>

                    <x-toggle-switch name="regenerate_token" value="1" label="Regenerar token">
                        Genera un nuevo token al guardar manteniendo intacto el flujo actual.
                    </x-toggle-switch>

                    <div class="flex gap-2">
                        <button class="px-4 py-2 rounded-xl bg-indigo-500/30 hover:bg-indigo-500/40 text-white border border-white/10">Actualizar</button>
                        <a href="{{ route('customers.index') }}" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
                            Cancelar
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

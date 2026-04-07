@extends('meta.layout')

@section('title', 'Crear Customer')
@section('subtitle', 'Registro de clientes y configuración base')

@section('header_actions')
    <a href="{{ route('customers.index') }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        Volver
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 backdrop-blur p-6">
        <form method="POST" action="{{ route('customers.store') }}" class="space-y-4">
            @csrf

            @include('customers.partials.form', ['customer' => null, 'metaPages' => $metaPages, 'selectedMetaPageIds' => $selectedMetaPageIds])
        </form>
    </div>
@endsection

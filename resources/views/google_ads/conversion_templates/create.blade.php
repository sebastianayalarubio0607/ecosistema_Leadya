@extends('meta.layout')

@section('title', 'Crear plantilla Google Ads')
@section('subtitle', 'Plantilla independiente para crear conversion actions automaticamente')

@section('header_actions')
    <a href="{{ route('google-ads.conversion-templates.index') }}"
       class="rounded-xl border border-white/10 bg-white/10 px-4 py-2 text-white hover:bg-white/15">
        Volver
    </a>
@endsection

@section('content')
    <div class="rounded-2xl border border-white/10 bg-zinc-950/25 p-6 backdrop-blur">
        <form method="POST" action="{{ route('google-ads.conversion-templates.store') }}">
            @csrf

            @include('google_ads.conversion_templates._form', [
                'template' => $template,
                'categoryOptions' => $categoryOptions,
                'typeOptions' => $typeOptions,
                'statusOptions' => $statusOptions,
            ])
        </form>
    </div>
@endsection

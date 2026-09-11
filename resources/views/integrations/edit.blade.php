@extends('meta.layout')

@section('title', 'Editar Integración')
@section('subtitle', 'Ajusta la configuración visible sin alterar la lógica existente')

@section('header_actions')
    <a href="{{ route('integrations.index') }}"
       class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white border border-white/10">
        Volver
    </a>
@endsection

@section('content')
    <livewire:integrations.form
        :integration="$integration"
        :customer-options="$customers"
        :type-options="$types"
        :lead-field-options="$leadFields"
        :initial-kommo-conditions="$kommoPipelineConditions"
        :initial-atom-webhooks="$atomWebhooks"
        :initial-atom-conditions="$atomConditions"
        :initial-lety-webhooks="$letyWebhooks"
        :initial-lety-conditions="$letyConditions"
        :initial-freshworks-mappings="$freshworksVariableMappings"
        :initial-integration-mappings="$integrationVariableMappings"
        :initial-variables="$integrationVariables"
        :initial-variable-conditions="$integrationVariableConditions"
    />
@endsection

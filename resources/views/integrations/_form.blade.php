{{-- Compatibilidad con create/edit compilados antes de migrar a Livewire.
     El formulario antiguo permanece archivado; este archivo carga el vigente.
     Las vistas anteriores ya contienen el <form>, CSRF y el metodo HTTP. --}}
<livewire:integrations.form
    :integration="$integration"
    :customer-options="$customers"
    :type-options="$types"
    :lead-field-options="$leadFields ?? []"
    :initial-kommo-conditions="$kommoPipelineConditions ?? []"
    :initial-atom-webhooks="$atomWebhooks ?? []"
    :initial-atom-conditions="$atomConditions ?? []"
    :initial-lety-webhooks="$letyWebhooks ?? []"
    :initial-lety-conditions="$letyConditions ?? []"
    :initial-freshworks-mappings="$freshworksVariableMappings ?? []"
    :initial-integration-mappings="$integrationVariableMappings ?? []"
    :initial-variables="$integrationVariables ?? []"
    :initial-variable-conditions="$integrationVariableConditions ?? []"
    :embedded-in-legacy-form="true"
/>

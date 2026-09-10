<?php

namespace App\Livewire;

use Livewire\Component;

class IntegrationVariableConditionsForm extends Component
{
    public array $conditions = [];

    public array $leadFields = [];

    public array $variableNames = [];

    public array $sourceTypes = [
        'lead' => 'Lead',
        'variable' => 'Variable del body',
    ];

    public array $types = [
        'text' => 'Texto',
        'number' => 'Numero',
        'boolean' => 'Booleano',
        'binary' => 'Binario',
        'url' => 'URL',
        'json' => 'JSON',
        'any' => 'Cualquier',
    ];

    public array $operators = [
        'equals' => 'Igual a',
        'not_equals' => 'Distinto de',
        'greater_than' => 'Mayor que',
        'less_than' => 'Menor que',
        'greater_or_equal' => 'Mayor o igual',
        'less_or_equal' => 'Menor o igual',
        'and_logic' => 'Y logico',
        'or_logic' => 'O logico',
        'negation' => 'Negacion',
        'exists' => 'Existe',
        'not_exists' => 'No existe',
        'empty' => 'Esta vacio',
        'not_empty' => 'No esta vacio',
        'is' => 'Es',
        'not_is' => 'No es',
        'contains' => 'Contiene',
        'not_contains' => 'No contiene',
        'starts_with' => 'Empieza con',
        'not_starts_with' => 'No empieza con',
        'ends_with' => 'Termina con',
        'not_ends_with' => 'No termina con',
        'in_list' => 'Esta dentro de una lista',
        'not_in_list' => 'No esta dentro de una lista',
        'key_exists' => 'Existe una llave',
        'key_not_exists' => 'No existe una llave',
        'record_exists' => 'Existe un registro',
        'record_not_exists' => 'No existe un registro',
        'has_elements' => 'Hay elementos',
        'no_elements' => 'No hay elementos',
        'matches_pattern' => 'Coincide con un patron',
        'not_matches_pattern' => 'No coincide con un patron',
        'all_conditions' => 'Todas las condiciones se cumplen',
        'any_condition' => 'Alguna condicion se cumple',
        'no_conditions' => 'Ninguna condicion se cumple',
    ];

    public function mount(array $initialConditions = [], array $leadFields = [], array $variableNames = []): void
    {
        $this->leadFields = array_values(array_filter($leadFields, fn ($field) => (string) $field !== ''));
        $this->variableNames = array_values(array_unique(array_filter($variableNames, fn ($name) => (string) $name !== '')));

        $this->conditions = collect($initialConditions)
            ->map(fn ($condition, $index) => [
                'target_variable' => (string) ($condition['target_variable'] ?? ''),
                'source_type' => (string) ($condition['source_type'] ?? 'lead'),
                'source_key' => (string) ($condition['source_key'] ?? ''),
                'operator' => (string) ($condition['operator'] ?? 'equals'),
                'comparison_value' => (string) ($condition['comparison_value'] ?? ''),
                'result_value' => (string) ($condition['result_value'] ?? ''),
                'result_type' => (string) ($condition['result_type'] ?? 'text'),
                'order' => (int) ($condition['order'] ?? $index),
                'active' => (bool) ($condition['active'] ?? true),
                'key' => (string) ($condition['key'] ?? uniqid('condition_', true)),
            ])
            ->values()
            ->all();
    }

    public function addCondition(): void
    {
        $this->conditions[] = [
            'target_variable' => '',
            'source_type' => 'lead',
            'source_key' => '',
            'operator' => 'equals',
            'comparison_value' => '',
            'result_value' => '',
            'result_type' => 'text',
            'order' => count($this->conditions),
            'active' => true,
            'key' => uniqid('condition_', true),
        ];
    }

    public function removeCondition(int $index): void
    {
        unset($this->conditions[$index]);
        $this->conditions = array_values($this->conditions);
    }

    public function render()
    {
        return view('livewire.integration-variable-conditions-form');
    }
}

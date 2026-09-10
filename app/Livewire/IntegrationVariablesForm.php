<?php

namespace App\Livewire;

use Livewire\Component;

class IntegrationVariablesForm extends Component
{
    public array $variables = [];

    public array $types = [
        'text' => 'Texto',
        'number' => 'Numero',
        'boolean' => 'Booleano',
        'binary' => 'Binario',
        'url' => 'URL',
        'json' => 'JSON',
        'any' => 'Cualquier',
    ];

    public function mount(array $initialVariables = []): void
    {
        $this->variables = collect($initialVariables)
            ->map(fn ($variable, $index) => [
                'name' => (string) ($variable['name'] ?? ''),
                'value' => (string) ($variable['value'] ?? ''),
                'type' => (string) ($variable['type'] ?? 'text'),
                'order' => (int) ($variable['order'] ?? $index),
                'active' => (bool) ($variable['active'] ?? true),
                'key' => (string) ($variable['key'] ?? uniqid('var_', true)),
            ])
            ->values()
            ->all();
    }

    public function addVariable(): void
    {
        $this->variables[] = [
            'name' => '',
            'value' => '',
            'type' => 'text',
            'order' => count($this->variables),
            'active' => true,
            'key' => uniqid('var_', true),
        ];
    }

    public function removeVariable(int $index): void
    {
        unset($this->variables[$index]);
        $this->variables = array_values($this->variables);
    }

    public function render()
    {
        return view('livewire.integration-variables-form');
    }
}

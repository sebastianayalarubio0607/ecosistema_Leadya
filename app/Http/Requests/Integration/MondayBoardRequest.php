<?php

namespace App\Http\Requests\Integration;

use App\Models\Lead;
use App\Models\MondayBoardColumn;
use App\Models\MondayBoardGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MondayBoardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'boolean'],
            'condition_lead_field' => ['nullable', 'string', Rule::in(Lead::integrationMappableFields())],
            'condition_expected_value' => ['nullable', 'string', 'max:255'],
            'monday_group_id' => ['nullable', 'string', 'max:100'],
            'mappings' => ['nullable', 'array'],
            'mappings.*.column_id' => ['required_with:mappings', 'integer', 'exists:monday_board_columns,id'],
            'mappings.*.source_type' => ['nullable', 'string', Rule::in(['lead_field', 'fixed_value'])],
            'mappings.*.lead_field_name' => ['nullable', 'string', Rule::in(Lead::integrationMappableFields())],
            'mappings.*.static_value' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $board = $this->route('board');
            if (!$board) {
                return;
            }

            $status = $this->boolean('status');
            $mappings = collect($this->input('mappings', []));
            $hasMappedColumns = $mappings->contains(function ($mapping) {
                $sourceType = $mapping['source_type'] ?? 'lead_field';

                return ($sourceType === 'fixed_value' && filled($mapping['static_value'] ?? null))
                    || ($sourceType !== 'fixed_value' && filled($mapping['lead_field_name'] ?? null));
            });
            $hasSyncData = $board->details_synced_at !== null || $board->groups()->exists() || $board->columns()->exists();
            $isAttemptingConfiguration = filled($this->input('condition_lead_field'))
                || filled($this->input('condition_expected_value'))
                || filled($this->input('monday_group_id'))
                || $hasMappedColumns;

            foreach ($mappings as $index => $mapping) {
                $columnId = (int) ($mapping['column_id'] ?? 0);
                if ($columnId <= 0) {
                    continue;
                }

                $columnExists = MondayBoardColumn::query()
                    ->whereKey($columnId)
                    ->where('monday_board_id', $board->id)
                    ->exists();

                if (!$columnExists) {
                    $validator->errors()->add("mappings.{$index}.column_id", 'La columna seleccionada no pertenece a este board.');
                }
            }

            if (!$status) {
                return;
            }

            if (!($hasSyncData || $isAttemptingConfiguration)) {
                return;
            }

            if (blank($this->input('condition_lead_field'))) {
                $validator->errors()->add('condition_lead_field', 'Debes seleccionar el campo Lead que activa el board.');
            }

            if (blank($this->input('condition_expected_value'))) {
                $validator->errors()->add('condition_expected_value', 'Debes indicar el valor esperado para activar el board.');
            }

            if (blank($this->input('monday_group_id'))) {
                $validator->errors()->add('monday_group_id', 'Debes seleccionar el grupo Monday destino.');
            }

            if (filled($this->input('monday_group_id'))) {
                $groupExists = MondayBoardGroup::query()
                    ->where('monday_board_id', $board->id)
                    ->where('monday_group_id', $this->input('monday_group_id'))
                    ->exists();

                if (!$groupExists) {
                    $validator->errors()->add('monday_group_id', 'El grupo seleccionado no pertenece a este board.');
                }
            }
        });
    }
}

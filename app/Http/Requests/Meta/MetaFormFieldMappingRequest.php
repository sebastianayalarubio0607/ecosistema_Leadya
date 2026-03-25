<?php

namespace App\Http\Requests\Meta;

use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MetaFormFieldMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $mappingId = $this->route('mapping')?->id;

        return [
            'meta_form_id' => ['required', 'integer', 'exists:meta_forms,id'],
            'meta_field_name' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('meta_form_field_mappings', 'meta_field_name')
                    ->where(fn ($query) => $query->where('meta_form_id', $this->integer('meta_form_id')))
                    ->ignore($mappingId),
            ],
            'lead_field_name' => ['required', 'string', Rule::in(Lead::metaMappableFields())],
            'static_value' => ['nullable', 'string', 'max:255'],
            'is_required' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (blank($this->input('meta_field_name')) && blank($this->input('static_value'))) {
                $validator->errors()->add('meta_field_name', 'Debes indicar un campo Meta o un valor estático.');
            }
        });
    }
}

<?php

namespace App\Http\Requests\Meta;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MetaFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $formId = $this->route('form')?->id;

        return [
            'meta_page_id' => ['required', 'integer', 'exists:meta_pages,id'],
            'meta_form_id' => ['required', 'string', 'max:255', Rule::unique('meta_forms', 'meta_form_id')->ignore($formId)],
            'name' => ['required', 'string', 'max:255'],
            'locale' => ['nullable', 'string', 'max:50'],
            'status' => ['required', 'boolean'],
            'meta_status' => ['nullable', 'string', 'max:255'],
        ];
    }
}

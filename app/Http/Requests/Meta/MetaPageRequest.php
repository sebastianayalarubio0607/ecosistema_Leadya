<?php

namespace App\Http\Requests\Meta;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MetaPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $pageId = $this->route('page')?->id;

        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'meta_page_id' => ['required', 'string', 'max:255', Rule::unique('meta_pages', 'meta_page_id')->ignore($pageId)],
            'name' => ['required', 'string', 'max:255'],
            'page_access_token' => ['nullable', 'string'],
            'status' => ['required', 'boolean'],
        ];
    }
}

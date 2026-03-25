<?php

namespace App\Http\Requests\Meta;

use App\Models\MetaAccessToken;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MetaAccessTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $shortTokenRules = ['nullable', 'string'];

        if ($this->isMethod('post')) {
            $shortTokenRules = ['required', 'string'];
        }

        return [
            'token_type' => [
                'required',
                'string',
                Rule::in(MetaAccessToken::availableTypes()),
                Rule::unique('meta_access_tokens', 'token_type')->ignore($this->route('access_token')?->id),
            ],
            'short_lived_token' => $shortTokenRules,
            'meta_app_id' => ['nullable', 'string', 'max:255'],
            'meta_app_secret' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}

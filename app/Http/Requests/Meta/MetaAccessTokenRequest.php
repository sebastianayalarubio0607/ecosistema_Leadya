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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'purpose' => $this->input('purpose') ?: MetaAccessToken::PURPOSE_GENERAL,
            'customer_id' => $this->filled('customer_id') ? $this->input('customer_id') : null,
            'is_default' => $this->boolean('is_default'),
        ]);
    }

    public function rules(): array
    {
        $shortTokenRules = ['nullable', 'string'];
        $purpose = $this->input('purpose') ?: MetaAccessToken::PURPOSE_GENERAL;
        $tokenTypeRules = [
            'required',
            'string',
            Rule::in(MetaAccessToken::availableTypes()),
        ];

        if ($this->isMethod('post')) {
            $shortTokenRules = ['required', 'string'];
        }

        if ($purpose !== MetaAccessToken::PURPOSE_WHATSAPP) {
            $tokenTypeRules[] = Rule::unique('meta_access_tokens', 'token_type')
                ->where(function ($query) {
                    $query->whereNull('purpose')
                        ->orWhere('purpose', MetaAccessToken::PURPOSE_GENERAL);
                })
                ->ignore($this->route('access_token')?->id);
        }

        return [
            'name' => ['nullable', 'string', 'max:255'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'purpose' => ['required', 'string', Rule::in(MetaAccessToken::availablePurposes())],
            'token_type' => $tokenTypeRules,
            'short_lived_token' => $shortTokenRules,
            'meta_app_id' => ['nullable', 'string', 'max:255'],
            'meta_app_secret' => ['nullable', 'string', 'max:255'],
            'meta_business_id' => ['nullable', 'string', 'max:255'],
            'meta_system_user_id' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'is_default' => ['required', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $purpose = $this->input('purpose') ?: MetaAccessToken::PURPOSE_GENERAL;

            if ($purpose !== MetaAccessToken::PURPOSE_WHATSAPP) {
                if ($this->boolean('is_default')) {
                    $validator->errors()->add('is_default', 'El default solo aplica para tokens WhatsApp.');
                }

                return;
            }

            if ($this->input('token_type') !== MetaAccessToken::TYPE_SYSTEM_ACCESS_TOKEN) {
                $validator->errors()->add('token_type', 'Los tokens WhatsApp deben ser de tipo system_access_token.');
            }

            if (blank($this->input('meta_app_id'))) {
                $validator->errors()->add('meta_app_id', 'Los tokens WhatsApp necesitan Meta App ID para validar subscribed_apps.');
            }

            if ($this->boolean('is_default') && $this->filled('customer_id')) {
                $validator->errors()->add('customer_id', 'El usuario del sistema WhatsApp por defecto no debe estar asociado a un cliente especifico.');
            }

            if ($this->boolean('is_default') && ! $this->boolean('is_active')) {
                $validator->errors()->add('is_active', 'El usuario del sistema WhatsApp por defecto debe estar activo.');
            }
        });
    }
}

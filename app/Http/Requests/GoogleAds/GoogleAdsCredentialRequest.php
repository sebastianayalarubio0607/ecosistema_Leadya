<?php

namespace App\Http\Requests\GoogleAds;

use Illuminate\Foundation\Http\FormRequest;

class GoogleAdsCredentialRequest extends FormRequest
{
    protected ?int $credentialId = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $this->credentialId = $this->route('credential')?->id;
        $requiredOnCreate = $this->isMethod('post') ? ['required', 'string'] : ['nullable', 'string'];

        return [
            'mcc_developer_token' => $requiredOnCreate,
            'client_id' => $requiredOnCreate,
            'client_secret' => $requiredOnCreate,
            'refresh_token' => $requiredOnCreate,
            'access_token' => ['nullable', 'string'],
            'customer_id' => array_merge($requiredOnCreate, ['max:64']),
            'mcc_id' => array_merge($requiredOnCreate, ['max:64', 'regex:/^[0-9]+$/']),
            'access_token_expires_at' => ['nullable', 'date'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->isMethod('post') && \App\Models\GoogleAdsCredential::query()->exists()) {
                $validator->errors()->add('credential', 'Solo puede existir una credencial global de Google Ads.');
            }

            if (
                $this->boolean('is_active')
                && \App\Models\GoogleAdsCredential::query()
                    ->where('is_active', true)
                    ->when($this->credentialId, fn ($query) => $query->whereKeyNot($this->credentialId))
                    ->exists()
            ) {
                $validator->errors()->add('is_active', 'Ya existe una credencial global activa de Google Ads.');
            }
        });
    }
}

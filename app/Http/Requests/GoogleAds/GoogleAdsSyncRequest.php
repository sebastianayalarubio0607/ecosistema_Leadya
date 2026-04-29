<?php

namespace App\Http\Requests\GoogleAds;

use Illuminate\Foundation\Http\FormRequest;

class GoogleAdsSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'report_date' => ['required', 'date', 'before_or_equal:today'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
        ];
    }
}

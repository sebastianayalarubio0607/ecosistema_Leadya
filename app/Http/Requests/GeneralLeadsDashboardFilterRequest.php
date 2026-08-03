<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GeneralLeadsDashboardFilterRequest extends FormRequest
{
    private const SORTABLE = ['name', 'cost', 'impressions', 'conversions', 'roas', 'leads', 'qualified_leads', 'unqualified_leads', 'cpl'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'integration_id' => ['nullable', 'integer', 'exists:integrations,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:255'],
            'campaign_origin' => ['nullable', 'string', 'max:255'],
            'plataforma' => ['nullable', 'string', 'max:255'],
            'crm_state' => ['nullable', 'string', 'max:255'],
            'qualification' => ['nullable', 'integer', 'exists:qualification,id'],
            'lenguaje' => ['nullable', 'string', 'max:255'],
            'geo' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'array'],
            'sort.*' => ['nullable', 'string', 'in:'.implode(',', self::SORTABLE)],
            'dir' => ['nullable', 'array'],
            'dir.*' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $from = $this->date('from');
                $to = $this->date('to');

                if ($from && $to && $to->lt($from)) {
                    $validator->errors()->add('to', 'La fecha final no puede ser anterior a la fecha inicial.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['customer_id', 'integration_id', 'from', 'to', 'source', 'campaign_origin', 'plataforma', 'crm_state', 'qualification', 'lenguaje', 'geo'] as $key) {
            $value = $this->input($key);
            $this->merge([$key => is_string($value) && trim($value) === '' ? null : $value]);
        }
    }
}

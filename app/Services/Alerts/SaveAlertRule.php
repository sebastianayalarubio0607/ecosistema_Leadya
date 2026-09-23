<?php

namespace App\Services\Alerts;

use App\Models\Alerts\AlertRule;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveAlertRule
{
    public function save(array $input, ?int $id = null, ?int $version = null): void
    {
        Gate::authorize('manage-alerts');
        foreach (['subcategory_id', 'type_id', 'ends_at', 'starts_time', 'ends_time'] as $key) {
            $input[$key] = ($input[$key] ?? '') === '' ? null : $input[$key];
        }
        $data = Validator::make($input, [
            'name' => 'required|string|max:150', 'customer_ids' => 'required|array|min:1|max:100',
            'customer_ids.*' => 'required|integer|distinct|exists:customers,id',
            'user_ids' => 'required|array|min:1|max:200', 'user_ids.*' => 'integer|distinct|exists:users,id',
            'category_id' => ['required', 'integer', Rule::exists('alert_categories', 'id')->whereNotIn('code', ['publicidad', 'leads_quality'])],
            'subcategory_id' => ['nullable', 'integer', Rule::exists('alert_subcategories', 'id')->where('category_id', $input['category_id'] ?? 0)],
            'type_id' => ['nullable', 'integer', Rule::exists('alert_types', 'id')->where('subcategory_id', $input['subcategory_id'] ?? 0)],
            'enabled' => 'required|boolean', 'requires_response' => 'required|boolean',
            'timezone' => 'required|timezone', 'weekdays' => 'required|array|min:1|max:7',
            'weekdays.*' => 'integer|between:1,7|distinct',
            'starts_time' => 'nullable|required_with:ends_time|date_format:H:i',
            'ends_time' => 'nullable|required_with:starts_time|date_format:H:i|different:starts_time',
            'starts_at' => 'required|date_format:Y-m-d\TH:i',
            'ends_at' => 'nullable|date_format:Y-m-d\TH:i|after:starts_at',
            'max_notifications' => 'required|integer|between:1,100',
            'min_alerts' => 'required|integer|between:1,10000',
            'interval_minutes' => 'required|integer|between:1,525600',
            'severity' => ['required', Rule::in(['info', 'warning', 'critical'])],
            'channel' => ['required', Rule::in(['internal'])],
        ], [], ['customer_ids' => 'clientes', 'user_ids' => 'destinatarios', 'weekdays' => 'días'])->validate();

        $customerIds = array_map('intval', $data['customer_ids']);
        sort($customerIds);
        $users = $data['user_ids'];
        unset($data['customer_ids'], $data['user_ids']);
        $data['scope_key'] = $data['type_id'] ? 'type:'.$data['type_id'] : ($data['subcategory_id'] ? 'subcategory:'.$data['subcategory_id'] : 'category:'.$data['category_id']);
        $data['starts_at'] = Carbon::parse($data['starts_at'], $data['timezone'])->setTimezone(config('app.timezone'));
        $data['ends_at'] = $data['ends_at'] ? Carbon::parse($data['ends_at'], $data['timezone'])->setTimezone(config('app.timezone')) : null;
        $data['starts_time'] = $data['starts_time'] ? $data['starts_time'].':00' : null;
        $data['ends_time'] = $data['ends_time'] ? $data['ends_time'].':00' : null;

        DB::transaction(function () use ($data, $customerIds, $users, $id, $version) {
            Customer::whereIn('id', $customerIds)->orderBy('id')->lockForUpdate()->get();
            $existing = $id ? AlertRule::whereKey($id)->lockForUpdate()->firstOrFail() : null;
            if ($existing && ($customerIds !== [(int) $existing->customer_id] || (int) $existing->version !== $version)) {
                throw ValidationException::withMessages(['name' => 'La regla cambió. Vuelve a abrirla antes de guardar.']);
            }
            foreach ($customerIds as $customerId) {
                if (AlertRule::where('customer_id', $customerId)->where('scope_key', $data['scope_key'])->when($id, fn ($q) => $q->whereKeyNot($id))->exists()) {
                    throw ValidationException::withMessages(['customer_ids' => 'Ya existe una regla para uno de estos clientes y este alcance. Edita la regla existente.']);
                }
                $rule = $existing ?? new AlertRule;
                $rule->fill($data + ['customer_id' => $customerId]);
                $rule->version = $existing ? $existing->version + 1 : 1;
                $rule->save();
                $rule->users()->sync($users);
            }
        }, 3);
    }
}

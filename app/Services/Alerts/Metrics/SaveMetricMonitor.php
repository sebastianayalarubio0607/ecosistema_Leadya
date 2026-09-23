<?php

namespace App\Services\Alerts\Metrics;

use App\Models\Alerts\AlertMetricMonitor;
use App\Models\Alerts\AlertMetricRun;
use App\Models\Alerts\AlertMetricSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveMetricMonitor
{
    public static function defaults(): array
    {
        return [
            'platforms' => ['meta'], 'level' => 'campaign', 'minimum' => 1, 'window_hours' => 24, 'window_mode' => 'hours', 'lag_hours' => 3,
            'timezone' => 'America/Bogota', 'query_days' => [1, 2, 3, 4, 5, 6, 7], 'query_start' => '00:00', 'query_end' => '00:00',
            'query_interval' => 60, 'notify_days' => [1, 2, 3, 4, 5, 6, 7], 'notify_start' => '00:00', 'notify_end' => '00:00',
            'notify_interval' => 60, 'max_notifications' => 1, 'fresh_hours' => 24, 'requires_response' => false,
            'grouping' => 'separate', 'severity' => 'warning', 'warmup' => true, 'meta_ids' => [], 'google_ids' => [],
        ];
    }

    public function save(array $form, ?int $monitorId = null, ?int $subscriptionId = null, ?int $version = null): AlertMetricMonitor
    {
        Gate::authorize('manage-alerts');
        $rules = [
            'name' => ['required', 'string', 'max:150'], 'kind' => ['required', Rule::in(['impressions', 'leads'])],
            'customer_ids' => ['required', 'array', 'min:1', 'max:200'], 'customer_ids.*' => ['integer', 'distinct', 'exists:customers,id'],
            'user_ids' => ['required', 'array', 'min:1'], 'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'enabled' => ['required', 'boolean'], 'settings' => ['required', 'array'],
            'settings.platforms' => ['required', 'array', 'min:1', 'max:2'], 'settings.platforms.*' => [Rule::in(['meta', 'google']), 'distinct'],
            'settings.level' => ['required', Rule::in(['campaign', 'group', 'ad'])],
            'settings.window_mode' => ['required', Rule::in(['hours', 'days'])],
            'settings.timezone' => ['required', 'timezone'], 'settings.grouping' => ['required', Rule::in(['separate', 'together'])],
            'settings.severity' => ['required', Rule::in(['info', 'warning', 'critical'])],
            'settings.requires_response' => ['required', 'boolean'], 'settings.warmup' => ['required', 'boolean'],
        ];
        foreach (['minimum' => [1, 1000000000], 'window_hours' => [1, 720], 'lag_hours' => [0, 72], 'query_interval' => [5, 10080],
            'notify_interval' => [1, 10080], 'max_notifications' => [1, 100], 'fresh_hours' => [1, 168]] as $key => [$min, $max]) {
            $rules['settings.'.$key] = ['required', 'integer', 'min:'.$min, 'max:'.$max];
        }
        foreach (['query', 'notify'] as $phase) {
            $rules['settings.'.$phase.'_days'] = ['required', 'array', 'min:1', 'max:7'];
            $rules['settings.'.$phase.'_days.*'] = ['integer', 'between:1,7', 'distinct'];
            foreach (['start', 'end'] as $bound) {
                $rules['settings.'.$phase.'_'.$bound] = ['required', 'date_format:H:i'];
            }
        }
        foreach (['meta_ids', 'google_ids'] as $key) {
            $rules['settings.'.$key] = ['present', 'array', 'max:200'];
            $rules['settings.'.$key.'.*'] = ['string', 'regex:/^\d{1,30}$/', 'distinct'];
        }
        $data = Validator::make($form, $rules)->validate();
        $settings = array_intersect_key($data['settings'], self::defaults());
        foreach (['minimum', 'window_hours', 'lag_hours', 'query_interval', 'notify_interval', 'max_notifications', 'fresh_hours'] as $key) {
            $settings[$key] = (int) $settings[$key];
        }
        foreach (['warmup', 'requires_response'] as $key) {
            $settings[$key] = (bool) $settings[$key];
        }
        if ($data['kind'] === 'leads') {
            $settings['lag_hours'] = 0;
            $settings['grouping'] = 'separate';
        }
        if ($settings['window_mode'] === 'days' && $settings['window_hours'] % 24 !== 0) {
            throw ValidationException::withMessages(['settings.window_hours' => 'Para días completos usa 24, 48, 72 horas u otro múltiplo de 24.']);
        }
        if ($data['kind'] === 'impressions' && $settings['level'] === 'ad' && in_array('google', $settings['platforms'], true) && $settings['window_mode'] !== 'days') {
            throw ValidationException::withMessages(['settings.window_mode' => 'Google Ads permite medir anuncios por días completos. Selecciona ese periodo.']);
        }

        return DB::transaction(function () use ($data, $settings, $monitorId, $subscriptionId, $version) {
            $monitor = $monitorId ? AlertMetricMonitor::whereKey($monitorId)->lockForUpdate()->firstOrFail()
                : AlertMetricMonitor::create(['name' => $data['name'], 'kind' => $data['kind']]);
            if ($monitor->kind !== $data['kind']) {
                throw ValidationException::withMessages(['kind' => 'El tipo de una alerta existente no puede cambiarse.']);
            }
            foreach ($data['customer_ids'] as $customerId) {
                $s = $monitor->subscriptions()->where('customer_id', $customerId)->lockForUpdate()->first();
                if ($subscriptionId) {
                    if (! $s || $s->id !== $subscriptionId || $s->version !== $version || count($data['customer_ids']) !== 1) {
                        throw ValidationException::withMessages(['name' => 'La configuración cambió. Recarga antes de guardar.']);
                    }
                    foreach ($s->states()->get() as $state) {
                        app(MetricIncidentService::class)->close($state, 'superseded', now());
                    }
                    $s->states()->update(['first_seen_at' => now()]);
                } elseif ($s) {
                    throw ValidationException::withMessages(['customer_ids' => 'Este cliente ya está asociado. Edita su configuración.']);
                }
                $s ??= new AlertMetricSubscription(['monitor_id' => $monitor->id, 'customer_id' => $customerId, 'version' => 0]);
                $s->fill(['enabled' => $data['enabled'], 'settings' => $settings, 'version' => $s->version + 1,
                    'activated_at' => now(), 'next_query_at' => now(), 'last_checked_at' => null, 'last_status' => 'pending', 'last_error' => null])->save();
                $s->users()->sync($data['user_ids']);
                AlertMetricRun::create(['subscription_id' => $s->id, 'version' => $s->version, 'status' => 'configured',
                    'summary' => ['settings' => $settings, 'user_ids' => $data['user_ids'], 'changed_by' => auth()->id(), 'enabled' => $s->enabled], 'checked_at' => now()]);
            }

            return $monitor;
        }, 3);
    }
}

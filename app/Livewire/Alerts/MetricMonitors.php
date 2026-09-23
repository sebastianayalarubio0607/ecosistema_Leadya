<?php

namespace App\Livewire\Alerts;

use App\Models\Alerts\AlertMetricMonitor;
use App\Models\Alerts\AlertMetricRun;
use App\Models\Alerts\AlertMetricSubscription;
use App\Models\Customer;
use App\Models\User;
use App\Services\Alerts\Metrics\MetricAvailability;
use App\Services\Alerts\Metrics\SaveMetricMonitor;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class MetricMonitors extends Component
{
    use WithPagination;

    public bool $editing = false;

    #[Locked]
    public ?int $monitorId = null;

    #[Locked]
    public ?int $subscriptionId = null;

    #[Locked]
    public ?int $version = null;

    public array $form = [];

    public string $metaIds = '';

    public string $googleIds = '';

    public function create(?int $monitorId = null): void
    {
        Gate::authorize('manage-alerts');
        $monitor = $monitorId ? AlertMetricMonitor::findOrFail($monitorId) : null;
        $this->resetValidation();
        $this->monitorId = $monitorId;
        $this->subscriptionId = $this->version = null;
        $this->editing = true;
        $this->metaIds = $this->googleIds = '';
        $this->form = ['name' => $monitor?->name ?? 'Nueva alerta de volumen', 'kind' => $monitor?->kind ?? 'impressions',
            'customer_ids' => [], 'user_ids' => [auth()->id()], 'enabled' => true, 'settings' => SaveMetricMonitor::defaults()];
    }

    public function edit(int $id): void
    {
        Gate::authorize('manage-alerts');
        $s = AlertMetricSubscription::with(['monitor', 'users'])->findOrFail($id);
        abort_unless($s->customer_id, 404);
        $this->resetValidation();
        $this->monitorId = $s->monitor_id;
        $this->subscriptionId = $s->id;
        $this->version = $s->version;
        $this->editing = true;
        $this->form = ['name' => $s->monitor->name, 'kind' => $s->monitor->kind, 'customer_ids' => [$s->customer_id],
            'user_ids' => $s->users->pluck('id')->all(), 'enabled' => $s->enabled, 'settings' => array_replace(SaveMetricMonitor::defaults(), $s->settings)];
        $this->metaIds = implode(', ', $s->settings['meta_ids']);
        $this->googleIds = implode(', ', $s->settings['google_ids']);
    }

    public function save(): void
    {
        Gate::authorize('manage-alerts');
        $this->form['settings']['meta_ids'] = $this->ids($this->metaIds);
        $this->form['settings']['google_ids'] = $this->ids($this->googleIds);
        app(SaveMetricMonitor::class)->save($this->form, $this->monitorId, $this->subscriptionId, $this->version);
        $this->editing = false;
        session()->flash('metric-saved', 'Configuración guardada. Las consultas y avisos respetarán sus horarios.');
    }

    public function updated(string $property): void
    {
        if (str_starts_with($property, 'form.') && ($this->form['kind'] ?? '') === 'impressions'
            && ($this->form['settings']['level'] ?? '') === 'ad' && in_array('google', $this->form['settings']['platforms'] ?? [], true)) {
            $this->form['settings']['window_mode'] = 'days';
            $this->form['settings']['window_hours'] = max(24, (int) ceil(($this->form['settings']['window_hours'] ?? 24) / 24) * 24);
        }
    }

    private function ids(string $value): array
    {
        return preg_split('/[\s,;]+/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    public function render()
    {
        Gate::authorize('manage-alerts');
        abort_unless(app(MetricAvailability::class)->ready(), 503);

        return view('livewire.alerts.metric-monitors', [
            'monitors' => AlertMetricMonitor::with(['subscriptions.customer', 'subscriptions.users'])->latest('id')->paginate(10),
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'status']),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'runs' => $this->subscriptionId ? AlertMetricRun::where('subscription_id', $this->subscriptionId)->latest('id')->limit(5)->get() : collect(),
        ]);
    }
}

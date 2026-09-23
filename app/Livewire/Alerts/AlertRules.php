<?php

namespace App\Livewire\Alerts;

use App\Models\Alerts\AlertCategory;
use App\Models\Alerts\AlertRule;
use App\Models\Alerts\AlertSubcategory;
use App\Models\Alerts\AlertType;
use App\Models\Customer;
use App\Models\User;
use App\Services\Alerts\AlertAvailability;
use App\Services\Alerts\SaveAlertRule;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class AlertRules extends Component
{
    use WithPagination;

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?int $version = null;

    public bool $editing = false;

    public array $form = [];

    public function create(): void
    {
        Gate::authorize('manage-alerts');
        $this->resetValidation();
        $this->editingId = null;
        $this->version = null;
        $this->editing = true;
        $this->form = [
            'name' => 'Estado de cuentas publicitarias', 'customer_ids' => [], 'user_ids' => [],
            'category_id' => AlertCategory::where('code', 'cuentas_publicitarias')->value('id'),
            'subcategory_id' => AlertSubcategory::where('code', 'estados')->value('id'),
            'type_id' => AlertType::where('code', 'cuenta_publicitaria_inactiva')->value('id'),
            'enabled' => true, 'requires_response' => false, 'weekdays' => [1, 2, 3, 4, 5, 6, 7],
            'timezone' => 'America/Bogota', 'starts_time' => '', 'ends_time' => '',
            'starts_at' => now()->setTimezone('America/Bogota')->format('Y-m-d\TH:i'), 'ends_at' => '',
            'max_notifications' => 1, 'min_alerts' => 1, 'interval_minutes' => 60,
            'severity' => 'warning', 'channel' => 'internal',
        ];
    }

    public function edit(int $id): void
    {
        Gate::authorize('manage-alerts');
        $this->resetValidation();
        $rule = AlertRule::with('users')->findOrFail($id);
        $this->editingId = $id;
        $this->version = $rule->version;
        $this->editing = true;
        $this->form = $rule->only(['name', 'category_id', 'subcategory_id', 'type_id', 'enabled', 'requires_response', 'weekdays', 'timezone', 'max_notifications', 'min_alerts', 'interval_minutes', 'severity', 'channel']);
        $this->form += [
            'customer_ids' => [$rule->customer_id], 'user_ids' => $rule->users->pluck('id')->all(),
            'starts_time' => substr($rule->starts_time ?? '', 0, 5), 'ends_time' => substr($rule->ends_time ?? '', 0, 5),
            'starts_at' => $rule->starts_at->setTimezone($rule->timezone)->format('Y-m-d\TH:i'),
            'ends_at' => $rule->ends_at?->setTimezone($rule->timezone)->format('Y-m-d\TH:i') ?? '',
        ];
    }

    public function updatedFormCategoryId(): void
    {
        $this->form['subcategory_id'] = '';
        $this->form['type_id'] = '';
    }

    public function updatedFormSubcategoryId(): void
    {
        $this->form['type_id'] = '';
    }

    public function save(): void
    {
        Gate::authorize('manage-alerts');
        app(SaveAlertRule::class)->save($this->form, $this->editingId, $this->version);
        $this->editing = false;
        session()->flash('alert-success', 'Configuración guardada. Se evaluará automáticamente en el siguiente ciclo.');
    }

    public function render()
    {
        Gate::authorize('manage-alerts');
        abort_unless(app(AlertAvailability::class)->ready(), 503);

        return view('livewire.alerts.rules', [
            'items' => AlertRule::with(['customer', 'category', 'subcategory', 'type', 'users'])->latest('id')->paginate(15),
            'customers' => $this->editing ? Customer::orderBy('name')->get(['id', 'name']) : collect(),
            'users' => $this->editing ? User::orderBy('name')->get(['id', 'name']) : collect(),
            'categories' => AlertCategory::whereNotIn('code', ['publicidad', 'leads_quality'])->get(),
            'subcategories' => AlertSubcategory::where('category_id', $this->form['category_id'] ?? 0)->get(),
            'types' => AlertType::where('subcategory_id', $this->form['subcategory_id'] ?? 0)->get(),
        ]);
    }
}

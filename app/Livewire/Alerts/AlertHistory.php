<?php

namespace App\Livewire\Alerts;

use App\Models\Alerts\Alert;
use App\Models\Customer;
use App\Services\Alerts\AlertAvailability;
use App\Services\Alerts\AlertInteractionService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class AlertHistory extends Component
{
    use WithPagination;

    public string $customerId = '';

    public string $status = '';

    public string $reason = '';

    #[Locked]
    public ?int $selectedId = null;

    public function updatedCustomerId(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function close(): void
    {
        $this->selectedId = null;
    }

    public function open(int $id): void
    {
        Gate::authorize('manage-alerts');
        $this->selectedId = $id;
        $this->reason = '';
        $this->resetValidation();
    }

    public function changeStatus(string $status): void
    {
        Gate::authorize('manage-alerts');
        abort_unless($this->selectedId, 404);
        app(AlertInteractionService::class)->manage($this->selectedId, $status, $this->reason);
        $this->reason = '';
    }

    public function render()
    {
        Gate::authorize('manage-alerts');
        abort_unless(app(AlertAvailability::class)->ready(), 503);

        return view('livewire.alerts.history', [
            'items' => Alert::with('type.subcategory.category')->when($this->customerId, fn ($q) => $q->where('customer_id', $this->customerId))
                ->when($this->status, fn ($q) => $q->where('status', $this->status))->latest('id')->paginate(20),
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'selected' => $this->selectedId ? Alert::with(['events.user', 'recipients.user', 'recipients.comments', 'recipients.notifications'])->findOrFail($this->selectedId) : null,
        ]);
    }
}

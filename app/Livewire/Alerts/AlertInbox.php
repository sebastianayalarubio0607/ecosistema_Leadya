<?php

namespace App\Livewire\Alerts;

use App\Models\Alerts\AlertRecipient;
use App\Services\Alerts\AlertAvailability;
use App\Services\Alerts\AlertInteractionService;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class AlertInbox extends Component
{
    use WithPagination;

    public string $filter = 'pending';

    public string $comment = '';

    #[Locked]
    public ?int $selectedId = null;

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function open(int $id): void
    {
        $this->authorizeAccess();
        app(AlertInteractionService::class)->read($id, auth()->id());
        $this->selectedId = $id;
        $this->comment = '';
        $this->resetValidation();
        $this->dispatch('alerts-updated');
    }

    public function acknowledge(): void
    {
        $this->authorizeAccess();
        abort_unless($this->selectedId, 404);
        app(AlertInteractionService::class)->acknowledge($this->selectedId, auth()->id(), $this->comment);
        $this->comment = '';
        $this->dispatch('alerts-updated');
    }

    public function dismiss(int $id): void
    {
        $this->authorizeAccess();
        app(AlertInteractionService::class)->dismiss($id, auth()->id());
        $this->dispatch('alerts-updated');
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth()->check() && app(AlertAvailability::class)->ready(), 403);
    }

    public function render()
    {
        $this->authorizeAccess();
        $query = AlertRecipient::with(['alert.type.subcategory.category', 'latestNotification'])->where('user_id', auth()->id())->where('notification_count', '>', 0);
        $selected = $this->selectedId ? (clone $query)->with(['comments.user', 'alert.events'])->findOrFail($this->selectedId) : null;
        if ($this->filter === 'pending') {
            $query->pending();
        }
        if ($this->filter === 'unread') {
            $query->whereNull('read_at');
        }
        $items = $query->orderByDesc('last_notified_at')->orderByDesc('id')->paginate(15);

        return view('livewire.alerts.inbox', compact('items', 'selected'));
    }
}

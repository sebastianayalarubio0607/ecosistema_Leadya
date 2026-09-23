<?php

namespace App\Livewire\Alerts;

use App\Models\Alerts\AlertRecipient;
use App\Services\Alerts\AlertAvailability;
use Livewire\Attributes\On;
use Livewire\Component;

class AlertBell extends Component
{
    #[On('alerts-updated')]
    public function refreshCount(): void {}

    public function render()
    {
        $available = app(AlertAvailability::class)->ready();
        try {
            $count = $available && auth()->check()
                ? AlertRecipient::where('user_id', auth()->id())->where('notification_count', '>', 0)->pending()->count() : 0;
        } catch (\Throwable $e) {
            // A module deployment/failure must not break the application's navigation.
            report($e);
            $available = false;
            $count = 0;
        }

        return view('livewire.alerts.bell', compact('count', 'available'));
    }
}

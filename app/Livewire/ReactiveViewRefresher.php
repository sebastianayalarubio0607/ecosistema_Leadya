<?php

namespace App\Livewire;

use Livewire\Component;

class ReactiveViewRefresher extends Component
{
    public function tick(): void
    {
        $this->dispatch('reactive-view-refresh');
    }

    public function render()
    {
        return view('livewire.reactive-view-refresher');
    }
}

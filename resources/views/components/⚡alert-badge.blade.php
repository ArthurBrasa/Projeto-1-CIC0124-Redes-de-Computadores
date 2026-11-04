<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/* Number of active alerts next to ALERTS in the menu, updated live. */
new class extends Component
{
    public function getListeners()
    {
        return ['echo-private:App.Models.User.'.Auth::id().',.alert.changed' => '$refresh'];
    }

    #[Computed]
    public function count(): int
    {
        return Auth::user()->alerts()->where('status', 'active')->count();
    }
};
?>

<span class="count">@if ($this->count){{ $this->count }}@endif</span>

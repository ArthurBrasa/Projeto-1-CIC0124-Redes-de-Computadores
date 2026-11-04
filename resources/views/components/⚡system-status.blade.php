<?php

use App\Models\Alert;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/* Top bar chip: "SYSTEMS OK" or "N ALERTS", on every page. */
new class extends Component
{
    public function getListeners()
    {
        return ['echo-private:App.Models.User.'.Auth::id().',.alert.changed' => '$refresh'];
    }

    #[Computed]
    public function active(): int
    {
        return Auth::user()->alerts()->where('status', Alert::ACTIVE)->count();
    }
};
?>

<a class="chip hide-sm" href="{{ route('alerts.index') }}">
    @if ($this->active)
        <span class="st down"></span><b class="c-bad">{{ $this->active }} {{ $this->active === 1 ? 'ALERT' : 'ALERTS' }}</b>
    @else
        <span class="st up"></span><b>SYSTEMS OK</b>
    @endif
</a>

<?php

use App\Models\Alert;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/* Dialog at the top of the dashboard: one sentence about the current state. */
new class extends Component
{
    public function getListeners()
    {
        $ch = 'echo-private:App.Models.User.'.Auth::id();

        return [
            $ch.',.monitor.checked' => '$refresh',
            $ch.',.alert.changed' => '$refresh',
        ];
    }

    #[Computed]
    public function state(): array
    {
        $user = Auth::user();
        $monitors = $user->monitors()->where('is_active', true)->with('latestCheck')->get();
        $down = $monitors->filter(fn ($m) => $m->latestCheck && ! $m->latestCheck->is_up);

        // "down since": when its open downtime alert started, else its last check
        $first = $down->first();
        $since = null;
        if ($first) {
            $alert = $first->alerts()->open()->where('type', Alert::MONITOR_DOWN)->first();
            $since = ($alert?->started_at ?? $first->latestCheck->checked_at)->local()->format('H:i');
        }

        return [
            'name' => $user->firstName(),
            'monitors' => $monitors->count(),
            'up' => $monitors->count() - $down->count(),
            'down' => $down->pluck('name')->all(),
            'since' => $since,
            'apps' => $user->applications()->count(),
            'alerts' => $user->alerts()->where('status', Alert::ACTIVE)->count(),
        ];
    }
};
?>

<div class="box dialog mb">
    @php($s = $this->state)
    <p style="margin:0">
        @if ($s['monitors'] === 0 && $s['apps'] === 0)
            Welcome, {{ strtoupper($s['name']) }}! Start by adding a monitor or registering an application.
        @elseif (count($s['down']))
            Welcome back, {{ strtoupper($s['name']) }}! {{ $s['up'] }} of {{ $s['monitors'] }} monitors are up.
            <b>{{ strtoupper(implode(', ', $s['down'])) }}</b> {{ count($s['down']) === 1 ? 'is' : 'are' }} DOWN since {{ $s['since'] }}.
        @elseif ($s['monitors'] > 0)
            Welcome back, {{ strtoupper($s['name']) }}! All {{ $s['monitors'] }} {{ $s['monitors'] === 1 ? 'monitor is' : 'monitors are' }} up.
            @if ($s['alerts']) <b>{{ $s['alerts'] }} {{ $s['alerts'] === 1 ? 'alert needs' : 'alerts need' }} your attention.</b> @endif
        @else
            Welcome back, {{ strtoupper($s['name']) }}! Add a monitor to start tracking uptime.
        @endif
    </p>
</div>

<?php

use App\Models\Alert;
use App\Models\ApplicationEvent;
use App\Models\MonitorCheck;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/* STATUS box: the main numbers. */
new class extends Component
{
    public function getListeners()
    {
        $ch = 'echo-private:App.Models.User.'.Auth::id();

        return [
            $ch.',.monitor.checked' => '$refresh',
            $ch.',.application-event.received' => '$refresh',
            $ch.',.alert.changed' => '$refresh',
        ];
    }

    #[Computed]
    public function stats(): array
    {
        $user = Auth::user();
        $apps = $user->applications()->with('monitors.latestCheck')->withMax('events', 'created_at')->get();
        $appIds = $apps->pluck('id');
        $monitorIds = $user->monitors()->where('is_active', true)->pluck('id');

        $checks = MonitorCheck::whereIn('monitor_id', $monitorIds)->where('checked_at', '>=', now()->subDay());
        $total = (clone $checks)->count();
        $up = (clone $checks)->where('is_up', true)->count();

        return [
            'apps' => $appIds->count(),
            // linked monitor up, or (no monitor) an event in the last 10 minutes
            'online' => $apps->filter(fn ($a) => $a->liveStatus() === 'up')->count(),
            'monitors' => $monitorIds->count(),
            'uptime' => $total ? round($up / $total * 100, 2) : null,
            'events' => ApplicationEvent::whereIn('application_id', $appIds)->where('created_at', '>=', now()->subDay())->count(),
            'alerts' => $user->alerts()->where('status', Alert::ACTIVE)->count(),
        ];
    }

    /** 184302 -> 184k */
    public function short(int $n): string
    {
        return $n >= 1000000 ? round($n / 1000000, 1).'M' : ($n >= 10000 ? round($n / 1000).'k' : number_format($n));
    }
};
?>

<section class="box">
    @php($s = $this->stats)
    <div class="box-title"><h2>STATUS</h2></div>
    <dl class="stats">
        <div><dt>APPS</dt><dd>{{ $s['apps'] }}</dd></div>
        <div><dt title="Linked monitor is up, or an event arrived in the last 10 minutes">ONLINE</dt><dd @class(['c-up' => $s['online'] > 0])>{{ $s['online'] }}</dd></div>
        <div><dt>MONITORS</dt><dd>{{ $s['monitors'] }}</dd></div>
        <div><dt>UPTIME 24H</dt><dd>{{ $s['uptime'] !== null ? $s['uptime'].'%' : '—' }}</dd></div>
        <div><dt>EVENTS 24H</dt><dd>{{ $this->short($s['events']) }}</dd></div>
        <div><dt>ALERTS</dt><dd @class(['c-bad' => $s['alerts'] > 0])>{{ $s['alerts'] }}</dd></div>
    </dl>
</section>

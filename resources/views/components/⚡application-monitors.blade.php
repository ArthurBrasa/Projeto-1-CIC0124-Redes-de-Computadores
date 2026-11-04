<?php

use App\Models\Application;
use App\Models\MonitorCheck;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/* Monitors linked to this application, on the application page. */
new class extends Component
{
    public Application $application;

    public function getListeners()
    {
        return ['echo-private:App.Models.User.'.Auth::id().',.monitor.checked' => 'onCheck'];
    }

    public function onCheck(array $payload = [])
    {
        if (! $this->monitors->contains('id', $payload['monitor_id'] ?? null)) {
            $this->skipRender();
        }
    }

    #[Computed]
    public function monitors()
    {
        $monitors = $this->application->monitors()->with('latestCheck')->orderBy('name')->get();

        $uptime = MonitorCheck::whereIn('monitor_id', $monitors->pluck('id'))
            ->where('checked_at', '>=', now()->subDay())
            ->selectRaw('monitor_id, count(*) as total, sum(case when is_up then 1 else 0 end) as up')
            ->groupBy('monitor_id')
            ->get()
            ->keyBy('monitor_id');

        return $monitors->each(function ($m) use ($uptime) {
            $row = $uptime[$m->id] ?? null;
            $m->uptime24 = $row && $row->total ? round($row->up / $row->total * 100, 2) : null;
        });
    }
};
?>

<section class="box">
    <div class="box-title">
        <h2>MONITORS</h2>
        <a class="link" href="{{ route('monitors.create', ['application' => $application->public_id]) }}">+ ADD MONITOR</a>
    </div>

    @if ($this->monitors->isEmpty())
        <p class="muted" style="margin:0">
            No monitor linked. Without one, this app counts as online only while it sends events.
            Add a monitor, or link an existing one in its settings.
        </p>
    @else
        <table class="tbl">
            <tbody>
                @foreach ($this->monitors as $m)
                    <tr class="clickable" wire:key="am-{{ $m->id }}" onclick="location.href='{{ route('monitors.show', $m) }}'">
                        <td><span class="name">{{ $m->name }}</span><div class="small muted">{{ $m->method }} {{ $m->url }}</div></td>
                        <td>
                            @if (! $m->is_active)
                                <span class="st off">PAUSED</span>
                            @elseif (! $m->latestCheck)
                                <span class="st off">NO DATA</span>
                            @elseif ($m->latestCheck->is_up)
                                <span class="st up">UP</span>
                            @else
                                <span class="st down">DOWN</span>
                            @endif
                        </td>
                        <td class="num">{{ $m->uptime24 !== null ? $m->uptime24.'% 24h' : '—' }}</td>
                        <td class="num muted">{{ $m->latestCheck ? $m->latestCheck->checked_at->diffForHumans() : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</section>

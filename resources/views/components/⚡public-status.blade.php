<?php

use App\Models\Alert;
use App\Models\Monitor;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /** 'checks' = one block per check (last 60), 'days' = one block per day (last 90). */
    public string $range = 'checks';

    public function setRange(string $range)
    {
        $this->range = $range === 'days' ? 'days' : 'checks';
    }

    protected $listeners = [
        'echo:status,.monitor.checked' => '$refresh',
        'echo:status,.alert.changed' => '$refresh',
    ];

    #[Computed]
    public function monitors()
    {
        return Monitor::where('is_public', true)
            ->where('is_active', true)
            ->with('latestCheck')
            ->orderBy('name')
            ->get();
    }

    /** Downtime of public monitors: the last 5 "monitor down" alerts. */
    #[Computed]
    public function incidents()
    {
        return Alert::where('type', Alert::MONITOR_DOWN)
            ->where('alertable_type', (new Monitor)->getMorphClass())
            ->whereIn('alertable_id', $this->monitors->pluck('id'))
            ->with('alertable')
            ->latest('started_at')
            ->limit(5)
            ->get();
    }

    /** Names of public monitors whose last check failed. */
    #[Computed]
    public function down(): array
    {
        return $this->monitors
            ->filter(fn ($m) => $m->latestCheck && ! $m->latestCheck->is_up)
            ->pluck('name')
            ->all();
    }
};
?>

<div>
    <div class="box dialog mb">
        <p style="margin:0">
            @if ($this->monitors->isEmpty())
                No public services yet.
            @elseif (empty($this->down))
                All systems are running.
            @else
                <b>{{ strtoupper(implode(', ', $this->down)) }}</b> {{ count($this->down) === 1 ? 'is' : 'are' }} down. Our team is working on it.
            @endif
        </p>
    </div>

    <section class="box mb">
        <div class="box-title">
            <div class="row" style="gap:22px">
                <h2>SERVICES</h2>
                <div class="tabs">
                    <button @class(['active' => $range === 'checks']) wire:click="setRange('checks')">LAST CHECKS</button>
                    <button @class(['active' => $range === 'days']) wire:click="setRange('days')">90 DAYS</button>
                </div>
            </div>
            <div class="legend">
                <span><i style="background:var(--hp)"></i>UP</span>
                @if ($range === 'days')<span><i style="background:var(--warn)"></i>SLOW</span>@endif
                <span><i style="background:var(--bad)"></i>DOWN</span>
                <span><i style="background:var(--paper-3)"></i>NO DATA</span>
            </div>
        </div>

        @forelse ($this->monitors as $monitor)
            <div class="svc" wire:key="svc-{{ $monitor->id }}">
                <div class="row between">
                    <div class="px">{{ $monitor->name }}</div>
                    @if (! $monitor->latestCheck)
                        <span class="st off">NO DATA</span>
                    @elseif ($monitor->latestCheck->is_up)
                        <span class="st up">OPERATIONAL</span>
                    @else
                        <span class="st down">DOWN</span>
                    @endif
                </div>

                @if ($range === 'checks')
                    @php
                        $checks = $monitor->checks()->latest('checked_at')->limit(60)->get()->reverse();
                        $uptime = $checks->isEmpty() ? null : round($checks->where('is_up', true)->count() / $checks->count() * 100, 2);
                    @endphp
                    <div class="blocks sm" style="margin-top:10px">
                        @for ($i = $checks->count(); $i < 60; $i++)
                            <i class="none" data-t="NO DATA"></i>
                        @endfor
                        @foreach ($checks as $check)
                            <i class="{{ $check->is_up ? '' : 'down' }}"
                               data-t="{{ $check->checked_at->local()->format('H:i:s') }} {{ $check->is_up ? 'UP' : 'DOWN' }} {{ $check->response_ms }}ms"></i>
                        @endforeach
                    </div>
                    <div class="svc-scale">
                        <span>every {{ $monitor->intervalLabel() }}</span>
                        <span>{{ $uptime !== null ? $uptime.'% uptime' : 'no checks yet' }}</span>
                        <span>now</span>
                    </div>
                @else
                    @php($history = $monitor->history(90))
                    <div class="blocks sm" style="margin-top:10px">
                        @foreach ($history['days'] as $day)
                            <i class="{{ $day['state'] === 'up' ? '' : $day['state'] }}"
                               data-t="{{ $day['date']->format('M d') }} {{ ['up' => 'UP', 'degraded' => 'SLOW', 'down' => 'DOWN', 'none' => 'NO DATA'][$day['state']] }}"></i>
                        @endforeach
                    </div>
                    <div class="svc-scale">
                        <span>90 days ago</span>
                        <span>{{ $history['uptime'] !== null ? $history['uptime'].'% uptime' : 'no checks yet' }}</span>
                        <span>today</span>
                    </div>
                @endif
            </div>
        @empty
            <p class="muted" style="margin:0">Nothing to show here yet.</p>
        @endforelse
    </section>

    <section class="box">
        <div class="box-title"><h2>PAST INCIDENTS</h2></div>
        @forelse ($this->incidents as $incident)
            <div class="incident" wire:key="inc-{{ $incident->id }}">
                <div class="row between">
                    <span class="px">{{ $incident->alertable?->name }} unavailable</span>
                    @if ($incident->isOpen())
                        <span class="st down">ONGOING</span>
                    @else
                        <span class="st up">RESOLVED</span>
                    @endif
                </div>
                <div class="when">
                    {{ $incident->started_at->local()->format('M d, H:i') }}
                    · {{ $incident->isOpen() ? 'for' : 'lasted' }} {{ $incident->duration() }}
                </div>
            </div>
        @empty
            <p class="muted" style="margin:0">No incidents recorded.</p>
        @endforelse
    </section>
</div>

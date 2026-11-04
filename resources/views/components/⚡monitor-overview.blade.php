<?php

use App\Models\Monitor;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Monitor $monitor;

    /** @var array<int, int> response time of the last 60 checks, oldest first */
    public array $latency = [];

    public function mount()
    {
        $this->load();
    }

    public function getListeners()
    {
        return ['echo-private:App.Models.User.'.auth()->id().',.monitor.checked' => 'onCheck'];
    }

    public function onCheck(array $payload = [])
    {
        if (($payload['monitor_id'] ?? null) !== $this->monitor->id) {
            return $this->skipRender();
        }

        $this->load();
    }

    public function load()
    {
        unset($this->latest, $this->recent, $this->lastChecks, $this->stats, $this->history, $this->incidents);

        $this->latency = $this->monitor->checks()
            ->latest('checked_at')->limit(60)->pluck('response_ms')
            ->reverse()->map(fn ($ms) => (int) $ms)->values()->all();
    }

    #[Computed]
    public function latest()
    {
        return $this->monitor->checks()->latest('checked_at')->first();
    }

    #[Computed]
    public function recent()
    {
        return $this->monitor->checks()->latest('checked_at')->limit(10)->get();
    }

    /** Last 60 checks, oldest first, for the per-check bar. */
    #[Computed]
    public function lastChecks()
    {
        return $this->monitor->checks()->latest('checked_at')->limit(60)->get()->reverse()->values();
    }

    #[Computed]
    public function stats(): array
    {
        $month = $this->monitor->checks()->where('checked_at', '>=', now()->subDays(30));
        $total = (clone $month)->count();
        $up = (clone $month)->where('is_up', true)->count();

        $avg = $this->monitor->checks()
            ->where('checked_at', '>=', now()->subDay())
            ->where('is_up', true)
            ->avg('response_ms');

        return [
            'uptime' => $total ? round($up / $total * 100, 2) : null,
            'avg' => $avg ? (int) round($avg) : null,
            'total' => $total,
        ];
    }

    #[Computed]
    public function history(): array
    {
        return $this->monitor->history(90);
    }

    /** Periods of consecutive failed checks, newest first. */
    #[Computed]
    public function incidents(): array
    {
        $checks = $this->monitor->checks()->latest('checked_at')->limit(2000)->get()->reverse();
        $incidents = [];
        $current = null;

        foreach ($checks as $check) {
            if (! $check->is_up) {
                $current ??= ['start' => $check->checked_at, 'end' => null, 'cause' => $check->error ?? 'HTTP '.$check->status_code];
            } elseif ($current) {
                $current['end'] = $check->checked_at;
                $incidents[] = $current;
                $current = null;
            }
        }

        if ($current) {
            $incidents[] = $current; // still down
        }

        return array_slice(array_reverse($incidents), 0, 5);
    }
};
?>

<div class="stack">
    {{-- summary --}}
    <div class="box dialog">
        <p style="margin:0">
            @if (! $monitor->is_active)
                This monitor is paused. Press RESUME to start checking again.
            @elseif (! $this->latest)
                No checks yet. {{ strtoupper($monitor->name) }} will be checked every {{ $monitor->intervalLabel() }}.
            @elseif ($this->latest->is_up)
                {{ strtoupper($monitor->name) }} is up. Last check {{ $this->latest->checked_at->diffForHumans() }}.
            @else
                <b>{{ strtoupper($monitor->name) }} is DOWN.</b> {{ $this->latest->error ?? 'HTTP '.$this->latest->status_code }}
            @endif
        </p>
    </div>

    {{-- numbers --}}
    <section class="box">
        <dl class="kpis">
            <div>
                <dt>STATUS</dt>
                @if (! $this->latest)
                    <dd class="muted">—</dd>
                @elseif ($this->latest->is_up)
                    <dd class="c-up">UP</dd>
                @else
                    <dd class="c-bad">DOWN</dd>
                @endif
            </div>
            <div><dt>UPTIME 30D</dt><dd>{{ $this->stats['uptime'] !== null ? $this->stats['uptime'].'%' : '—' }}</dd></div>
            <div><dt>AVG RESPONSE 24H</dt><dd>{{ $this->stats['avg'] !== null ? $this->stats['avg'].'ms' : '—' }}</dd></div>
            <div><dt>LAST CHECK</dt><dd>{{ $this->latest?->checked_at->local()->format('H:i:s') ?? '—' }}</dd></div>
        </dl>
    </section>

    {{-- 90 day timeline --}}
    <section class="box">
        <div class="box-title">
            <h2>LAST 90 DAYS</h2>
            <div class="legend"><span><i style="background:var(--hp)"></i>UP</span><span><i style="background:var(--warn)"></i>SLOW</span><span><i style="background:var(--bad)"></i>DOWN</span><span><i style="background:var(--paper-3)"></i>NO DATA</span></div>
        </div>
        <div class="blocks">
            @foreach ($this->history['days'] as $day)
                <i class="{{ $day['state'] === 'up' ? '' : $day['state'] }}"
                   data-t="{{ $day['date']->format('M d') }} {{ ['up' => 'UP', 'degraded' => 'SLOW', 'down' => 'DOWN', 'none' => 'NO DATA'][$day['state']] }}"></i>
            @endforeach
        </div>
        <div class="row between mt"><span class="px muted">90 DAYS AGO</span><span class="px muted">TODAY</span></div>
    </section>

    {{-- one block per check --}}
    <section class="box">
        <div class="box-title"><h2>LAST 60 CHECKS</h2><span class="px muted">EVERY {{ strtoupper($monitor->intervalLabel()) }}</span></div>
        <div class="blocks">
            @for ($i = $this->lastChecks->count(); $i < 60; $i++)
                <i class="none" data-t="NO DATA"></i>
            @endfor
            @foreach ($this->lastChecks as $check)
                <i class="{{ $check->is_up ? '' : 'down' }}"
                   data-t="{{ $check->checked_at->local()->format('H:i:s') }} {{ $check->is_up ? 'UP' : 'DOWN' }} {{ $check->status_code ?? 'ERR' }} {{ $check->response_ms }}ms"></i>
            @endforeach
        </div>
        <div class="row between mt"><span class="px muted">OLDER</span><span class="px muted">NOW</span></div>
    </section>

    {{-- latency --}}
    <section class="box">
        <div class="box-title"><h2>LATENCY</h2><span class="px muted">LAST 60 CHECKS</span></div>
        @if (empty($latency))
            <p class="muted" style="margin:0">No data yet.</p>
        @endif
        <div class="chart" wire:ignore @if (empty($latency)) hidden @endif><canvas></canvas></div>
    </section>

    <div class="cols half">
        {{-- checks --}}
        <section class="box">
            <div class="box-title"><h2>CHECKS</h2><span class="px muted">{{ number_format($this->stats['total']) }} IN 30D</span></div>
            @if ($this->recent->isEmpty())
                <p class="muted" style="margin:0">No checks yet.</p>
            @else
                <table class="tbl">
                    <tbody>
                        @foreach ($this->recent as $check)
                            <tr wire:key="check-{{ $check->id }}">
                                <td>{{ $check->checked_at->local()->format('H:i:s') }}</td>
                                <td>
                                    @if ($check->is_up)
                                        <span class="st up">{{ $check->status_code }} OK</span>
                                    @else
                                        <span class="st down" title="{{ $check->error }}">{{ $check->status_code ?? 'ERROR' }}</span>
                                    @endif
                                </td>
                                <td class="num">{{ $check->response_ms }}ms</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        {{-- downtime --}}
        <section class="box">
            <div class="box-title"><h2>DOWNTIME</h2></div>
            @if (empty($this->incidents))
                <p class="muted" style="margin:0">No downtime recorded.</p>
            @else
                <table class="tbl">
                    <tbody>
                        @foreach ($this->incidents as $incident)
                            <tr>
                                <td>{{ $incident['start']->local()->format('M d, H:i') }}</td>
                                <td>
                                    @if ($incident['end'])
                                        {{ $incident['start']->diffForHumans($incident['end'], true) }}
                                    @else
                                        <span class="c-bad">ongoing</span>
                                    @endif
                                </td>
                                <td class="small muted" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $incident['cause'] }}">{{ $incident['cause'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </div>
</div>

@script
<script>
    const canvas = $wire.$el.querySelector('canvas');
    const draw = () => {
        if (!$wire.latency.length) return;
        canvas.parentElement.hidden = false;
        const max = Math.max(500, ...$wire.latency);
        PM.lineChart(canvas, [{ data: $wire.latency, color: PM.C.hp, fill: true }], {
            unit: 'ms',
            threshold: 500,
            max: Math.ceil(max * 1.15 / 100) * 100,
        });
    };
    draw();
    $wire.$watch('latency', draw);
    window.addEventListener('resize', draw);
</script>
@endscript

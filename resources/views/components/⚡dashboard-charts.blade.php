<?php

use App\Models\ApplicationEvent;
use App\Models\MonitorCheck;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/* Chart box with 3 tabs: response time and uptime per hour (24h), events per minute (60 min). */
new class extends Component
{
    /** @var array<int, int|null> avg response ms per hour, oldest first */
    public array $resp = [];

    /** @var array<int, float|null> % of checks that passed per hour */
    public array $uptime = [];

    /** @var array<int, int> events per minute */
    public array $epm = [];

    /** @var array<int, string> x axis labels for the hourly charts */
    public array $hours = [];

    public int $limit = 500;

    public function mount()
    {
        $this->limit = Auth::user()->alertRule->latency_limit_ms;
        $this->loadChecks();
        $this->loadEvents();
    }

    public function getListeners()
    {
        $ch = 'echo-private:App.Models.User.'.Auth::id();

        return [
            $ch.',.monitor.checked' => 'loadChecks',
            $ch.',.application-event.received' => 'loadEvents',
        ];
    }

    public function loadChecks()
    {
        $from = now()->subHours(23)->startOfHour();
        $ids = Auth::user()->monitors()->where('is_active', true)->pluck('id');

        $sum = array_fill(0, 24, 0);
        $upCount = array_fill(0, 24, 0);
        $total = array_fill(0, 24, 0);

        MonitorCheck::whereIn('monitor_id', $ids)
            ->where('checked_at', '>=', $from)
            ->get(['is_up', 'response_ms', 'checked_at'])
            ->each(function ($c) use ($from, &$sum, &$upCount, &$total) {
                $i = (int) $from->diffInHours(Carbon::parse($c->checked_at)->startOfHour());
                if ($i < 0 || $i > 23) {
                    return;
                }
                $total[$i]++;
                if ($c->is_up) {
                    $upCount[$i]++;
                    $sum[$i] += $c->response_ms;
                }
            });

        $this->resp = array_map(fn ($i) => $upCount[$i] ? (int) round($sum[$i] / $upCount[$i]) : null, range(0, 23));
        $this->uptime = array_map(fn ($i) => $total[$i] ? round($upCount[$i] / $total[$i] * 100, 2) : null, range(0, 23));

        $local = $from->local();
        $this->hours = [
            $local->format('H').'h',
            $local->addHours(6)->format('H').'h',
            $local->addHours(12)->format('H').'h',
            $local->addHours(18)->format('H').'h',
            'now',
        ];
    }

    public function loadEvents()
    {
        $from = now()->subMinutes(59)->startOfMinute();
        $buckets = array_fill(0, 60, 0);

        ApplicationEvent::whereIn('application_id', Auth::user()->applications()->pluck('id'))
            ->where('created_at', '>=', $from)
            ->pluck('created_at')
            ->each(function ($t) use ($from, &$buckets) {
                $i = (int) $from->diffInMinutes(Carbon::parse($t)->startOfMinute());
                if ($i >= 0 && $i < 60) {
                    $buckets[$i]++;
                }
            });

        $this->epm = $buckets;
    }
};
?>

<section class="box">
    <div wire:ignore>
        <div class="box-title">
            <div class="tabs" data-tabs>
                <button class="active" data-pane="dash-resp">RESPONSE</button>
                <button data-pane="dash-up">UPTIME</button>
                <button data-pane="dash-epm">EVENTS/MIN</button>
            </div>
            <span class="px muted" data-range>24H</span>
        </div>
        <div id="dash-resp" class="chart"><canvas></canvas></div>
        <div id="dash-up" class="chart" hidden><canvas></canvas></div>
        <div id="dash-epm" class="chart" hidden><canvas></canvas></div>
    </div>
</section>

@script
<script>
    const root = $wire.$el;
    const canvas = id => root.querySelector('#' + id + ' canvas');

    // hours without checks: repeat the previous value so the line stays continuous
    const fill = arr => { let last = arr.find(v => v !== null) ?? null; return arr.map(v => v === null ? last : (last = v)); };

    const empty = (c, text) => {
        const r = c.parentElement.getBoundingClientRect();
        if (!r.width) return;
        c.width = r.width; c.height = r.height;
        const ctx = c.getContext('2d');
        ctx.clearRect(0, 0, r.width, r.height);
        ctx.fillStyle = PM.C.stone2; ctx.font = '8px "Press Start 2P"'; ctx.textAlign = 'center';
        ctx.fillText(text, r.width / 2, r.height / 2);
    };

    const draw = () => {
        const resp = fill($wire.resp), up = fill($wire.uptime), labels = $wire.hours;

        if (resp[0] === null) empty(canvas('dash-resp'), 'NO CHECKS YET');
        else {
            // scale follows the data; the latency limit line only shows when it fits (up to 2x the slowest hour)
            const peak = Math.max(...resp);
            const showLimit = $wire.limit <= peak * 2;
            const max = Math.ceil(Math.max(peak, showLimit ? $wire.limit : 0) * 1.2 / 50) * 50 || 50;
            PM.lineChart(canvas('dash-resp'), [{ data: resp, color: PM.C.hp, fill: true }], {
                unit: 'ms', xlabels: labels, max, threshold: showLimit ? $wire.limit : null,
            });
        }

        if (up[0] === null) empty(canvas('dash-up'), 'NO CHECKS YET');
        else PM.lineChart(canvas('dash-up'), [{ data: up, color: PM.C.hp, fill: true }], {
            unit: '%', xlabels: labels, min: Math.max(0, Math.floor(Math.min(...up)) - 1), max: 100,
        });

        if (!$wire.epm.some(v => v > 0)) empty(canvas('dash-epm'), 'NO EVENTS IN THE LAST HOUR');
        else PM.barChart(canvas('dash-epm'), $wire.epm, {
            xlabels: ['-60m', '-45m', '-30m', '-15m', 'now'], colorFn: v => v > 100 ? PM.C.warn : PM.C.info,
        });
    };

    // the range label follows the open tab
    root.querySelector('[data-tabs]').addEventListener('click', e => {
        const pane = e.target.closest('[data-pane]')?.dataset.pane;
        if (pane) root.querySelector('[data-range]').textContent = pane === 'dash-epm' ? '60 MIN' : '24H';
    });

    draw();
    $wire.$watch('resp', draw);
    $wire.$watch('uptime', draw);
    $wire.$watch('epm', draw);
    window.addEventListener('resize', draw);
</script>
@endscript

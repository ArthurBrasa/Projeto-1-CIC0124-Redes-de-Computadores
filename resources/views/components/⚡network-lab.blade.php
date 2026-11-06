<?php

use App\Jobs\RunNetworkExperiment;
use App\Models\NetworkExperiment;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public const SIZES = ['target' => 'Target URL as is', '1024' => '1 KB', '10240' => '10 KB', '102400' => '100 KB', '1048576' => '1 MB', 'all' => 'All sizes (1 KB → 1 MB)'];

    public string $target = '';
    public int|string $samples = 30;
    public int|string $clients = 5;
    public int|string $duration = 10;
    public string $size = 'all';
    public string $levels = '1,2,5';

    /** Chart series of the latest experiment of each type (watched by the charts). */
    public array $rttSeries = [];
    public array $thrSeries = [];
    public array $conSeries = [];

    public function mount()
    {
        $user = Auth::user();
        $this->target = $user->monitors()->value('url') ?? $user->applications()->value('url') ?? url('/up');
        $this->loadSeries();
    }

    public function getListeners()
    {
        return ['echo-private:App.Models.User.'.Auth::id().',.experiment.updated' => 'loadSeries'];
    }

    public function start(string $type)
    {
        abort_unless(in_array($type, NetworkExperiment::TYPES), 404);

        $this->validate([
            'target' => 'required|url|max:255',
            'samples' => 'required|integer|between:30,500',
            'clients' => 'required|integer|between:1,100',
            'duration' => 'required|integer|between:3,30',
            'size' => 'required|in:'.implode(',', array_keys(self::SIZES)),
            'levels' => ['required', 'regex:/^\s*\d+(\s*,\s*\d+){0,5}\s*$/'],
        ], ['levels.regex' => 'Use up to 6 numbers separated by commas, like 1,2,5.', 'samples.between' => 'Use at least 30 samples per condition.']);

        $levels = collect(explode(',', $this->levels))->map(fn ($v) => (int) trim($v))->filter(fn ($v) => $v >= 1 && $v <= 100)->unique()->sort()->values()->all();
        if ($type === NetworkExperiment::CONCURRENCY && ! $levels) {
            $this->addError('levels', 'Levels must be between 1 and 100.');

            return;
        }

        if ($this->running) {
            $this->js("PM.toast('Wait for the current test to finish.')");

            return;
        }

        $sizes = match ($this->size) {
            'target' => [null],
            'all' => [1024, 10240, 102400, 1048576],
            default => [(int) $this->size],
        };

        $params = match ($type) {
            NetworkExperiment::RTT => ['samples' => (int) $this->samples],
            NetworkExperiment::THROUGHPUT => ['clients' => (int) $this->clients, 'duration' => (int) $this->duration, 'sizes' => $sizes],
            NetworkExperiment::CONCURRENCY => ['levels' => $levels, 'duration' => (int) $this->duration],
        };

        $experiment = Auth::user()->networkExperiments()->create([
            'type' => $type,
            'target_url' => $this->target,
            'params' => $params,
            'status' => 'queued',
        ]);

        RunNetworkExperiment::dispatch($experiment);

        unset($this->running, $this->latest, $this->history);
        $this->js("PM.toast('Experiment #{$experiment->id} queued.')");
    }

    #[Computed]
    public function running(): ?NetworkExperiment
    {
        return Auth::user()->networkExperiments()->whereIn('status', ['queued', 'running'])->latest('id')->first();
    }

    /** Latest (running or finished) experiment of each type. */
    #[Computed]
    public function latest(): array
    {
        $out = [];
        foreach (NetworkExperiment::TYPES as $type) {
            $out[$type] = Auth::user()->networkExperiments()
                ->where('type', $type)->whereIn('status', ['running', 'done'])
                ->latest('id')->first();
        }

        return $out;
    }

    #[Computed]
    public function history()
    {
        return Auth::user()->networkExperiments()->latest('id')->limit(15)->get();
    }

    public function loadSeries()
    {
        unset($this->running, $this->latest, $this->history);

        $rtt = $this->latest['rtt']?->samples ?? [];
        $this->rttSeries = [
            'rtt' => array_map(fn ($s) => $s['ok'] ? $s['rtt'] : null, $rtt),
            'total' => array_map(fn ($s) => $s['ok'] ? $s['total'] : null, $rtt),
        ];
        $this->thrSeries = array_map(fn ($s) => array_diff_key($s, ['per_second' => 1]), $this->latest['throughput']?->samples['steps'] ?? []);
        $this->conSeries = array_map(fn ($s) => array_diff_key($s, ['per_second' => 1]), $this->latest['concurrency']?->samples['steps'] ?? []);
    }

    /** Summary of every experiment, one row each. */
    public function export()
    {
        $rows = Auth::user()->networkExperiments()->latest('id')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'date', 'type', 'target', 'params', 'status', 'result', 'results_json']);
            foreach ($rows as $e) {
                fputcsv($out, [$e->id, $e->created_at->local()->format('Y-m-d H:i:s'), $e->type, $e->target_url, $e->paramsLabel(), $e->status, $e->resultLabel(), json_encode($e->results)]);
            }
            fclose($out);
        }, 'network-lab-'.now()->local()->format('Ymd-His').'.csv');
    }

    /** Raw samples / steps of one experiment, for charts in the report. */
    public function exportSamples(int $id)
    {
        $e = Auth::user()->networkExperiments()->findOrFail($id);

        return response()->streamDownload(function () use ($e) {
            $out = fopen('php://output', 'w');
            foreach ($e->csvRows() as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'experiment-'.$e->id.'-'.$e->type.'.csv');
    }
};
?>

<div>
    @php
        $rtt = $this->latest['rtt']?->results ?? [];
        $thr = $this->latest['throughput']?->results ?? [];
        $con = $this->latest['concurrency']?->results ?? [];
        $run = $this->running;
        $names = ['rtt' => 'RTT', 'throughput' => 'THROUGHPUT', 'concurrency' => 'CONCURRENCY'];
        $bytes = fn ($b) => \App\Models\NetworkExperiment::bytes($b ?? 0);
    @endphp

    <div class="head">
        <div>
            <h1>NETWORK LAB</h1>
            <p class="sub">Measure RTT, throughput and concurrency against any URL.</p>
        </div>
    </div>

    {{-- controls --}}
    <section class="box mb">
        <div class="field">
            <label for="target">TARGET URL</label>
            <input class="input" id="target" type="url" wire:model="target" placeholder="https://api.example.com/health">
            @error('target') <div class="error">{{ $message }}</div> @enderror
        </div>
        <div class="fields-2" style="grid-template-columns:repeat(3,1fr)">
            <div class="field">
                <label for="samples">RTT SAMPLES</label>
                <input class="input" id="samples" type="number" min="30" max="500" wire:model="samples">
                @error('samples') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="clients">THROUGHPUT CLIENTS</label>
                <input class="input" id="clients" type="number" min="1" max="100" wire:model="clients">
                @error('clients') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="duration">SECONDS PER STEP</label>
                <input class="input" id="duration" type="number" min="3" max="30" wire:model="duration">
                @error('duration') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field" style="grid-column:span 2">
                <label for="size">THROUGHPUT PAYLOAD</label>
                <div wire:ignore>
                    <select class="input" id="size" wire:model="size">
                        @foreach (['target' => 'Target URL as is', '1024' => '1 KB', '10240' => '10 KB', '102400' => '100 KB', '1048576' => '1 MB', 'all' => 'All sizes (1 KB → 1 MB)'] as $value => $label)
                            <option value="{{ $value }}" @selected($size === (string) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="hint">Sizes use /api/v1/lab/payload on the target's host, so the target must run PULSEMONITOR.</div>
                @error('size') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="levels">CONCURRENCY LEVELS</label>
                <input class="input" id="levels" wire:model="levels" placeholder="1,2,5">
                @error('levels') <div class="error">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="row">
            <button class="btn primary" wire:click="start('rtt')" @disabled($run)>START RTT TEST</button>
            <button class="btn primary" wire:click="start('throughput')" @disabled($run)>START THROUGHPUT TEST</button>
            <button class="btn primary" wire:click="start('concurrency')" @disabled($run)>START CONCURRENCY TEST</button>
        </div>

        @if ($run)
            <div class="mt">
                <div class="row between">
                    <span class="px">
                        {{ $names[$run->type] }} TEST #{{ $run->id }}
                        {{ $run->status === 'queued' ? 'WAITING FOR THE QUEUE...' : 'RUNNING...' }}
                    </span>
                    <span class="px">{{ $run->progress }}%</span>
                </div>
                <div class="hp mt"><span>EXP</span><div class="bar"><i style="width:{{ $run->progress }}%;background:var(--info)"></i></div></div>
                @if ($run->status === 'queued')
                    <p class="small muted" style="margin:8px 0 0">Tests run on the queue worker. If it stays here, check that the queue worker is running.</p>
                @endif
            </div>
        @endif
    </section>

    {{-- numbers --}}
    <section class="box mb">
        <dl class="kpis c3">
            <div><dt>RTT MEAN · MEDIAN</dt><dd>{{ isset($rtt['rtt_avg']) ? $rtt['rtt_avg'].' · '.($rtt['rtt_median'] ?? '—').'ms' : '—' }}</dd></div>
            <div><dt>RTT STD DEV · P95</dt><dd>{{ isset($rtt['rtt_stddev']) ? $rtt['rtt_stddev'].' · '.($rtt['rtt_p95'] ?? '—').'ms' : '—' }}</dd></div>
            <div><dt>RTT MIN / MAX</dt><dd>{{ isset($rtt['rtt_min']) ? $rtt['rtt_min'].' / '.$rtt['rtt_max'].'ms' : '—' }}</dd></div>
            <div><dt>JITTER · LOSS</dt><dd>{{ $rtt ? ($rtt['jitter'] ?? 0).'ms · '.($rtt['loss'] ?? 0).'%' : '—' }}</dd></div>
            <div><dt>RESPONSE TIME MEAN · MEDIAN</dt><dd>{{ isset($rtt['total_avg']) ? $rtt['total_avg'].' · '.($rtt['total_median'] ?? '—').'ms' : '—' }}</dd></div>
            <div><dt>GOODPUT (LAST STEP)</dt><dd>{{ isset($thr['bytes_per_sec']) ? $bytes($thr['bytes_per_sec']).'/s' : '—' }}</dd></div>
        </dl>
        <p class="small muted" style="margin:16px 0 0">
            RTT = TCP handshake (one round trip), on a new connection per request. Response time = whole HTTP request
            (DNS + connect + TLS + server + transfer). Goodput = payload bytes received per second (headers excluded).
            Std dev is the sample standard deviation; jitter is the mean difference between consecutive RTTs (RFC 3550).
        </p>
    </section>

    {{-- charts --}}
    <section class="box mb">
        <div wire:ignore>
            <div class="box-title">
                <div class="tabs" data-tabs>
                    <button class="active" data-pane="lab-rtt">RTT</button>
                    <button data-pane="lab-thr">THROUGHPUT</button>
                    <button data-pane="lab-con">CONCURRENCY</button>
                </div>
                <span class="px muted" data-caption>MS PER SAMPLE · GREEN RTT, BLUE TOTAL</span>
            </div>
            <div id="lab-rtt" class="chart"><canvas></canvas></div>
            <div id="lab-thr" class="chart" hidden><canvas></canvas></div>
            <div id="lab-con" class="chart" hidden><canvas></canvas></div>
        </div>
    </section>

    {{-- step tables (copy into the report) --}}
    <div class="cols half mb">
        <section class="box table-wrap">
            <div class="box-title"><h2>THROUGHPUT STEPS</h2>@if ($this->latest['throughput'])<span class="px muted">#{{ $this->latest['throughput']->id }}</span>@endif</div>
            @if (! $this->thrSeries)
                <p class="muted" style="margin:0">Run a throughput test.</p>
            @else
                <table class="tbl">
                    <thead><tr><th>SIZE</th><th class="num">REQ/S</th><th class="num">GOODPUT</th><th class="num">MEDIAN</th><th class="num">ERR</th></tr></thead>
                    <tbody>
                        @foreach ($this->thrSeries as $s)
                            <tr><td>{{ ($s['size'] ?? null) ? $bytes($s['size']) : 'target' }}</td><td class="num">{{ $s['req_per_sec'] ?? '—' }}</td><td class="num">{{ $bytes($s['goodput'] ?? 0) }}/s</td><td class="num">{{ $s['median_ms'] ?? '—' }}ms</td><td class="num">{{ $s['errors'] ?? 0 }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
        <section class="box table-wrap">
            <div class="box-title"><h2>CONCURRENCY STEPS</h2>@if ($this->latest['concurrency'])<span class="px muted">#{{ $this->latest['concurrency']->id }}</span>@endif</div>
            @if (! $this->conSeries)
                <p class="muted" style="margin:0">Run a concurrency test.</p>
            @else
                <table class="tbl">
                    <thead><tr><th>CLIENTS</th><th class="num">MSG/S</th><th class="num">MEAN</th><th class="num">MEDIAN</th><th class="num">P95</th><th class="num">ERR</th></tr></thead>
                    <tbody>
                        @foreach ($this->conSeries as $s)
                            <tr><td>{{ $s['clients'] ?? '—' }}</td><td class="num">{{ $s['req_per_sec'] ?? '—' }}</td><td class="num">{{ $s['avg_ms'] ?? '—' }}ms</td><td class="num">{{ $s['median_ms'] ?? '—' }}ms</td><td class="num">{{ $s['p95_ms'] ?? '—' }}ms</td><td class="num">{{ $s['errors'] ?? 0 }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </div>

    {{-- history --}}
    <section class="box table-wrap">
        <div class="box-title"><h2>HISTORY</h2><button class="btn sm" wire:click="export">EXPORT CSV</button></div>
        @if ($this->history->isEmpty())
            <p class="muted" style="margin:0">No experiments yet. Pick a target and start a test.</p>
        @else
            <table class="tbl">
                <thead><tr><th>#</th><th>DATE</th><th>TEST</th><th>TARGET</th><th>PARAMS</th><th class="num">RESULT</th><th></th></tr></thead>
                <tbody>
                    @foreach ($this->history as $e)
                        <tr wire:key="exp-{{ $e->id }}">
                            <td>{{ $e->id }}</td>
                            <td>{{ $e->created_at->local()->format('M d, H:i') }}</td>
                            <td><span class="tag">{{ $names[$e->type] }}</span></td>
                            <td title="{{ $e->target_url }}">{{ $e->host() }}</td>
                            <td class="muted">{{ $e->paramsLabel() }}</td>
                            <td class="num">
                                @if ($e->status === 'failed')
                                    <span class="c-bad" title="{{ $e->error }}">FAILED</span>
                                @elseif ($e->isRunning())
                                    <span class="c-info">{{ $e->progress }}%</span>
                                @else
                                    {{ $e->resultLabel() }}
                                @endif
                            </td>
                            <td class="num">@if ($e->status === 'done')<button class="btn sm" wire:click="exportSamples({{ $e->id }})">CSV</button>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
</div>

@script
<script>
    const root = $wire.$el;
    const canvas = id => root.querySelector('#' + id + ' canvas');
    const captions = {
        'lab-rtt': 'MS PER SAMPLE · GREEN RTT, BLUE TOTAL',
        'lab-thr': 'GOODPUT KB/S BY PAYLOAD SIZE',
        'lab-con': 'MEAN MS BY CLIENTS',
    };
    const kb = b => b >= 1048576 ? (b / 1048576).toFixed(0) + 'MB' : b >= 1024 ? (b / 1024).toFixed(0) + 'KB' : b + 'B';

    const empty = (c, text) => {
        const r = c.parentElement.getBoundingClientRect();
        if (!r.width) return;
        c.width = r.width; c.height = r.height;
        const ctx = c.getContext('2d');
        ctx.clearRect(0, 0, r.width, r.height);
        ctx.fillStyle = PM.C.stone2; ctx.font = '8px "Press Start 2P"'; ctx.textAlign = 'center';
        ctx.fillText(text, r.width / 2, r.height / 2);
    };
    const clean = arr => { let last = arr.find(v => v !== null) ?? 0; return arr.map(v => v === null ? last : (last = v)); };
    const ticks = n => n < 2 ? ['#1'] : [0, .25, .5, .75, 1].map(f => '#' + Math.max(1, Math.round(f * n)));

    const draw = () => {
        const rtt = $wire.rttSeries || {};
        if (!(rtt.rtt || []).length) empty(canvas('lab-rtt'), 'RUN AN RTT TEST');
        else {
            const a = clean(rtt.rtt), b = clean(rtt.total);
            PM.lineChart(canvas('lab-rtt'), [{ data: b, color: PM.C.info }, { data: a, color: PM.C.hp, fill: true }], {
                unit: 'ms', xlabels: ticks(a.length), max: Math.ceil(Math.max(1, ...b) * 1.2),
            });
        }

        const thr = $wire.thrSeries || [];
        if (!thr.length) empty(canvas('lab-thr'), 'RUN A THROUGHPUT TEST');
        else PM.barChart(canvas('lab-thr'), thr.map(s => Math.round((s.goodput ?? 0) / 1024)), {
            unit: '', color: PM.C.info, xlabels: thr.map(s => s.size ? kb(s.size) : 'target'),
        });

        const con = $wire.conSeries || [];
        if (!con.length) empty(canvas('lab-con'), 'RUN A CONCURRENCY TEST');
        else {
            const avg = con.map(s => s.avg_ms ?? 0);
            const first = avg[0] || 1;
            PM.barChart(canvas('lab-con'), avg, {
                unit: 'ms', xlabels: con.map(s => s.clients + 'c'),
                colorFn: v => v > first * 3 ? PM.C.bad : v > first * 1.5 ? PM.C.warn : PM.C.hp,
            });
        }
    };

    root.querySelector('[data-tabs]').addEventListener('click', e => {
        const pane = e.target.closest('[data-pane]')?.dataset.pane;
        if (pane) root.querySelector('[data-caption]').textContent = captions[pane];
    });

    draw();
    $wire.$watch('rttSeries', draw);
    $wire.$watch('thrSeries', draw);
    $wire.$watch('conSeries', draw);
    window.addEventListener('resize', draw);
</script>
@endscript

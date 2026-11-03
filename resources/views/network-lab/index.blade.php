@extends('layouts.app')

@section('title', 'Network lab')

@section('content')
<div class="head">
    <div>
        <h1>NETWORK LAB</h1>
        <p class="sub">Experiments for the networks course.</p>
    </div>
</div>

<section class="box mb">
    <div class="field"><label>TARGET</label><input class="input" value="https://api.shop.gg/health"></div>
    <div class="row">
        <button class="btn primary" onclick="run('RTT')">START RTT TEST</button>
        <button class="btn primary" onclick="run('THROUGHPUT')">START THROUGHPUT TEST</button>
        <button class="btn primary" onclick="run('CONCURRENCY')">START CONCURRENCY TEST</button>
    </div>
    <div class="mt" id="prog" hidden>
        <div class="row between"><span class="px" id="prog-lbl"></span><span class="px" id="prog-pct">0%</span></div>
        <div class="hp mt"><span>EXP</span><div class="bar"><i id="prog-bar" style="width:0;background:var(--info)"></i></div></div>
    </div>
</section>

<section class="box mb">
    <dl class="kpis c3">
        <div><dt>RTT AVG</dt><dd id="rtt">38ms</dd></div>
        <div><dt>RTT MIN / MAX</dt><dd id="rtt-mm">21 / 112ms</dd></div>
        <div><dt>THROUGHPUT</dt><dd id="thr">842 req/s</dd></div>
        <div><dt>RESPONSE TIME</dt><dd id="rt">143ms</dd></div>
        <div><dt>CLIENTS</dt><dd id="cc">50</dd></div>
        <div><dt>PACKET LOSS</dt><dd>0.0%</dd></div>
    </dl>
</section>

<section class="box mb">
    <div class="box-title">
        <div class="tabs" data-tabs>
            <button class="active" data-pane="p-rtt">RTT</button>
            <button data-pane="p-thr">THROUGHPUT</button>
            <button data-pane="p-con">CONCURRENCY</button>
        </div>
    </div>
    <div id="p-rtt" class="chart"><canvas id="ch-rtt"></canvas></div>
    <div id="p-thr" class="chart" hidden><canvas id="ch-thr"></canvas></div>
    <div id="p-con" class="chart" hidden><canvas id="ch-con"></canvas></div>
</section>

<section class="box table-wrap">
    <div class="box-title"><h2>HISTORY</h2></div>
    <table class="tbl">
        <thead><tr><th>#</th><th>DATE</th><th>TEST</th><th class="num">CLIENTS</th><th class="num">RESULT</th></tr></thead>
        <tbody>
            <tr><td>42</td><td>Nov 02, 13:40</td><td>RTT</td><td class="num">1</td><td class="num">38ms avg</td></tr>
            <tr><td>41</td><td>Nov 02, 13:12</td><td>THROUGHPUT</td><td class="num">50</td><td class="num">842 req/s</td></tr>
            <tr><td>40</td><td>Nov 02, 12:55</td><td>CONCURRENCY</td><td class="num">200</td><td class="num c-warn">488ms avg</td></tr>
            <tr><td>39</td><td>Nov 01, 18:20</td><td>CONCURRENCY</td><td class="num">100</td><td class="num c-bad">timeouts</td></tr>
        </tbody>
    </table>
</section>
@endsection

@push('scripts')
<script>
    const draw = () => {
        PM.lineChart(document.getElementById('ch-rtt'), [{ data: PM.series(60, 38, 26, .05).map(v => Math.max(20, v)), color: PM.C.hp, fill: true }], { unit: 'ms', xlabels: ['#1', '#15', '#30', '#45', '#60'], max: 120 });
        PM.lineChart(document.getElementById('ch-thr'), [{ data: PM.series(30, 820, 160), color: PM.C.info, fill: true }], { xlabels: ['0s', '10s', '20s', '30s'], max: 1200 });
        PM.barChart(document.getElementById('ch-con'), [95, 110, 143, 181, 240, 311, 402, 488], { unit: 'ms', xlabels: ['10', '50', '100', '150', '200'], colorFn: v => v > 400 ? PM.C.bad : v > 250 ? PM.C.warn : PM.C.hp });
    };
    draw(); window.addEventListener('resize', draw);

    function run(kind) {
        document.getElementById('prog').hidden = false;
        const lbl = document.getElementById('prog-lbl');
        lbl.textContent = kind + ' TEST RUNNING...';
        let pct = 0;
        const t = setInterval(() => {
            pct = Math.min(100, pct + PM.rnd(3, 9));
            document.getElementById('prog-pct').textContent = pct + '%';
            document.getElementById('prog-bar').style.width = pct + '%';
            if (kind === 'RTT') { document.getElementById('rtt').textContent = PM.rnd(30, 45) + 'ms'; document.getElementById('rtt-mm').textContent = PM.rnd(18, 24) + ' / ' + PM.rnd(90, 130) + 'ms'; }
            if (kind === 'THROUGHPUT') document.getElementById('thr').textContent = PM.rnd(760, 920) + ' req/s';
            if (kind === 'CONCURRENCY') { document.getElementById('rt').textContent = PM.rnd(120, 180) + 'ms'; document.getElementById('cc').textContent = Math.round(50 * pct / 100); }
            if (pct === 100) { clearInterval(t); lbl.textContent = kind + ' TEST COMPLETE!'; draw(); PM.toast('Experiment #43 saved.'); }
        }, 180);
    }
</script>
@endpush

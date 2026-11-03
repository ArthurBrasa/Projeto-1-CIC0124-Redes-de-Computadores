@extends('layouts.app')

@section('title', 'Payments API')

@section('content')
<div class="head">
    <div>
        <a class="back" href="{{ route('monitors.index') }}">◀ MONITORS</a>
        <div class="row"><h1>PAYMENTS API</h1><span class="st down">DOWN</span></div>
        <p class="sub">GET pay.shop.gg/ping, every 1 min.</p>
    </div>
    <button class="btn" data-toast="Check queued...">CHECK NOW</button>
</div>

<div class="box dialog mb">
    <p style="margin:0" data-type>PAYMENTS API stopped answering at 14:03. <b>Timeout after 10s</b> on the last 14 checks.</p>
</div>

<section class="box mb">
    <dl class="kpis">
        <div><dt>STATUS</dt><dd class="c-bad">DOWN</dd></div>
        <div><dt>UPTIME 30D</dt><dd>99.21%</dd></div>
        <div><dt>AVG RESPONSE</dt><dd>233ms</dd></div>
        <div><dt>LAST CHECK</dt><dd>14:17</dd></div>
    </dl>
</section>

<section class="box mb">
    <div class="box-title">
        <h2>LAST 90 DAYS</h2>
        <div class="legend"><span><i style="background:var(--hp)"></i>UP</span><span><i style="background:var(--warn)"></i>SLOW</span><span><i style="background:var(--bad)"></i>DOWN</span></div>
    </div>
    <div class="blocks" data-auto="90" data-step="86400000" data-down="89,61,34" data-deg="70,45,12"></div>
    <div class="row between mt"><span class="px muted">90 DAYS AGO</span><span class="px muted">TODAY</span></div>
</section>

<section class="box mb">
    <div class="box-title"><h2>LATENCY</h2><span class="px muted">24H</span></div>
    <div class="chart"><canvas id="ch"></canvas></div>
</section>

<div class="cols half">
    <section class="box">
        <div class="box-title"><h2>CHECKS</h2></div>
        <table class="tbl">
            <tbody>
                <tr><td>14:17</td><td><span class="st down">TIMEOUT</span></td><td class="num">10000ms</td></tr>
                <tr><td>14:16</td><td><span class="st down">TIMEOUT</span></td><td class="num">10000ms</td></tr>
                <tr><td>14:03</td><td><span class="st down">503</span></td><td class="num">3908ms</td></tr>
                <tr><td>14:02</td><td><span class="st warn">200 SLOW</span></td><td class="num">812ms</td></tr>
                <tr><td>14:01</td><td><span class="st up">200 OK</span></td><td class="num">240ms</td></tr>
            </tbody>
        </table>
    </section>
    <section class="box">
        <div class="box-title"><h2>DOWNTIME</h2></div>
        <table class="tbl">
            <tbody>
                <tr><td>Today 14:03</td><td class="c-bad">ongoing</td></tr>
                <tr><td>Oct 04, 02:11</td><td>1h 52m</td></tr>
                <tr><td>Sep 07, 19:40</td><td>3h 21m</td></tr>
                <tr><td>Aug 22, 08:03</td><td>14m</td></tr>
            </tbody>
        </table>
    </section>
</div>
@endsection

@push('scripts')
<script>
    const draw = () => {
        const d = PM.series(48, 230, 70, .04);
        for (let i = 41; i < 48; i++) d[i] = i < 43 ? 800 : 1000;
        PM.lineChart(document.getElementById('ch'), [{ data: d, color: PM.C.hp, fill: true }], { unit: 'ms', xlabels: ['-24h', '-18h', '-12h', '-6h', 'now'], threshold: 500, max: 1000 });
    };
    draw(); window.addEventListener('resize', draw);
</script>
@endpush

@extends('layouts.app')

@section('title', 'checkout-api')

@section('content')
<div class="head">
    <div>
        <a class="back" href="{{ route('applications.index') }}">◀ APPLICATIONS</a>
        <div class="row"><h1>CHECKOUT-API</h1><span class="st up">ONLINE</span></div>
        <p class="sub">Production. 61,204 events received in 24h.</p>
    </div>
    <div class="row">
        <button class="btn" data-toast="Test event sent. 200 OK!">TEST INTEGRATION</button>
        <button class="btn" data-modal="m-regen">REGENERATE TOKEN</button>
    </div>
</div>

<div class="cols half mb">
    <section class="box">
        <div class="box-title"><h2>INTEGRATION</h2></div>
        <dl class="kv">
            <div><dt>APPLICATION ID</dt><dd><code>app_7f3kq9</code></dd></div>
            <div><dt>API TOKEN</dt><dd><code>pm_live_9Xk2 ••••••••</code></dd></div>
            <div><dt>WEBHOOK URL</dt><dd><code>ingest.pulsemonitor.gg/v1/hooks/app_7f3kq9</code><button class="btn sm" data-copy="https://ingest.pulsemonitor.gg/v1/hooks/app_7f3kq9" data-label="Webhook copied!">COPY</button></dd></div>
        </dl>
    </section>

    <section class="box">
        <div class="box-title"><h2>EVENTS BY LEVEL</h2><span class="px muted">24H</span></div>
        <dl class="stats">
            <div><dt>INFO</dt><dd>58,902</dd></div>
            <div><dt class="c-warn">WARNING</dt><dd>2,090</dd></div>
            <div><dt class="c-bad">ERROR</dt><dd>212</dd></div>
        </dl>
    </section>
</div>

<section class="box mb">
    <div class="box-title"><h2>EVENTS PER MINUTE</h2><span class="px muted">LAST 2H</span></div>
    <div class="chart"><canvas id="ch"></canvas></div>
</section>

<section class="box dark">
    <div class="box-title"><h2>RECENT LOGS</h2><a class="link" href="{{ route('events.index') }}">ALL EVENTS</a></div>
    <div class="log" id="feed"></div>
</section>
@endsection

@push('modals')
<div class="modal-bg" id="m-regen">
    <div class="box modal">
        <h2>REGENERATE TOKEN?</h2>
        <p style="margin:14px 0 0">The current token stops working right away. Every integration using it will get <b class="c-bad" style="font-weight:400">401 Unauthorized</b>.</p>
        <div class="actions">
            <button class="btn" data-close>CANCEL</button>
            <button class="btn danger" data-close data-toast="New token generated!">REGENERATE</button>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script>
    const draw = () => PM.barChart(document.getElementById('ch'), PM.series(40, 110, 50, .05), { xlabels: ['-2h', '-1h30', '-1h', '-30m', 'now'], colorFn: v => v > 170 ? PM.C.warn : PM.C.hp });
    draw(); window.addEventListener('resize', draw);
    PM.feed(document.getElementById('feed'), { max: 8, app: 'checkout-api' });
</script>
@endpush

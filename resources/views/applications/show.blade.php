@extends('layouts.app')

@section('title', $application->name)

@section('content')
<div class="head">
    <div>
        <a class="back" href="{{ route('applications.index') }}">◀ APPLICATIONS</a>
        <div class="row"><h1>{{ strtoupper($application->name) }}</h1><span class="st off">WAITING EVENTS</span></div>
        <p class="sub">{{ ucfirst($application->environment) }} · {{ $application->url }} · created {{ $application->created_at->format('M d, Y') }}</p>
    </div>
    <div class="row">
        <button class="btn" data-toast="Test event sent. 200 OK!">TEST INTEGRATION</button>
        <button class="btn" data-modal="m-regen">REGENERATE TOKEN</button>
    </div>
</div>

<div class="cols half mb">
    <livewire:application-token :application="$application" />

    <section class="box">
        <div class="box-title"><h2>EVENTS BY LEVEL</h2><span class="tag" style="opacity:.5;margin-left:10px">SAMPLE</span><span class="px muted">24H</span></div>
        <dl class="stats">
            <div><dt>INFO</dt><dd>58,902</dd></div>
            <div><dt class="c-warn">WARNING</dt><dd>2,090</dd></div>
            <div><dt class="c-bad">ERROR</dt><dd>212</dd></div>
        </dl>
    </section>
</div>

<section class="box mb">
    <div class="box-title"><h2>EVENTS PER MINUTE</h2><span class="tag" style="opacity:.5;margin-left:10px">SAMPLE</span><span class="px muted">LAST 2H</span></div>
    <div class="chart"><canvas id="ch"></canvas></div>
</section>

<section class="box dark">
    <div class="box-title"><h2>RECENT LOGS</h2><span class="tag" style="opacity:.5;margin-left:10px">SAMPLE</span><a class="link" href="{{ route('events.index') }}">ALL EVENTS</a></div>
    <div class="log" id="feed"></div>
</section>
@endsection


@push('scripts')
<script>
    const draw = () => PM.barChart(document.getElementById('ch'), PM.series(40, 110, 50, .05), { xlabels: ['-2h', '-1h30', '-1h', '-30m', 'now'], colorFn: v => v > 170 ? PM.C.warn : PM.C.hp });
    draw(); window.addEventListener('resize', draw);
    PM.feed(document.getElementById('feed'), { max: 8, app: @js($application->name) });
</script>
@endpush

@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="head">
    <div>
        <h1>DASHBOARD</h1>
        <p class="sub">Live overview of your systems.</p>
    </div>
    <a class="btn" href="{{ route('applications.create') }}">NEW APP</a>
</div>

<div class="box dialog mb">
    <p style="margin:0" data-type>Welcome back, {{Auth::user()->name}}! 11 of 12 systems are running. <b>PAYMENTS</b> is DOWN since 14:03.</p>
</div>

<div class="cols">
    <div class="stack">
        <section class="box">
            <div class="box-title">
                <div class="tabs" data-tabs>
                    <button class="active" data-pane="p-resp">RESPONSE</button>
                    <button data-pane="p-avail">UPTIME</button>
                    <button data-pane="p-epm">EVENTS/MIN</button>
                </div>
                <span class="px muted">24H</span>
            </div>
            <div id="p-resp" class="chart"><canvas id="ch-resp"></canvas></div>
            <div id="p-avail" class="chart" hidden><canvas id="ch-avail"></canvas></div>
            <div id="p-epm" class="chart" hidden><canvas id="ch-epm"></canvas></div>
        </section>

        <section class="box dark">
            <div class="box-title"><h2>LIVE ACTIVITY</h2><a class="link" href="{{ route('events.index') }}">ALL EVENTS</a></div>
            <div class="log" id="feed"></div>
        </section>
    </div>

    <div class="stack">
        <section class="box">
            <div class="box-title"><h2>STATUS</h2></div>
            <dl class="stats">
                <div><dt>APPS</dt><dd>12</dd></div>
                <div><dt>ONLINE</dt><dd class="c-up">11</dd></div>
                <div><dt>MONITORS</dt><dd>27</dd></div>
                <div><dt>UPTIME</dt><dd>99.97%</dd></div>
                <div><dt>EVENTS 24H</dt><dd>184k</dd></div>
                <div><dt>ALERTS</dt><dd class="c-bad">3</dd></div>
            </dl>
        </section>

        <section class="box">
            <div class="box-title"><h2>PARTY</h2><a class="link" href="{{ route('applications.index') }}">ALL</a></div>
            <div class="party">
                @foreach ([
                    ['checkout-api', 100, 'up'],
                    ['auth-service', 100, 'up'],
                    ['payments', 18, 'down'],
                    ['mobile-bff', 96, 'up'],
                    ['webhook-relay', 62, 'warn'],
                ] as [$name, $hp, $st])
                    <a href="{{ route('applications.index') }}" style="grid-template-columns:minmax(0,1fr) auto">
                        <div>
                            <div class="name">{{ $name }}</div>
                            <div @class(['hp', 'mid' => $hp < 80 && $hp >= 40, 'low' => $hp < 40])><span>HP</span><div class="bar"><i style="width:{{ $hp }}%"></i></div></div>
                        </div>
                        <span class="st {{ $st }}"></span>
                    </a>
                @endforeach
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const xl = ['00h', '06h', '12h', '18h', 'now'];
    const draw = () => {
        PM.lineChart(document.getElementById('ch-resp'), [{ data: PM.series(40, 160, 60, .04), color: PM.C.hp, fill: true }], { unit: 'ms', xlabels: xl, threshold: 500, max: 600 });
        PM.lineChart(document.getElementById('ch-avail'), [{ data: PM.series(40, 99.6, .6).map((v, i) => i > 34 ? 91.6 : Math.min(100, v)), color: PM.C.hp, fill: true }], { unit: '%', xlabels: xl, min: 90, max: 100 });
    };
    draw(); window.addEventListener('resize', draw);
    PM.live(document.getElementById('ch-epm'), { base: 120, noise: 50, max: 240, color: PM.C.info });
    PM.feed(document.getElementById('feed'), { max: 6 });
</script>
@endpush

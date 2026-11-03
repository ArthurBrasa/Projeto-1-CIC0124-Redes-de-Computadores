@extends('layouts.app')

@section('title', 'Uptime monitors')

@section('content')
<div class="head">
    <div>
        <h1>UPTIME MONITORS</h1>
        <p class="sub">27 monitors. 26 up, 1 down.</p>
    </div>
    <a class="btn primary" href="{{ route('monitors.create') }}">ADD MONITOR</a>
</div>

<section class="box table-wrap">
    <table class="tbl">
        <thead>
            <tr><th>MONITOR</th><th>METHOD</th><th>STATUS</th><th class="num">AVG</th><th style="width:180px">UPTIME</th><th class="num">CHECKED</th></tr>
        </thead>
        <tbody>
            @foreach ([
                ['API Gateway', 'api.shop.gg/health', 'GET', 'up', '142ms', 100, '12s'],
                ['Payments API', 'pay.shop.gg/ping', 'GET', 'down', '—', 87.5, '4s'],
                ['Storefront', 'shop.gg', 'GET', 'up', '88ms', 99.9, '30s'],
                ['Auth Login', 'auth.shop.gg/login', 'POST', 'up', '201ms', 100, '18s'],
                ['Webhook Relay', 'hooks.shop.gg/status', 'GET', 'warn', '612ms', 99.8, '52s'],
                ['Status Page', 'status.shop.gg', 'GET', 'up', '54ms', 100, '7s'],
            ] as [$name, $url, $method, $st, $avg, $up, $ago])
                <tr>
                    <td><a class="name" href="{{ route('monitors.show') }}">{{ $name }}</a><div class="small muted">{{ $url }}</div></td>
                    <td><span class="tag">{{ $method }}</span></td>
                    <td><span class="st {{ $st }}">{{ ['up' => 'UP', 'down' => 'DOWN', 'warn' => 'SLOW'][$st] }}</span></td>
                    <td class="num">{{ $avg }}</td>
                    <td><div @class(['hp', 'mid' => $up < 99.5 && $up >= 95, 'low' => $up < 95])><span>{{ $up }}%</span><div class="bar"><i style="width:{{ $up }}%"></i></div></div></td>
                    <td class="num muted">{{ $ago }} ago</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</section>
@endsection

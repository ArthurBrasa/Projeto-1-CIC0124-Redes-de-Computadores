@extends('layouts.app')

@section('title', 'Applications')

@section('content')
<div class="head">
    <div>
        <h1>APPLICATIONS</h1>
        <p class="sub">12 registered. 11 online.</p>
    </div>
    <a class="btn primary" href="{{ route('applications.create') }}">NEW APP</a>
</div>

<section class="box">
    <div class="row mb">
        <input class="input" style="flex:1;min-width:220px" placeholder="Search applications...">
        <select class="input" style="width:auto"><option>All environments</option><option>Production</option><option>Staging</option><option>Development</option></select>
    </div>

    <div class="party">
        @foreach ([
            ['checkout-api', 'PRODUCTION', 'api.shop.gg/checkout', 100, 'up', '61k events'],
            ['auth-service', 'PRODUCTION', 'auth.shop.gg', 100, 'up', '44k events'],
            ['payments', 'PRODUCTION', 'pay.shop.gg', 18, 'down', '9k events'],
            ['mobile-bff', 'PRODUCTION', 'bff.shop.gg', 96, 'up', '31k events'],
            ['billing-worker', 'PRODUCTION', 'queue consumer', 100, 'up', '12k events'],
            ['search-index', 'STAGING', 'search.shop.gg', 88, 'up', '5k events'],
            ['checkout-api', 'DEVELOPMENT', 'localhost:8000', 100, 'up', '1k events'],
        ] as [$name, $env, $url, $hp, $st, $events])
            <a href="{{ route('applications.show') }}">
                <span data-ico="apps" data-size="26"></span>
                <div>
                    <div class="row" style="gap:10px"><span class="name">{{ $name }}</span><span class="tag" style="opacity:.6">{{ $env }}</span></div>
                    <div class="meta">{{ $url }}</div>
                    <div @class(['hp', 'mid' => $hp < 80 && $hp >= 40, 'low' => $hp < 40])><span>HP</span><div class="bar"><i style="width:{{ $hp }}%"></i></div></div>
                </div>
                <div class="right">
                    <span class="st {{ $st }}">{{ $st === 'up' ? 'ONLINE' : 'DOWN' }}</span>
                    <span class="small muted">{{ $events }}</span>
                </div>
            </a>
        @endforeach
    </div>
</section>
@endsection

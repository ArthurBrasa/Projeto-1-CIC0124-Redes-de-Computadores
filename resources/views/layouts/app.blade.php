@php
    $menu = [
        ['DASHBOARD', 'dashboard', 'dashboard'],
        ['APPS', 'applications.index', 'applications.*'],
        ['MONITORS', 'monitors.index', 'monitors.*'],
        ['EVENTS', 'events.index', 'events.*'],
        ['ALERTS', 'alerts.index', 'alerts.*'],
        ['NETWORK LAB', 'network-lab', 'network-lab'],
    ];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PULSEMONITOR — @yield('title')</title>
    <link rel="stylesheet" href="{{ asset('pulse/pulse.css') }}">
    @stack('styles')
    @vite('resources/js/app.js')
</head>
<body>
<div class="app">
    <header class="topbar">
        <a class="logo" href="{{ route('dashboard') }}"><span data-ico="heart" data-color="#d6474f" data-size="18"></span>PULSEMONITOR</a>
        <div class="spacer"></div>
        <span class="chip hide-sm"><span class="st up"></span><b>SYSTEMS OK</b></span>
        <span class="chip hide-sm">WS <b data-ws>...</b></span>
        <span class="chip hide-sm" id="clock">--:--</span>
        <a class="chip" href="{{ route('settings') }}">{{auth()->user()->firstName()}} <b>LV1</b></a>
    </header>

    <aside class="side">
        <nav class="box menu">
            @foreach ($menu as [$label, $route, $pattern])
                <a href="{{ route($route) }}" @class(['active' => request()->routeIs($pattern)])>
                    {{ $label }}@if ($label === 'ALERTS')<livewire:alert-badge />@endif
                </a>
            @endforeach
            <hr>
            <a href="{{ route('settings') }}" @class(['active' => request()->routeIs('settings')])>OPTIONS</a>
            <form method="POST" action="{{ route('logout') }}" style="margin:0">
                @csrf
                <a href="{{ route('logout') }}" onclick="event.preventDefault(); this.closest('form').submit()">EXIT</a>
            </form>
        </nav>
    </aside>

    <main class="main">
        @yield('content')
    </main>
</div>

@stack('modals')

<script src="{{ asset('pulse/pulse.js') }}"></script>
@if (session('toast'))
<script>PM.toast(@js(session('toast')));</script>
@endif
@stack('scripts')
</body>
</html>

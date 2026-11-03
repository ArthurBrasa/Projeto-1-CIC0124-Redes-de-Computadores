@extends('layouts.guest')

@section('title', 'Screens')

@section('content')
<div class="auth">
    <div class="auth-wrap">
        <div class="auth-logo">
            <div class="logo"><span data-ico="heart" data-color="#d6474f" data-size="22"></span>PULSEMONITOR</div>
            <div class="slogan">YOUR SYSTEMS. YOUR SIGNAL. REAL-TIME.</div>
        </div>
        <nav class="box menu">
            @foreach ([
                ['PUBLIC STATUS', 'status'], ['LOGIN', 'login'],['REGISTER', 'register'], ['DASHBOARD', 'dashboard'],
                ['APPS', 'applications.index'], ['NEW APP', 'applications.create'], ['APP DETAILS', 'applications.show'],
                ['MONITORS', 'monitors.index'], ['NEW MONITOR', 'monitors.create'], ['MONITOR DETAILS', 'monitors.show'],
                ['EVENTS', 'events.index'], ['ALERTS', 'alerts.index'], ['NETWORK LAB', 'network-lab'], ['OPTIONS', 'settings'],
            ] as [$label, $route])
                <a href="{{ route($route) }}">{{ $label }}</a>
            @endforeach
        </nav>
        <div class="press-start blink">SELECT A SCREEN</div>
    </div>
</div>
@endsection

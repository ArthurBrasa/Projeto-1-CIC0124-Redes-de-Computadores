@extends('layouts.app')

@section('title', 'Applications')

@php
    $applications = auth()->user()->applications()->with('monitors.latestCheck')->withMax('events', 'created_at')->latest()->get();
@endphp

@section('content')
<div class="head">
    <div>
        <h1>APPLICATIONS</h1>
        <p class="sub">{{ $applications->count() }} registered.</p>
    </div>
    <a class="btn primary" href="{{ route('applications.create') }}">NEW APP</a>
</div>

@if ($applications->isEmpty())
    <div class="box dialog">
        <p style="margin:0">No applications yet. Register your first one to get a token and a webhook.</p>
        <a class="btn primary mt" style="margin-top:16px" href="{{ route('applications.create') }}">NEW APP</a>
    </div>
@else
    <section class="box">
        <div class="party">
            @foreach ($applications as $app)
                <a href="{{ route('applications.show', $app) }}">
                    <span data-ico="apps" data-size="26"></span>
                    <div>
                        <div class="row" style="gap:10px"><span class="name">{{ $app->name }}</span><span class="tag" style="opacity:.6">{{ strtoupper($app->environment) }}</span></div>
                        <div class="meta">{{ $app->url }}@if ($app->monitors->count()) · {{ $app->monitors->count() }} {{ $app->monitors->count() === 1 ? 'monitor' : 'monitors' }}@endif</div>
                    </div>
                    <div class="right">
                        @php($st = $app->liveStatus())
                        <span class="st {{ ['up' => 'up', 'down' => 'down', 'idle' => 'off'][$st] }}">{{ ['up' => 'ONLINE', 'down' => 'DOWN', 'idle' => 'NO SIGNAL'][$st] }}</span>
                        <span class="small muted">{{ $app->public_id }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    </section>
@endif
@endsection

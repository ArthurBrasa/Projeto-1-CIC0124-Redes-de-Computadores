@extends('layouts.app')

@section('title', $monitor->name)

@section('content')
<div class="head">
    <div>
        <a class="back" href="{{ route('monitors.index') }}">◀ MONITORS</a>
        <div class="row">
            <h1>{{ strtoupper($monitor->name) }}</h1>
            @if ($monitor->is_active)
                <span class="st up">ACTIVE</span>
            @else
                <span class="st off">PAUSED</span>
            @endif
        </div>
        <p class="sub">{{ $monitor->method }} {{ $monitor->url }}</p>
    </div>
    <livewire:monitor-actions :monitor="$monitor" />
</div>

<div class="mb">
    <livewire:monitor-overview :monitor="$monitor" />
</div>

<section class="box">
    <div class="box-title"><h2>CONFIGURATION</h2>@if ($monitor->is_public)<span class="tag">PUBLIC</span>@endif</div>
    <dl class="kpis">
        <div><dt>METHOD</dt><dd>{{ $monitor->method }}</dd></div>
        <div><dt>CHECK EVERY</dt><dd>{{ $monitor->intervalLabel() }}</dd></div>
        <div><dt>TIMEOUT</dt><dd>{{ $monitor->timeout_seconds }}s</dd></div>
        <div><dt>EXPECTS</dt><dd>{{ $monitor->expected_status }}</dd></div>
    </dl>
    <dl class="kv mt">
        <div><dt>URL</dt><dd><code>{{ $monitor->url }}</code></dd></div>
        <div><dt>CREATED</dt><dd>{{ $monitor->created_at->local()->format('M d, Y H:i') }}</dd></div>
    </dl>
</section>
@endsection

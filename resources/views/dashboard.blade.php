@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="head">
    <div>
        <h1>DASHBOARD</h1>
        <p class="sub">Live overview of your systems.</p>
    </div>
    <div class="row">
        <a class="btn" href="{{ route('monitors.create') }}">NEW MONITOR</a>
        <a class="btn" href="{{ route('applications.create') }}">NEW APP</a>
    </div>
</div>

<livewire:dashboard-summary />

<div class="cols">
    <div class="stack">
        <livewire:dashboard-charts />
        <livewire:dashboard-activity />
    </div>

    <div class="stack">
        <livewire:dashboard-status />
        <livewire:dashboard-party />
    </div>
</div>
@endsection

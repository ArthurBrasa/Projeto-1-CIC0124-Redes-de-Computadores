@extends('layouts.app')

@section('title', isset($monitor) ? 'Edit '.$monitor->name : 'New monitor')

@section('content')
<div class="head">
    <div>
        <a class="back" href="{{ isset($monitor) ? route('monitors.show', $monitor) : route('monitors.index') }}">◀ {{ isset($monitor) ? strtoupper($monitor->name) : 'MONITORS' }}</a>
        <h1>{{ isset($monitor) ? 'EDIT MONITOR' : 'NEW MONITOR' }}</h1>
    </div>
</div>

<livewire:monitor-form :monitor="$monitor ?? null" />
@endsection

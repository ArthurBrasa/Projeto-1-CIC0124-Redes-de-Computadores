@extends('layouts.app')

@section('title', 'Uptime monitors')

@php
    $monitors = auth()->user()->monitors()->latest()->get();
    $active = $monitors->where('is_active', true)->count();
@endphp

@section('content')
<div class="head">
    <div>
        <h1>UPTIME MONITORS</h1>
        <p class="sub">{{ $monitors->count() }} monitors. {{ $active }} active.</p>
    </div>
    <a class="btn primary" href="{{ route('monitors.create') }}">ADD MONITOR</a>
</div>

@if ($monitors->isEmpty())
    <div class="box dialog">
        <p style="margin:0">No monitors yet. Add a URL and we will check if it is up.</p>
        <a class="btn primary" style="margin-top:16px" href="{{ route('monitors.create') }}">ADD MONITOR</a>
    </div>
@else
    <section class="box table-wrap">
        <table class="tbl">
            <thead>
                <tr><th>MONITOR</th><th>METHOD</th><th>STATUS</th><th>EVERY</th><th class="num">EXPECTS</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($monitors as $monitor)
                    <tr class="clickable" onclick="if (!event.target.closest('a')) location.href='{{ route('monitors.show', $monitor) }}'">
                        <td><a class="name" href="{{ route('monitors.show', $monitor) }}">{{ $monitor->name }}</a><div class="small muted">{{ $monitor->url }}</div></td>
                        <td><span class="tag">{{ $monitor->method }}</span></td>
                        <td>
                            @if ($monitor->is_active)
                                <span class="st up">ACTIVE</span>
                            @else
                                <span class="st off">PAUSED</span>
                            @endif
                        </td>
                        <td>{{ $monitor->intervalLabel() }}</td>
                        <td class="num">{{ $monitor->expected_status }}</td>
                        <td class="num"><a class="btn sm" href="{{ route('monitors.edit', $monitor) }}">EDIT</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
@endif
@endsection

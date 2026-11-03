@extends('layouts.app')

@section('title', $application->name)

@php
    $lastEvent = $application->events()->latest('id')->first();
    $receiving = $lastEvent && $lastEvent->created_at->gt(now()->subMinutes(10));
@endphp

@section('content')
<div class="head">
    <div>
        <a class="back" href="{{ route('applications.index') }}">◀ APPLICATIONS</a>
        <div class="row">
            <h1>{{ strtoupper($application->name) }}</h1>
            @if ($receiving)
                <span class="st up">RECEIVING</span>
            @elseif ($lastEvent)
                <span class="st warn">QUIET</span>
            @else
                <span class="st off">WAITING EVENTS</span>
            @endif
        </div>
        <p class="sub">
            {{ ucfirst($application->environment) }} · {{ $application->url }}
            · {{ $lastEvent ? 'last event '.$lastEvent->created_at->diffForHumans() : 'no events yet' }}
        </p>
    </div>
    <div class="row">
        <livewire:application-test :application="$application" />
        <button class="btn" data-modal="m-regen">REGENERATE TOKEN</button>
    </div>
</div>

<div class="cols half mb">
    <livewire:application-token :application="$application" />
    <livewire:application-levels :application="$application" />
</div>

<div class="mb">
    <livewire:application-chart :application="$application" />
</div>

<livewire:application-logs :application="$application" />
@endsection

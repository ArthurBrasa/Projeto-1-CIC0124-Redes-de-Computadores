@extends('layouts.app')

@section('title', $application->name)

@php
    $lastEvent = $application->events()->latest('id')->first();
    $status = $application->liveStatus();
@endphp

@section('content')
<div class="head">
    <div>
        <a class="back" href="{{ route('applications.index') }}">◀ APPLICATIONS</a>
        <div class="row">
            <h1>{{ strtoupper($application->name) }}</h1>
            @if ($status === 'up')
                <span class="st up">ONLINE</span>
            @elseif ($status === 'down')
                <span class="st down">DOWN</span>
            @else
                <span class="st off">NO SIGNAL</span>
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
    <livewire:application-monitors :application="$application" />
</div>

<div class="mb">
    <livewire:application-chart :application="$application" />
</div>

<livewire:application-logs :application="$application" />
@endsection

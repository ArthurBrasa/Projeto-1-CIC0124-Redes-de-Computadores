@extends('layouts.app')

@section('title', 'Options')

@section('content')
<div class="head">
    <div>
        <h1>OPTIONS</h1>
    </div>
</div>

<div class="cols half">
    <livewire:profile />

    <livewire:alert-rules />
</div>
@endsection

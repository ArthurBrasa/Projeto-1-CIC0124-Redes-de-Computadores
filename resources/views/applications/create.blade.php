@extends('layouts.app')

@section('title', 'New application')

@section('content')
<div class="head">
    <div>
        <a class="back" href="{{ route('applications.index') }}">◀ APPLICATIONS</a>
        <h1>NEW APPLICATION</h1>
    </div>
</div>

<livewire:application-create />
@endsection

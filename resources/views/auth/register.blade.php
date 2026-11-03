@extends('layouts.guest')

@section('title', 'Create account')

@section('content')
<div class="auth">
    <div class="auth-wrap">
        <div class="auth-logo">
            <div class="logo"><span data-ico="heart" data-color="#d6474f" data-size="22"></span>PULSEMONITOR</div>
            <div class="slogan">YOUR SYSTEMS. YOUR SIGNAL. REAL-TIME.</div>
        </div>

        <div class="box">
            <h2 style="text-align:center">NEW PLAYER</h2>
            <p class="small muted" style="text-align:center;margin:8px 0 20px">Create your account to start monitoring.</p>

            <livewire:register />

            <p class="small" style="text-align:center;margin:18px 0 0">Already playing? <a class="link" href="{{ route('login') }}">BACK TO LOGIN</a></p>
        </div>
    </div>
</div>
@endsection

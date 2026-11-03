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

            <form onsubmit="event.preventDefault(); location.href='{{ route('dashboard') }}'">
                <div class="field"><label>FULL NAME</label><input class="input" type="text" placeholder="Arthur Brasa"></div>
                <div class="field"><label>E-MAIL</label><input class="input" type="email" placeholder="you@company.com"></div>
                <div class="field"><label>PASSWORD</label><input class="input" type="password" placeholder="8+ characters"></div>
                <div class="field"><label>CONFIRM PASSWORD</label><input class="input" type="password"></div>
                <button class="btn primary block mt" type="submit">CREATE PLAYER ACCOUNT</button>
            </form>

            <p class="small" style="text-align:center;margin:18px 0 0">Already playing? <a class="link" href="{{ route('login') }}">BACK TO LOGIN</a></p>
        </div>
    </div>
</div>
@endsection

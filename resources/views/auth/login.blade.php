@extends('layouts.guest')

@section('title', 'Login')

@section('content')
<div class="auth">
    <div class="auth-wrap">
        <div class="auth-logo">
            <div class="logo"><span data-ico="heart" data-color="#d6474f" data-size="22"></span>PULSEMONITOR</div>
            <div class="slogan">YOUR SYSTEMS. YOUR SIGNAL. REAL-TIME.</div>
        </div>

        <div class="box">
            {{-- pixel art: a computer glowing inside a cave --}}
            <svg class="art" viewBox="0 0 40 22" width="200" height="110" shape-rendering="crispEdges">
                <rect x="0" y="0" width="40" height="22" fill="#262338"/>
                <rect x="0" y="0" width="40" height="3" fill="#3a3f5e"/><rect x="4" y="3" width="3" height="2" fill="#3a3f5e"/><rect x="18" y="3" width="2" height="3" fill="#3a3f5e"/><rect x="31" y="3" width="4" height="2" fill="#3a3f5e"/>
                <rect x="0" y="18" width="40" height="4" fill="#3a3f5e"/><rect x="2" y="17" width="6" height="1" fill="#3a3f5e"/><rect x="30" y="16" width="7" height="2" fill="#3a3f5e"/>
                <rect x="13" y="5" width="14" height="10" fill="#cbbd96"/><rect x="14" y="6" width="12" height="7" fill="#181425"/>
                <rect x="15" y="10" width="2" height="1" fill="#6cc551"/><rect x="17" y="8" width="1" height="2" fill="#6cc551"/><rect x="18" y="10" width="1" height="2" fill="#6cc551"/><rect x="19" y="9" width="1" height="1" fill="#6cc551"/><rect x="20" y="10" width="2" height="1" fill="#6cc551"/><rect x="22" y="7" width="1" height="3" fill="#6cc551"/><rect x="23" y="10" width="2" height="1" fill="#6cc551"/>
                <rect x="17" y="15" width="6" height="1" fill="#cbbd96"/><rect x="15" y="16" width="10" height="2" fill="#6b7194"/>
                <rect x="6" y="9" width="1" height="1" fill="#e9a23b"/><rect x="34" y="8" width="1" height="1" fill="#e9a23b"/><rect x="9" y="13" width="1" height="1" fill="#6b7194"/>
            </svg>

            <h2 style="text-align:center">PLAYER LOGIN</h2>
            <p class="small muted" style="text-align:center;margin:8px 0 20px">Insert your credentials.</p>

            <form onsubmit="event.preventDefault(); location.href='{{ route('dashboard') }}'">
                <div class="field">
                    <label for="email">E-MAIL</label>
                    <input class="input" id="email" type="email" value="arthur@pulsemonitor.gg">
                </div>
                <div class="field">
                    <label for="pass">PASSWORD</label>
                    <input class="input" id="pass" type="password" value="hunter2hunter2">
                </div>
                <div class="row between" style="margin-bottom:20px">
                    <div class="choices"><label><input type="checkbox" checked><span>REMEMBER ME</span></label></div>
                    <a class="link" href="#" data-toast="A recovery link was sent to your e-mail.">FORGOT PASSWORD?</a>
                </div>
                <button class="btn primary block" type="submit">ENTER SYSTEM</button>
            </form>

            <p class="small" style="text-align:center;margin:18px 0 0">New player? <a class="link" href="{{ route('register') }}">CREATE ACCOUNT</a></p>
        </div>

        <div class="press-start blink">PRESS START TO CONTINUE</div>
    </div>
</div>
@endsection

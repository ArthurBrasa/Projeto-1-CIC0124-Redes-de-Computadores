@extends('layouts.app')

@section('title', 'New monitor')

@section('content')
<div class="head">
    <div>
        <a class="back" href="{{ route('monitors.index') }}">◀ MONITORS</a>
        <h1>NEW MONITOR</h1>
    </div>
</div>

<form class="box center" onsubmit="event.preventDefault(); PM.toast('Monitor created!'); setTimeout(() => location.href='{{ route('monitors.show') }}', 900)">
    <div class="field"><label>NAME</label><input class="input" value="Payments API" required></div>
    <div class="field"><label>URL</label><input class="input" type="url" value="https://pay.shop.gg/ping" required></div>
    <div class="field">
        <label>METHOD</label>
        <div class="choices">
            <label><input type="radio" name="method" checked><span>GET</span></label>
            <label><input type="radio" name="method"><span>POST</span></label>
        </div>
    </div>
    <div class="fields-2">
        <div class="field"><label>CHECK EVERY</label><select class="input"><option>30 seconds</option><option selected>1 minute</option><option>5 minutes</option></select></div>
        <div class="field"><label>TIMEOUT</label><select class="input"><option>5 seconds</option><option selected>10 seconds</option><option>30 seconds</option></select></div>
    </div>
    <div class="field"><label>EXPECTED STATUS CODE</label><input class="input" value="200" style="max-width:140px"></div>
    <div class="row" style="justify-content:flex-end">
        <a class="btn" href="{{ route('monitors.index') }}">CANCEL</a>
        <button class="btn primary" type="submit">CREATE MONITOR</button>
    </div>
</form>
@endsection

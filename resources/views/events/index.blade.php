@extends('layouts.app')

@section('title', 'Events & logs')

@section('content')
<div class="head">
    <div>
        <h1>EVENTS &amp; LOGS</h1>
        <p class="sub">Newest first. Click a payload to open it.</p>
    </div>
    <button class="btn" id="pause">PAUSE</button>
</div>

<section class="box mb">
    <div class="row">
        <input class="input" style="flex:1;min-width:220px" placeholder="Search message...">
        <select class="input" style="width:auto"><option>All apps</option><option>checkout-api</option><option>auth-service</option><option>payments</option></select>
        <div class="choices">
            <label><input type="checkbox" checked><span>INFO</span></label>
            <label><input type="checkbox" checked><span>WARN</span></label>
            <label><input type="checkbox" checked><span>ERROR</span></label>
        </div>
    </div>
</section>

<section class="box dark">
    <div class="log-head log"><div class="line" style="padding:0"><span>TIME</span><span>LEVEL</span><span class="src">SOURCE</span><span>MESSAGE</span></div></div>
    <div class="log" id="feed"></div>
</section>
@endsection

@push('scripts')
<script>
    const f = PM.feed(document.getElementById('feed'), { max: 14, payload: true, interval: 2200 });
    document.getElementById('pause').addEventListener('click', function () {
        this.textContent = f.toggle() ? 'RESUME' : 'PAUSE';
    });
</script>
@endpush

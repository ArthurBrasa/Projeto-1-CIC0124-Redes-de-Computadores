@extends('layouts.app')

@section('title', 'Options')

@section('content')
<div class="head">
    <div>
        <h1>OPTIONS</h1>
    </div>
</div>

<div class="cols half">
    <section class="box">
        <div class="box-title"><h2>PLAYER</h2></div>
        <div class="field"><label>NAME</label><input class="input" value="Arthur Brasa"></div>
        <div class="field"><label>E-MAIL</label><input class="input" value="arthur@pulsemonitor.gg"></div>
        <div class="field"><label>TIMEZONE</label><select class="input"><option>America/Sao_Paulo</option></select></div>
        <div class="row" style="justify-content:flex-end"><button class="btn primary" data-toast="Game saved!">SAVE</button></div>
    </section>

    <section class="box">
        <div class="box-title"><h2>ALERT RULES</h2></div>
        <div class="field"><label>LATENCY LIMIT (MS)</label><input class="input" value="500"></div>
        <div class="field"><label>ERROR RATE LIMIT (%)</label><input class="input" value="1.0"></div>
        <div class="field">
            <label>NOTIFY BY</label>
            <div class="choices">
                <label><input type="checkbox" checked><span>E-MAIL</span></label>
                <label><input type="checkbox" checked><span>SLACK</span></label>
                <label><input type="checkbox"><span>WEBHOOK</span></label>
            </div>
        </div>
        <div class="row" style="justify-content:flex-end"><button class="btn primary" data-toast="Game saved!">SAVE</button></div>
    </section>
</div>
@endsection

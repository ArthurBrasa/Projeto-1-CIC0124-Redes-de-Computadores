@extends('layouts.app')

@section('title', 'New application')

@php $hook = 'https://ingest.pulsemonitor.gg/v1/hooks/app_7f3kq9'; @endphp

@section('content')
<div class="head">
    <div>
        <a class="back" href="{{ route('applications.index') }}">◀ APPLICATIONS</a>
        <h1>NEW APPLICATION</h1>
    </div>
</div>

<div class="cols form">
    <form class="box" id="app-form">
        <div class="field"><label>NAME</label><input class="input" value="checkout-api" required></div>
        <div class="field"><label>DESCRIPTION</label><textarea class="input">Cart, checkout and order creation.</textarea></div>
        <div class="field">
            <label>ENVIRONMENT</label>
            <div class="choices">
                <label><input type="radio" name="env" checked><span>PRODUCTION</span></label>
                <label><input type="radio" name="env"><span>STAGING</span></label>
                <label><input type="radio" name="env"><span>DEVELOPMENT</span></label>
            </div>
        </div>
        <div class="field"><label>URL</label><input class="input" type="url" value="https://api.shop.gg/checkout" required></div>
        <div class="row" style="justify-content:flex-end"><button class="btn primary" type="submit">REGISTER</button></div>
    </form>

    <div class="stack">
        <div class="box dialog" id="hint">
            <p style="margin:0">Fill in the form. Your App ID, token and webhook will appear here.</p>
        </div>

        <div id="result" class="stack" hidden>
            <section class="box">
                <div class="box-title"><h2>CREDENTIALS</h2><span class="st up">ACTIVE</span></div>
                <dl class="kv">
                    <div><dt>APPLICATION ID</dt><dd><code>app_7f3kq9</code></dd></div>
                    <div><dt>API TOKEN</dt><dd><code id="tok">pm_live_9Xk2Lm4Np8Rt6Vw1</code></dd></div>
                    <div><dt>WEBHOOK ENDPOINT</dt><dd><code>{{ $hook }}</code></dd></div>
                </dl>
                <div class="row mt">
                    <button class="btn sm" id="gen">GENERATE TOKEN</button>
                    <button class="btn sm primary" data-copy="{{ $hook }}" data-label="Webhook copied!">COPY WEBHOOK</button>
                </div>
            </section>

            <section class="box dark">
                <div class="box-title">
                    <div class="tabs" data-tabs>
                        <button class="active" data-pane="ex-curl">CURL</button>
                        <button data-pane="ex-php">LARAVEL</button>
                    </div>
                </div>
<pre class="code" id="ex-curl">curl -X POST {{ $hook }} \
  -H <span class="s">"Authorization: Bearer $PULSE_TOKEN"</span> \
  -d <span class="s">'{"level":"error","message":"Gateway timeout"}'</span></pre>
<pre class="code" id="ex-php" hidden>Http::<span class="f">withToken</span>(<span class="f">env</span>(<span class="s">'PULSE_TOKEN'</span>))
    -><span class="f">post</span>(<span class="s">'{{ $hook }}'</span>, [
        <span class="s">'level'</span>   => <span class="s">'error'</span>,
        <span class="s">'message'</span> => <span class="s">'Gateway timeout'</span>,
    ]);</pre>
            </section>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('app-form').addEventListener('submit', e => {
        e.preventDefault();
        document.getElementById('hint').hidden = true;
        document.getElementById('result').hidden = false;
        PM.toast('Application registered!');
    });
    document.getElementById('gen').addEventListener('click', () => {
        document.getElementById('tok').textContent = 'pm_live_' + Math.random().toString(36).slice(2, 18);
        PM.toast('New token generated!');
    });
</script>
@endpush

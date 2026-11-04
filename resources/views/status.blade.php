@extends('layouts.guest')

@section('title', 'Status')


@push('styles')
<style>
    .status-page { max-width: 860px; margin: 0 auto; padding: 28px 16px 50px; }
    .status-top { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 26px; }
    .svc { padding: 16px 0; border-bottom: 3px dotted var(--paper-3); }
    .svc:last-child { border-bottom: 0; padding-bottom: 0; }
    .svc:first-of-type { padding-top: 0; }
    .svc-scale { display: flex; justify-content: space-between; margin-top: 8px; font-size: 17px; opacity: .6; }
        .incident { padding: 14px 0; border-bottom: 3px dotted var(--paper-3); }
    .incident:last-child { border-bottom: 0; padding-bottom: 0; }
    .incident:first-of-type { padding-top: 0; }
    .incident .when { font-size: 18px; opacity: .6; margin-top: 4px; }
    .incident p { margin: 8px 0 0; }
</style>
@endpush

@section('content')
<div class="status-page">
    <div class="status-top">
        <div>
            <div class="logo" style="font-size:15px"><span data-ico="heart" data-color="#d6474f" data-size="20"></span>SHOP.GG STATUS</div>
            <div class="chip" style="margin-top:10px">POWERED BY <b>PULSEMONITOR</b></div>
        </div>
        <button class="btn" data-modal="m-sub">SUBSCRIBE</button>
    </div>

    <livewire:public-status />


    <div class="press-start" style="color:var(--dim)">UPDATED <span id="upd">--:--</span> · REFRESHES EVERY 60s</div>
</div>
@endsection

@push('modals')
<div class="modal-bg" id="m-sub">
    <div class="box modal">
        <h2>GET UPDATES</h2>
        <p style="margin:14px 0">We will e-mail you when an incident starts or ends.</p>
        <div class="field"><label>E-MAIL</label><input class="input" type="email" placeholder="you@company.com"></div>
        <div class="actions">
            <button class="btn" data-close>CANCEL</button>
            <button class="btn primary" data-close data-toast="Subscribed! Check your inbox.">SUBSCRIBE</button>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script>
    document.getElementById('upd').textContent = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
</script>
@endpush

@extends('layouts.app')

@section('title', 'Alerts')

@section('content')
<div class="head">
    <div>
        <h1>ALERTS</h1>
        <p class="sub">3 need your attention.</p>
    </div>
</div>

<section class="box">
    <div class="tabs mb">
        <a class="active" href="#">ACTIVE</a>
        <a href="#">ACKNOWLEDGED</a>
        <a href="#">RESOLVED</a>
    </div>

    @foreach ([
        ['a1', 'skull', '#d6474f', 'Payments API is down', 'Monitor · timeout after 10s · today 14:03', true],
        ['a2', 'bolt', '#d6474f', 'payments: 1,908 errors in 15 min', 'Application · error rate 21% · today 14:05', true],
        ['a3', 'stopwatch', '#e9a23b', 'Webhook Relay latency above 500ms', 'Monitor · p95 612ms · today 13:58', true],
        ['a4', 'check', '#6b7194', 'search-index slow queries', 'Acknowledged by arthur at 12:40', false],
    ] as [$id, $icon, $color, $title, $meta, $active])
        <div @class(['alert', 'done' => ! $active]) id="{{ $id }}">
            <span data-ico="{{ $icon }}" data-color="{{ $color }}" data-size="26"></span>
            <div>
                <div class="title">{{ $title }}</div>
                <div class="meta">{{ $meta }}</div>
            </div>
            @if ($active)
                <button class="btn sm" onclick="ack('{{ $id }}')">ACKNOWLEDGE</button>
            @else
                <span class="px muted">ACK</span>
            @endif
        </div>
    @endforeach
</section>
@endsection

@push('scripts')
<script>
    function ack(id) {
        const el = document.getElementById(id);
        el.classList.add('done');
        el.querySelector('.btn').outerHTML = '<span class="px muted">ACK</span>';
        PM.toast('Alert acknowledged.');
    }
</script>
@endpush

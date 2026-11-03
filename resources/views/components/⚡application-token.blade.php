<?php

use App\Models\Application;
use Livewire\Component;

new class extends Component
{
    public Application $application;
    public ?string $token = null; // plain token, only right after regenerating

    public function regenerate()
    {
        abort_unless($this->application->user_id === auth()->id(), 403);

        $this->token = $this->application->generateToken();

        $this->js("document.getElementById('m-regen').classList.remove('open'); PM.toast('New token generated!')");
    }
};
?>

<div>
<section class="box">
    <div class="box-title"><h2>INTEGRATION</h2></div>
    <dl class="kv">
        <div><dt>APPLICATION ID</dt><dd><code>{{ $application->public_id }}</code></dd></div>
        <div>
            <dt>API TOKEN</dt>
            <dd>
                @if ($token)
                    <code>{{ $token }}</code>
                    <button class="btn sm" data-copy="{{ $token }}" data-label="Token copied!">COPY</button>
                @else
                    <code>{{ $application->maskedToken() }}</code>
                @endif
            </dd>
        </div>
        <div><dt>WEBHOOK URL</dt><dd><code>{{ $application->webhookUrl() }}</code><button class="btn sm" data-copy="{{ $application->webhookUrl() }}" data-label="Webhook copied!">COPY</button></dd></div>
    </dl>
    @if ($token)
        <p class="small c-bad" style="margin:14px 0 0">Copy the token now. It won't be shown again.</p>
    @endif
</section>

    <div class="modal-bg" id="m-regen" wire:ignore.self>
        <div class="box modal">
            <h2>REGENERATE TOKEN?</h2>
            <p style="margin:14px 0 0">The current token stops working right away. Every integration using it will get <b class="c-bad" style="font-weight:400">401 Unauthorized</b>.</p>
            <div class="actions">
                <button class="btn" data-close>CANCEL</button>
                <button class="btn danger" wire:click="regenerate">REGENERATE</button>
            </div>
        </div>
    </div>
</div>

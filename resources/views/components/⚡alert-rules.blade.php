<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public int|string $latency_limit_ms = 500;
    public float|string $error_rate_limit = 1.0;
    public bool $notify_email = true;
    public bool $notify_slack = false;
    public bool $notify_webhook = false;
    public ?string $slack_webhook_url = null;
    public ?string $webhook_url = null;

    public function mount()
    {
        $this->fill(Auth::user()->alertRule->only([
            'latency_limit_ms', 'error_rate_limit', 'notify_email', 'notify_slack', 'notify_webhook',
            'slack_webhook_url', 'webhook_url',
        ]));
    }

    public function save()
    {
        $data = $this->validate([
            'latency_limit_ms' => 'required|integer|min:50|max:60000',
            'error_rate_limit' => 'required|numeric|min:0|max:100',
            'notify_email' => 'boolean',
            'notify_slack' => 'boolean',
            'notify_webhook' => 'boolean',
            'slack_webhook_url' => 'nullable|url|max:255|required_if:notify_slack,true',
            'webhook_url' => 'nullable|url|max:255|required_if:notify_webhook,true',
        ], [
            'slack_webhook_url.required_if' => 'Add the Slack webhook URL to notify by Slack.',
            'webhook_url.required_if' => 'Add a URL to notify by webhook.',
        ]);

        Auth::user()->alertRule()->updateOrCreate([], $data);

        $this->js("PM.toast('Alert rules saved!')");
    }
};
?>

<section class="box">
    <div class="box-title"><h2>ALERT RULES</h2></div>
    <form wire:submit="save">
        <div class="field">
            <label for="latency">LATENCY LIMIT (MS)</label>
            <input class="input" id="latency" type="number" wire:model="latency_limit_ms">
            @error('latency_limit_ms') <div class="error">{{ $message }}</div> @enderror
        </div>
        <div class="field">
            <label for="error_rate">ERROR RATE LIMIT (%)</label>
            <input class="input" id="error_rate" type="number" step="0.01" wire:model="error_rate_limit">
            @error('error_rate_limit') <div class="error">{{ $message }}</div> @enderror
        </div>
        <div class="field">
            <label>NOTIFY BY</label>
            <div class="choices">
                <label><input type="checkbox" wire:model="notify_email"><span>E-MAIL</span></label>
                <label><input type="checkbox" wire:model.live="notify_slack"><span>SLACK</span></label>
                <label><input type="checkbox" wire:model.live="notify_webhook"><span>WEBHOOK</span></label>
            </div>
        </div>
        @if ($notify_slack)
            <div class="field">
                <label for="slack_url">SLACK WEBHOOK URL</label>
                <input class="input" id="slack_url" type="url" wire:model="slack_webhook_url" placeholder="https://hooks.slack.com/services/...">
                @error('slack_webhook_url') <div class="error">{{ $message }}</div> @enderror
            </div>
        @endif
        @if ($notify_webhook)
            <div class="field">
                <label for="webhook_url">WEBHOOK URL</label>
                <input class="input" id="webhook_url" type="url" wire:model="webhook_url" placeholder="https://example.com/pulse-alerts">
                <div class="hint">We POST a JSON body when an alert opens or resolves.</div>
                @error('webhook_url') <div class="error">{{ $message }}</div> @enderror
            </div>
        @endif
        <div class="row" style="justify-content:flex-end"><button class="btn primary" type="submit">SAVE</button></div>
    </form>
</section>

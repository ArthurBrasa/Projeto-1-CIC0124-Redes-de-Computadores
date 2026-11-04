<?php

use App\Models\Alert;
use App\Notifications\AlertNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    #[Url(except: 'active')]
    public string $tab = 'active';

    /** Alert being acknowledged (modal open when set). */
    public ?int $ackId = null;
    public string $note = '';

    public function getListeners()
    {
        return ['echo-private:App.Models.User.'.Auth::id().',.alert.changed' => '$refresh'];
    }

    public function setTab(string $tab)
    {
        $this->tab = in_array($tab, [Alert::ACTIVE, Alert::ACKNOWLEDGED, Alert::RESOLVED]) ? $tab : Alert::ACTIVE;
    }

    #[Computed]
    public function counts(): array
    {
        $rows = Auth::user()->alerts()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            Alert::ACTIVE => (int) ($rows[Alert::ACTIVE] ?? 0),
            Alert::ACKNOWLEDGED => (int) ($rows[Alert::ACKNOWLEDGED] ?? 0),
            Alert::RESOLVED => (int) ($rows[Alert::RESOLVED] ?? 0),
        ];
    }

    #[Computed]
    public function alerts()
    {
        return Auth::user()->alerts()
            ->with(['alertable', 'acknowledger'])
            ->where('status', $this->tab)
            ->orderByDesc($this->tab === Alert::RESOLVED ? 'resolved_at' : 'started_at')
            ->limit(50)
            ->get();
    }

    public function startAck(int $id)
    {
        $this->ackId = $id;
        $this->note = '';
    }

    public function cancelAck()
    {
        $this->ackId = null;
    }

    public function acknowledge()
    {
        $this->validate(['note' => 'nullable|string|max:500']);

        $alert = Auth::user()->alerts()->findOrFail($this->ackId);

        if ($alert->status === Alert::ACTIVE) {
            $alert->update([
                'status' => Alert::ACKNOWLEDGED,
                'acknowledged_at' => now(),
                'acknowledged_by' => Auth::id(),
                'note' => $this->note ?: null,
            ]);
        }

        $this->ackId = null;
        $this->js("PM.toast('Alert acknowledged.')");
    }

    /** Close it by hand. If the problem is still there, the next check opens a new one. */
    public function resolve(int $id)
    {
        $alert = Auth::user()->alerts()->findOrFail($id);

        if ($alert->isOpen()) {
            $alert->update(['status' => Alert::RESOLVED, 'resolved_at' => now()]);
            $alert->user->notify(new AlertNotification($alert, 'resolved'));
        }

        $this->js("PM.toast('Alert resolved.')");
    }
};
?>

<div>
    <div class="head">
        <div>
            <h1>ALERTS</h1>
            <p class="sub">
                @if ($this->counts['active'])
                    {{ $this->counts['active'] }} {{ $this->counts['active'] === 1 ? 'needs' : 'need' }} your attention.
                @else
                    All quiet. Nothing needs your attention.
                @endif
            </p>
        </div>
    </div>

    <section class="box">
        <div class="tabs mb">
            @foreach (['active' => 'ACTIVE', 'acknowledged' => 'ACKNOWLEDGED', 'resolved' => 'RESOLVED'] as $key => $label)
                <button @class(['active' => $tab === $key]) wire:click="setTab('{{ $key }}')">{{ $label }} ({{ $this->counts[$key] }})</button>
            @endforeach
        </div>

        @forelse ($this->alerts as $alert)
            <div @class(['alert', 'done' => $alert->status === 'resolved']) wire:key="alert-{{ $alert->id }}">
                <span data-ico="{{ ['monitor_down' => 'skull', 'latency' => 'stopwatch', 'error_rate' => 'bolt'][$alert->type] ?? 'alerts' }}"
                      data-color="{{ $alert->severity() === 'critical' ? '#d6474f' : '#e9a23b' }}" data-size="26"></span>
                <div>
                    <div class="row" style="gap:10px">
                        <span class="tag {{ $alert->severity() === 'critical' ? 'c-bad' : 'c-warn' }}">{{ $alert->typeLabel() }}</span>
                        <span class="title">{{ $alert->title }}</span>
                    </div>
                    <div class="meta">
                        {{ $alert->summary() }}
                        · started {{ $alert->started_at->local()->format('M d, H:i') }}
                        · {{ $alert->status === 'resolved' ? 'lasted' : 'for' }} {{ $alert->duration() }}
                        @if ($alert->subjectUrl())
                            · <a class="link" href="{{ $alert->subjectUrl() }}">OPEN</a>
                        @endif
                    </div>
                    @if ($alert->acknowledged_at)
                        <div class="meta">
                            Acknowledged by {{ $alert->acknowledger?->firstName() ?? 'someone' }} at {{ $alert->acknowledged_at->local()->format('H:i') }}@if ($alert->note): "{{ $alert->note }}"@endif
                        </div>
                    @endif
                </div>
                <div class="row" style="gap:6px">
                    @if ($alert->status === 'active')
                        <button class="btn sm primary" wire:click="startAck({{ $alert->id }})">ACKNOWLEDGE</button>
                    @endif
                    @if ($alert->isOpen())
                        <button class="btn sm" wire:click="resolve({{ $alert->id }})" wire:confirm="Mark this alert as resolved?">RESOLVE</button>
                    @endif
                </div>
            </div>
        @empty
            <p class="muted" style="margin:0">
                {{ ['active' => 'No active alerts. Everything is running.', 'acknowledged' => 'No acknowledged alerts.', 'resolved' => 'No resolved alerts yet.'][$tab] }}
            </p>
        @endforelse
    </section>

    @if ($ackId)
        <div class="modal-bg open">
            <div class="box modal">
                <h2>ACKNOWLEDGE ALERT</h2>
                <p style="margin:14px 0">You are taking care of this alert. Notifications stop while it is acknowledged; it still closes by itself when the problem ends.</p>
                <div class="field">
                    <label for="note">NOTE (OPTIONAL)</label>
                    <textarea class="input" id="note" wire:model="note" placeholder="Investigating gateway timeouts..."></textarea>
                    @error('note') <div class="error">{{ $message }}</div> @enderror
                </div>
                <div class="actions">
                    <button class="btn" wire:click="cancelAck">CANCEL</button>
                    <button class="btn primary" wire:click="acknowledge">ACKNOWLEDGE</button>
                </div>
            </div>
        </div>
    @endif
</div>

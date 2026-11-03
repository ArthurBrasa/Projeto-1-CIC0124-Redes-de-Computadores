<?php

use App\Models\Application;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public Application $application;

    #[Computed]
    public function events()
    {
        return $this->application->events()->latest('id')->limit(10)->get();
    }

    #[On('event-created')]
    public function refresh() {}
};
?>

<section class="box dark" wire:poll.5s>
    <div class="box-title"><h2>RECENT LOGS</h2><a class="link" href="{{ route('events.index') }}">ALL EVENTS</a></div>

    @if ($this->events->isEmpty())
        <p class="muted" style="margin:0">No events yet. Send one to the webhook or press TEST INTEGRATION.</p>
    @else
        <div class="log">
            @foreach ($this->events as $event)
                <div class="line" wire:key="event-{{ $event->id }}">
                    <span class="ts">{{ $event->created_at->local()->format('H:i:s') }}</span>
                    <span class="lvl {{ strtoupper($event->level) }}">{{ $event->level === 'warning' ? 'WARN' : strtoupper($event->level) }}</span>
                    <span class="src">{{ $event->source ?? $application->name }}</span>
                    <span>
                        {{ $event->message }}
                        @if ($event->payload)
                            <details wire:ignore><summary>payload</summary><pre>{{ json_encode($event->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>
                        @endif
                    </span>
                </div>
            @endforeach
        </div>
    @endif
</section>

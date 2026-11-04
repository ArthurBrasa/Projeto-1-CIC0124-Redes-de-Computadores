<?php

use App\Models\ApplicationEvent;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/* LIVE ACTIVITY: the latest events of all the user's applications. */
new class extends Component
{
    public function getListeners()
    {
        return ['echo-private:App.Models.User.'.Auth::id().',.application-event.received' => '$refresh'];
    }

    #[Computed]
    public function events()
    {
        return ApplicationEvent::whereIn('application_id', Auth::user()->applications()->pluck('id'))
            ->with('application:id,name')
            ->latest('id')
            ->limit(6)
            ->get();
    }
};
?>

<section class="box dark">
    <div class="box-title"><h2>LIVE ACTIVITY</h2><a class="link" href="{{ route('events.index') }}">ALL EVENTS</a></div>

    @if ($this->events->isEmpty())
        <p class="muted" style="margin:0">No events yet. Send one to an application webhook to see it here.</p>
    @else
        <div class="log">
            @foreach ($this->events as $event)
                <div class="line" wire:key="act-{{ $event->id }}">
                    <span class="ts">{{ $event->created_at->local()->format('H:i:s') }}</span>
                    <span class="lvl {{ strtoupper($event->level) }}">{{ $event->level === 'warning' ? 'WARN' : strtoupper($event->level) }}</span>
                    <span class="src">{{ $event->application->name }}</span>
                    <span>{{ $event->message }}</span>
                </div>
            @endforeach
        </div>
    @endif
</section>

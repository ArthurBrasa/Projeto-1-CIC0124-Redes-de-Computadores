<?php

namespace App\Events;

use App\Models\ApplicationEvent;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Sent to the app owner's private channel whenever an application receives an event.
 * ShouldBroadcastNow: no queue worker needed. ShouldRescue: if Reverb is down, the
 * webhook still works (the error is only logged).
 */
class ApplicationEventReceived implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;

    public function __construct(public ApplicationEvent $event) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->event->application->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'application-event.received';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->event->id,
            'application_id' => $this->event->application_id,
            'level' => $this->event->level,
        ];
    }
}

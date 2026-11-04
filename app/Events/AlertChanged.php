<?php

namespace App\Events;

use App\Models\Alert;
use App\Models\Monitor;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An alert was opened, acknowledged or resolved.
 * Owner's private channel always; public "status" channel for public monitors' downtime.
 */
class AlertChanged implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;

    public function __construct(public Alert $alert) {}

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('App.Models.User.'.$this->alert->user_id)];

        if ($this->alert->type === Alert::MONITOR_DOWN && $this->alert->alertable instanceof Monitor && $this->alert->alertable->is_public) {
            $channels[] = new Channel('status');
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'alert.changed';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->alert->id, 'status' => $this->alert->status, 'type' => $this->alert->type];
    }
}

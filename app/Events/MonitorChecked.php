<?php

namespace App\Events;

use App\Models\MonitorCheck;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Sent after every monitor check: to the owner's private channel and,
 * for public monitors, to the public "status" channel used by the status page.
 */
class MonitorChecked implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;

    public function __construct(public MonitorCheck $check) {}

    public function broadcastOn(): array
    {
        $monitor = $this->check->monitor;
        $channels = [new PrivateChannel('App.Models.User.'.$monitor->user_id)];

        if ($monitor->is_public) {
            $channels[] = new Channel('status');
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'monitor.checked';
    }

    public function broadcastWith(): array
    {
        return [
            'monitor_id' => $this->check->monitor_id,
            'is_up' => $this->check->is_up,
        ];
    }
}

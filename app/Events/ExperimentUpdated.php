<?php

namespace App\Events;

use App\Models\NetworkExperiment;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/** Progress / results of a Network Lab experiment, to its owner's private channel. */
class ExperimentUpdated implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;

    public function __construct(public NetworkExperiment $experiment) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->experiment->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'experiment.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->experiment->id,
            'status' => $this->experiment->status,
            'progress' => $this->experiment->progress,
        ];
    }
}

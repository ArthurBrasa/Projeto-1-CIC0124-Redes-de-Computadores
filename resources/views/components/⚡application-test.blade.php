<?php

use App\Models\Application;
use Livewire\Component;

new class extends Component
{
    public Application $application;

    public function send()
    {
        abort_unless($this->application->user_id === auth()->id(), 403);

        $this->application->events()->create([
            'level' => 'info',
            'message' => 'Test event from PULSEMONITOR',
            'source' => 'pulsemonitor',
            'payload' => ['test' => true, 'sent_at' => now()->toIso8601String()],
        ]);

        $this->dispatch('event-created');
        $this->js("PM.toast('Test event received!')");
    }
};
?>

<button class="btn" wire:click="send" wire:loading.attr="disabled">TEST INTEGRATION</button>

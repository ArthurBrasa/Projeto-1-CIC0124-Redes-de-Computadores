<?php

use App\Models\Application;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public Application $application;

    /** Event count per level in the last 24h. */
    #[Computed]
    public function counts(): array
    {
        $rows = $this->application->events()
            ->where('created_at', '>=', now()->subDay())
            ->selectRaw('level, count(*) as total')
            ->groupBy('level')
            ->pluck('total', 'level');

        return [
            'info' => (int) ($rows['info'] ?? 0),
            'warning' => (int) ($rows['warning'] ?? 0),
            'error' => (int) ($rows['error'] ?? 0),
        ];
    }

    #[On('event-created')]
    public function refresh() {}
};
?>

<section class="box" wire:poll.10s>
    <div class="box-title"><h2>EVENTS BY LEVEL</h2><span class="px muted">24H</span></div>
    <dl class="stats">
        <div><dt>INFO</dt><dd>{{ number_format($this->counts['info']) }}</dd></div>
        <div><dt class="c-warn">WARNING</dt><dd>{{ number_format($this->counts['warning']) }}</dd></div>
        <div><dt class="c-bad">ERROR</dt><dd>{{ number_format($this->counts['error']) }}</dd></div>
    </dl>
</section>

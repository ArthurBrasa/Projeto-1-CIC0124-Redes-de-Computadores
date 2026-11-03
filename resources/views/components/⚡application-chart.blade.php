<?php

use App\Models\Application;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public Application $application;

    /** @var array<int, int> events per minute, oldest first (60 buckets) */
    public array $series = [];

    public function mount()
    {
        $this->load();
    }

    #[On('event-created')]
    public function load()
    {
        $from = now()->subMinutes(59)->startOfMinute();
        $buckets = array_fill(0, 60, 0);

        $times = $this->application->events()
            ->where('created_at', '>=', $from)
            ->pluck('created_at');

        foreach ($times as $time) {
            $i = (int) $from->diffInMinutes(Carbon::parse($time)->startOfMinute());

            if ($i >= 0 && $i < 60) {
                $buckets[$i]++;
            }
        }

        $this->series = $buckets;
    }
};
?>

<section class="box" wire:poll.15s="load">
    <div class="box-title"><h2>EVENTS PER MINUTE</h2><span class="px muted">LAST 60 MIN</span></div>
    <div class="chart" wire:ignore><canvas></canvas></div>
</section>

@script
<script>
    const canvas = $wire.$el.querySelector('canvas');
    const draw = () => PM.barChart(canvas, $wire.series, {
        xlabels: ['-60m', '-45m', '-30m', '-15m', 'now'],
        colorFn: v => v > 100 ? PM.C.warn : PM.C.hp,
    });
    draw();
    $wire.$watch('series', draw);
    window.addEventListener('resize', draw);
</script>
@endscript

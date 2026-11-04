<?php

use App\Models\MonitorCheck;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/* PARTY box: monitors with their 24h uptime as an HP bar, down ones first. */
new class extends Component
{
    public function getListeners()
    {
        return ['echo-private:App.Models.User.'.Auth::id().',.monitor.checked' => '$refresh'];
    }

    #[Computed]
    public function party()
    {
        $monitors = Auth::user()->monitors()->where('is_active', true)->with('latestCheck')->get();

        $uptime = MonitorCheck::whereIn('monitor_id', $monitors->pluck('id'))
            ->where('checked_at', '>=', now()->subDay())
            ->selectRaw('monitor_id, count(*) as total, sum(case when is_up then 1 else 0 end) as up')
            ->groupBy('monitor_id')
            ->get()
            ->keyBy('monitor_id');

        return $monitors
            ->map(function ($m) use ($uptime) {
                $row = $uptime[$m->id] ?? null;
                $m->hp = $row && $row->total ? round($row->up / $row->total * 100, 1) : null;
                $m->state = ! $m->latestCheck ? 'off' : ($m->latestCheck->is_up ? 'up' : 'down');

                return $m;
            })
            ->sortBy([fn ($a, $b) => ($a->state === 'down' ? 0 : 1) <=> ($b->state === 'down' ? 0 : 1), ['name', 'asc']])
            ->take(6)
            ->values();
    }
};
?>

<section class="box">
    <div class="box-title"><h2>PARTY</h2><a class="link" href="{{ route('monitors.index') }}">ALL</a></div>
    @if ($this->party->isEmpty())
        <p class="muted" style="margin:0">No monitors yet. <a class="link" href="{{ route('monitors.create') }}">ADD ONE</a></p>
    @else
        <div class="party">
            @foreach ($this->party as $m)
                <a href="{{ route('monitors.show', $m) }}" style="grid-template-columns:minmax(0,1fr) auto" wire:key="pm-{{ $m->id }}">
                    <div>
                        <div class="name">{{ $m->name }}</div>
                        <div @class(['hp', 'mid' => $m->hp !== null && $m->hp < 99 && $m->hp >= 95, 'low' => $m->hp !== null && $m->hp < 95])>
                            <span>HP</span><div class="bar"><i style="width:{{ $m->hp ?? 0 }}%"></i></div>
                        </div>
                        <div class="meta">{{ $m->hp !== null ? $m->hp.'% uptime 24h' : 'no checks yet' }}</div>
                    </div>
                    <span class="st {{ $m->state }}"></span>
                </a>
            @endforeach
        </div>
    @endif
</section>

<?php

use App\Models\ApplicationEvent;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $app = ''; // application public_id, empty = all

    #[Url(except: ['info', 'warning', 'error'])]
    public array $levels = ['info', 'warning', 'error'];

    #[Url(except: '24h')]
    public string $period = '24h';

    public int $limit = 50;

    public bool $live = true;

    /** Any filter change starts the list from the top again. */
    public function updated($property)
    {
        $this->limit = 50;
    }

    public function loadMore()
    {
        $this->limit += 50;
    }

    public function toggleLive()
    {
        $this->live = ! $this->live;
    }

    public function getListeners()
    {
        return ['echo-private:App.Models.User.'.auth()->id().',.application-event.received' => 'onEvent'];
    }

    /** New event on one of the user's apps: refresh the list unless paused. */
    public function onEvent()
    {
        if (! $this->live) {
            $this->skipRender();
        }
    }

    #[Computed]
    public function apps()
    {
        return Auth::user()->applications()->orderBy('name')->get(['id', 'public_id', 'name']);
    }

    /** Events of the user's apps, filtered by app, period and search (not by level). */
    private function baseQuery()
    {
        $apps = $this->app === '' ? $this->apps : $this->apps->where('public_id', $this->app);

        $since = match ($this->period) {
            '1h' => now()->subHour(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subDay(),
        };

        return ApplicationEvent::query()
            ->whereIn('application_id', $apps->pluck('id'))
            ->where('created_at', '>=', $since)
            ->when(trim($this->search) !== '', fn ($q) => $q->where('message', 'like', '%'.trim($this->search).'%'));
    }

    #[Computed]
    public function counts(): array
    {
        $rows = $this->baseQuery()->selectRaw('level, count(*) as total')->groupBy('level')->pluck('total', 'level');

        return collect(ApplicationEvent::LEVELS)->mapWithKeys(fn ($l) => [$l => (int) ($rows[$l] ?? 0)])->all();
    }

    #[Computed]
    public function events()
    {
        return $this->baseQuery()
            ->whereIn('level', array_intersect($this->levels, ApplicationEvent::LEVELS))
            ->with('application:id,name')
            ->latest('id')
            ->limit($this->limit + 1)
            ->get();
    }
};
?>

<div>
    <div class="head">
        <div>
            <h1>EVENTS &amp; LOGS</h1>
            <p class="sub">
                {{ number_format(array_sum($this->counts)) }} events ·
                <span class="c-info">{{ number_format($this->counts['info']) }} info</span> ·
                <span class="c-warn">{{ number_format($this->counts['warning']) }} warn</span> ·
                <span class="c-bad">{{ number_format($this->counts['error']) }} error</span>
            </p>
        </div>
        <div class="row">
            @if ($live)
                <span class="st up">LIVE</span>
            @else
                <span class="st off">PAUSED</span>
            @endif
            <button class="btn" wire:click="toggleLive">{{ $live ? 'PAUSE' : 'RESUME' }}</button>
        </div>
    </div>

    <section class="box mb">
        <div class="row">
            <input class="input" style="flex:1;min-width:220px" placeholder="Search message..." wire:model.live.debounce.400ms="search">
            <div wire:ignore>
                <select class="input" style="width:auto" wire:model.live="app">
                    <option value="" @selected($app === '')>All apps</option>
                    @foreach ($this->apps as $a)
                        <option value="{{ $a->public_id }}" @selected($app === $a->public_id)>{{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div wire:ignore>
                <select class="input" style="width:auto" wire:model.live="period">
                    @foreach (['1h' => 'Last hour', '24h' => 'Last 24 hours', '7d' => 'Last 7 days', '30d' => 'Last 30 days'] as $value => $label)
                        <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="row between mt">
            <div class="choices">
                <label><input type="checkbox" value="info" wire:model.live="levels"><span>INFO</span></label>
                <label><input type="checkbox" value="warning" wire:model.live="levels"><span>WARN</span></label>
                <label><input type="checkbox" value="error" wire:model.live="levels"><span>ERROR</span></label>
            </div>
            <a class="link" href="{{ route('events.index') }}">CLEAR FILTERS</a>
        </div>
    </section>

    <section class="box dark">
        @php($events = $this->events->take($limit))

        @if ($events->isEmpty())
            <p class="muted" style="margin:0">
                @if ($this->apps->isEmpty())
                    No applications yet. Register one to start receiving events.
                @else
                    No events match these filters.
                @endif
            </p>
        @else
            <div class="log">
                <div class="line log-head" style="grid-template-columns:150px 64px 190px minmax(0,1fr)"><span>DATE</span><span>LEVEL</span><span>ORIGIN</span><span>MESSAGE</span></div>
                @foreach ($events as $event)
                    <div class="line" wire:key="ev-{{ $event->id }}" style="grid-template-columns:150px 64px 190px minmax(0,1fr)">
                        <span class="ts">{{ $event->created_at->local()->format('d/m H:i:s') }}</span>
                        <span class="lvl {{ strtoupper($event->level) }}">{{ $event->level === 'warning' ? 'WARN' : strtoupper($event->level) }}</span>
                        <span class="src" title="{{ $event->application->name }}{{ $event->source ? ' · '.$event->source : '' }}">
                            {{ $event->application->name }}@if ($event->source)<span style="opacity:.6"> · {{ $event->source }}</span>@endif
                        </span>
                        <span>
                            {{ $event->message }}
                            @if ($event->payload)
                                <details wire:ignore><summary>payload</summary><pre>{{ json_encode($event->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>

            @if ($this->events->count() > $limit)
                <div class="row mt" style="justify-content:center">
                    <button class="btn primary" wire:click="loadMore">LOAD MORE</button>
                </div>
            @endif
        @endif
    </section>
</div>

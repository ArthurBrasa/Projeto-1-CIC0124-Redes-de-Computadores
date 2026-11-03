<?php

use App\Models\Monitor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public ?Monitor $monitor = null;

    public string $name = '';
    public string $url = '';
    public string $method = 'GET';
    public int|string $interval_seconds = 60;
    public int|string $timeout_seconds = 10;
    public int|string $expected_status = 200;
    public bool $is_public = false;

    public function mount(?Monitor $monitor = null)
    {
        if ($monitor?->exists) {
            abort_unless($monitor->user_id === Auth::id(), 404);

            $this->monitor = $monitor;
            $this->fill($monitor->only(['name', 'url', 'method', 'interval_seconds', 'timeout_seconds', 'expected_status', 'is_public']));
        }
    }

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'url' => 'required|url|max:255',
            'method' => ['required', Rule::in(Monitor::METHODS)],
            'interval_seconds' => ['required', 'integer', Rule::in(array_keys(Monitor::INTERVALS))],
            'timeout_seconds' => ['required', 'integer', Rule::in(array_keys(Monitor::TIMEOUTS)), 'lt:interval_seconds'],
            'expected_status' => 'required|integer|between:100,599',
            'is_public' => 'boolean',
        ];
    }

    protected function messages(): array
    {
        return ['timeout_seconds.lt' => 'Timeout must be shorter than the check interval.'];
    }

    public function save()
    {
        $data = $this->validate();

        if ($this->monitor) {
            $this->monitor->update($data);
            $monitor = $this->monitor;
        } else {
            $monitor = Auth::user()->monitors()->create($data);
        }

        session()->flash('toast', $this->monitor ? 'Monitor updated!' : 'Monitor created!');

        return redirect()->route('monitors.show', $monitor);
    }

    /** Run one real request against the URL, without saving. */
    public function test()
    {
        $this->validateOnly('url');
        $this->validateOnly('method');

        $started = microtime(true);

        try {
            $response = Http::timeout((int) $this->timeout_seconds)->send($this->method, $this->url);
            $ms = (int) round((microtime(true) - $started) * 1000);
            $ok = $response->status() === (int) $this->expected_status;
            $msg = ($ok ? 'OK! ' : 'Unexpected status: ').$response->status().' in '.$ms.'ms';
        } catch (\Throwable $e) {
            $msg = 'Failed: '.str($e->getMessage())->limit(60);
        }

        $this->js('PM.toast('.json_encode($msg).')');
    }
};
?>

<form class="box center" wire:submit="save">
    <div class="field">
        <label for="name">NAME</label>
        <input class="input" id="name" wire:model="name" placeholder="Payments API">
        @error('name') <div class="error">{{ $message }}</div> @enderror
    </div>
    <div class="field">
        <label for="url">URL</label>
        <input class="input" id="url" type="url" wire:model="url" placeholder="https://api.example.com/health">
        @error('url') <div class="error">{{ $message }}</div> @enderror
    </div>
    <div class="field">
        <label>METHOD</label>
        <div class="choices">
            @foreach (\App\Models\Monitor::METHODS as $m)
                <label><input type="radio" value="{{ $m }}" wire:model="method"><span>{{ $m }}</span></label>
            @endforeach
        </div>
        @error('method') <div class="error">{{ $message }}</div> @enderror
    </div>
    <div class="fields-2">
        <div class="field">
            <label for="interval">CHECK EVERY</label>
            <div wire:ignore>
                <select class="input" id="interval" wire:model="interval_seconds">
                    @foreach (\App\Models\Monitor::INTERVALS as $value => $label)
                        <option value="{{ $value }}" @selected($value == $interval_seconds)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @error('interval_seconds') <div class="error">{{ $message }}</div> @enderror
        </div>
        <div class="field">
            <label for="timeout">TIMEOUT</label>
            <div wire:ignore>
                <select class="input" id="timeout" wire:model="timeout_seconds">
                    @foreach (\App\Models\Monitor::TIMEOUTS as $value => $label)
                        <option value="{{ $value }}" @selected($value == $timeout_seconds)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @error('timeout_seconds') <div class="error">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="field">
        <label for="status">EXPECTED STATUS CODE</label>
        <input class="input" id="status" type="number" wire:model="expected_status" style="max-width:140px">
        @error('expected_status') <div class="error">{{ $message }}</div> @enderror
    </div>
    <div class="field">
        <div class="choices"><label><input type="checkbox" wire:model="is_public"><span>SHOW ON PUBLIC STATUS PAGE</span></label></div>
    </div>
    <div class="row" style="justify-content:flex-end">
        <a class="btn" href="{{ $monitor ? route('monitors.show', $monitor) : route('monitors.index') }}">CANCEL</a>
        <button class="btn" type="button" wire:click="test" wire:loading.attr="disabled" wire:target="test">TEST NOW</button>
        <button class="btn primary" type="submit">{{ $monitor ? 'SAVE CHANGES' : 'CREATE MONITOR' }}</button>
    </div>
</form>

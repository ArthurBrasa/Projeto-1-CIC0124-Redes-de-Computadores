<?php

use App\Models\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public string $name = '';
    public string $description = '';
    public string $environment = 'production';
    public string $url = '';

    public ?Application $application = null;
    public ?string $token = null; // plain token, shown only after creating / regenerating

    public function save()
    {
        $data = $this->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'environment' => ['required', Rule::in(Application::ENVIRONMENTS)],
            'url' => 'required|url|max:255',
        ]);

        $this->application = Auth::user()->applications()->create($data);
        $this->token = $this->application->generateToken();

        $this->js("PM.toast('Application registered!')");
    }

    public function regenerate()
    {
        $this->token = $this->application->generateToken();

        $this->js("PM.toast('New token generated!')");
    }
};
?>

<div class="cols form">
    <form class="box" wire:submit="save">
        <fieldset style="all:unset;display:block" @disabled($application)>
            <div class="field">
                <label for="name">NAME</label>
                <input class="input" id="name" wire:model="name" placeholder="checkout-api">
                @error('name') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="description">DESCRIPTION</label>
                <textarea class="input" id="description" wire:model="description" placeholder="What does this application do?"></textarea>
                @error('description') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label>ENVIRONMENT</label>
                <div class="choices">
                    @foreach (\App\Models\Application::ENVIRONMENTS as $env)
                        <label><input type="radio" value="{{ $env }}" wire:model="environment"><span>{{ strtoupper($env) }}</span></label>
                    @endforeach
                </div>
                @error('environment') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="url">URL</label>
                <input class="input" id="url" type="url" wire:model="url" placeholder="https://api.example.com">
                @error('url') <div class="error">{{ $message }}</div> @enderror
            </div>
        </fieldset>

        <div class="row" style="justify-content:flex-end">
            @if ($application)
                <a class="btn" href="{{ route('applications.create') }}">REGISTER ANOTHER</a>
                <a class="btn primary" href="{{ route('applications.show', $application) }}">OPEN APPLICATION</a>
            @else
                <a class="btn" href="{{ route('applications.index') }}">CANCEL</a>
                <button class="btn primary" type="submit">REGISTER</button>
            @endif
        </div>
    </form>

    <div class="stack">
        @if (! $application)
            <div class="box dialog">
                <p style="margin:0">Fill in the form. Your App ID, token and webhook will appear here.</p>
            </div>
        @else
            <section class="box">
                <div class="box-title"><h2>CREDENTIALS</h2><span class="st up">ACTIVE</span></div>
                <dl class="kv">
                    <div><dt>APPLICATION ID</dt><dd><code>{{ $application->public_id }}</code></dd></div>
                    <div><dt>API TOKEN</dt><dd><code>{{ $token }}</code></dd></div>
                    <div><dt>WEBHOOK ENDPOINT</dt><dd><code>{{ $application->webhookUrl() }}</code></dd></div>
                </dl>
                <p class="small c-bad" style="margin:14px 0 0">Copy the token now. It won't be shown again.</p>
                <div class="row mt">
                    <button class="btn sm" type="button" wire:click="regenerate">GENERATE TOKEN</button>
                    <button class="btn sm" type="button" data-copy="{{ $token }}" data-label="Token copied!">COPY TOKEN</button>
                    <button class="btn sm primary" type="button" data-copy="{{ $application->webhookUrl() }}" data-label="Webhook copied!">COPY WEBHOOK</button>
                </div>
            </section>

            <section class="box dark">
                <div class="box-title"><h2>INTEGRATION EXAMPLE</h2></div>
<pre class="code">curl -X POST {{ $application->webhookUrl() }} \
  -H <span class="s">"Authorization: Bearer {{ $token }}"</span> \
  -H <span class="s">"Content-Type: application/json"</span> \
  -d <span class="s">'{"level":"error","message":"Gateway timeout"}'</span></pre>
            </section>
        @endif
    </div>
</div>

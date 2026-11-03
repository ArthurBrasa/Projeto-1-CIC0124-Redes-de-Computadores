<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component
{
    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    public function login()
    {
        $this->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $key = Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('email', 'Too many attempts. Try again in '.RateLimiter::availableIn($key).'s.');

            return;
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($key);
            $this->addError('email', 'Wrong e-mail or password.');

            return;
        }

        RateLimiter::clear($key);
        session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
};
?>

<div>
    <form wire:submit="login">
        <div class="field">
            <label for="email">E-MAIL</label>
            <input class="input" id="email" type="email" wire:model="email" placeholder="you@company.com">
            @error('email') <div class="error">{{ $message }}</div> @enderror
        </div>
        <div class="field">
            <label for="pass">PASSWORD</label>
            <input class="input" id="pass" type="password" wire:model="password">
            @error('password') <div class="error">{{ $message }}</div> @enderror
        </div>
        <div class="row between" style="margin-bottom:20px">
            <div class="choices"><label><input type="checkbox" wire:model="remember"><span>REMEMBER ME</span></label></div>
            <a class="link" href="#">FORGOT PASSWORD?</a>
        </div>
        <button class="btn primary block" type="submit">ENTER SYSTEM</button>
    </form>
</div>

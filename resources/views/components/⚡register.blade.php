<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

new class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function register()
    {
        $key = 'register|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('email', 'Too many attempts. Try again in '.RateLimiter::availableIn($key).'s.');

            return;
        }

        $data = $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = User::create($data);
        RateLimiter::hit($key, 3600);

        Auth::login($user);
        session()->regenerate();

        return redirect()->route('dashboard');
    }
};
?>

<div>
    <form wire:submit="register">
        <div class="field">
            <label for="name">FULL NAME</label>
            <input class="input" id="name" type="text" wire:model="name" placeholder="Arthur Brasa">
            @error('name') <div class="error">{{ $message }}</div> @enderror
        </div>
        <div class="field">
            <label for="email">E-MAIL</label>
            <input class="input" id="email" type="email" wire:model="email" placeholder="you@company.com">
            @error('email') <div class="error">{{ $message }}</div> @enderror
        </div>
        <div class="field">
            <label for="password">PASSWORD</label>
            <input class="input" id="password" type="password" wire:model="password" placeholder="8+ characters">
            @error('password') <div class="error">{{ $message }}</div> @enderror
        </div>
        <div class="field">
            <label for="password_confirmation">CONFIRM PASSWORD</label>
            <input class="input" id="password_confirmation" type="password" wire:model="password_confirmation">
        </div>
        <button class="btn primary block mt" type="submit">CREATE PLAYER ACCOUNT</button>
    </form>
</div>

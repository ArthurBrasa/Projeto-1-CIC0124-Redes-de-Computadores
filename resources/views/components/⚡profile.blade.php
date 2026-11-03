<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public string $name = '';
    public string $email = '';

    public function mount()
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    public function save()
    {
        $user = Auth::user();

        $data = $this->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ]);

        $user->update($data);

        $this->js("PM.toast('Game saved!')");
    }
};
?>

<section class="box">
    <div class="box-title"><h2>PLAYER</h2></div>
    <form wire:submit="save">
        <div class="field">
            <label for="name">NAME</label>
            <input class="input" id="name" type="text" wire:model="name">
            @error('name') <div class="error">{{ $message }}</div> @enderror
        </div>
        <div class="field">
            <label for="email">E-MAIL</label>
            <input class="input" id="email" type="email" wire:model="email">
            @error('email') <div class="error">{{ $message }}</div> @enderror
        </div>
        <div class="row" style="justify-content:flex-end"><button class="btn primary" type="submit">SAVE</button></div>
    </form>
</section>

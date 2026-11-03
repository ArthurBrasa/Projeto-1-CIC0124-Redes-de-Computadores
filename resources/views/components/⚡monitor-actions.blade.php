<?php

use App\Models\Monitor;
use Livewire\Component;

new class extends Component
{
    public Monitor $monitor;

    public function toggle()
    {
        $this->authorizeOwner();

        $this->monitor->update(['is_active' => ! $this->monitor->is_active]);

        return redirect()->route('monitors.show', $this->monitor);
    }

    public function delete()
    {
        $this->authorizeOwner();

        $this->monitor->delete();

        session()->flash('toast', 'Monitor deleted.');

        return redirect()->route('monitors.index');
    }

    private function authorizeOwner(): void
    {
        abort_unless($this->monitor->user_id === auth()->id(), 403);
    }
};
?>

<div class="row">
    <button class="btn" wire:click="toggle">{{ $monitor->is_active ? 'PAUSE' : 'RESUME' }}</button>
    <a class="btn" href="{{ route('monitors.edit', $monitor) }}">EDIT</a>
    <button class="btn danger" data-modal="m-delete">DELETE</button>

    <div class="modal-bg" id="m-delete" wire:ignore.self>
        <div class="box modal">
            <h2>DELETE MONITOR?</h2>
            <p style="margin:14px 0 0">{{ $monitor->name }} will stop being checked and its history will be lost.</p>
            <div class="actions">
                <button class="btn" data-close>CANCEL</button>
                <button class="btn danger" wire:click="delete">DELETE</button>
            </div>
        </div>
    </div>
</div>

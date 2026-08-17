<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    /**
     * Get the dashboard component matching the signed-in user's role.
     */
    #[Computed]
    public function dashboardComponent(): string
    {
        $user = Auth::user();

        return match (true) {
            $user->isAdministrator() => 'dashboard.admin',
            $user->isRegistrarStaff() => 'dashboard.registrar',
            default => 'dashboard.student',
        };
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <livewire:is :component="$this->dashboardComponent" :wire:key="$this->dashboardComponent" />
</div>

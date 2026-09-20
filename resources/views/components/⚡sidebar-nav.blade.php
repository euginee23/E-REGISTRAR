<?php

use App\Actions\Reports\BuildNavBadgeCounts;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    /**
     * Count what the sidebar should draw attention to.
     *
     * Polled rather than pushed, matching the notification bell: real-time
     * delivery would need a websocket server this deployment avoids.
     *
     * @return array{pendingRequests: int, appointmentsToday: int, pendingAccounts: int, openRequests: int, unreadNotifications: int}
     */
    #[Computed]
    public function badges(): array
    {
        return app(BuildNavBadgeCounts::class)(Auth::user());
    }
}; ?>

<div wire:poll.{{ config('registrar.nav.poll') }} data-test="sidebar-nav">
    @include('partials.nav.' . Auth::user()->role->value, ['badges' => $this->badges])
</div>

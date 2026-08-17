<?php

use App\Models\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    /**
     * Count the notifications the user has not opened.
     *
     * Polled rather than pushed: true real-time delivery would require a
     * websocket server, which this deployment deliberately avoids.
     */
    #[Computed]
    public function unreadCount(): int
    {
        return Auth::user()->unreadNotificationsCount();
    }

    /**
     * Get the most recent unread notifications for the dropdown.
     *
     * @return Collection<int, Notification>
     */
    #[Computed]
    public function latest(): Collection
    {
        return Auth::user()
            ->registrarNotifications()
            ->unread()
            ->take(5)
            ->get();
    }

    /**
     * Refresh the badge after the notifications page marks things read.
     */
    #[On('notifications-read')]
    public function refreshCount(): void
    {
        unset($this->unreadCount, $this->latest);
    }
}; ?>

<flux:dropdown position="bottom" align="end" wire:poll.30s>
    <flux:button variant="subtle" square :aria-label="__('Notifications')" data-test="notification-bell">
        <flux:icon name="bell" variant="outline" class="size-5" />

        @if ($this->unreadCount > 0)
            <flux:badge color="red" size="sm" data-test="notification-count">
                {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
            </flux:badge>
        @endif
    </flux:button>

    <flux:menu class="w-80">
        <div class="px-2 py-1.5">
            <flux:heading size="sm">{{ __('Notifications') }}</flux:heading>
        </div>

        <flux:menu.separator />

        @if ($this->latest->isEmpty())
            <div class="px-2 py-4 text-center">
                <flux:text size="sm" class="text-zinc-500">{{ __('You are all caught up.') }}</flux:text>
            </div>
        @else
            @foreach ($this->latest as $notification)
                <flux:menu.item
                    :href="$notification->url ?? route('notifications.index')"
                    :icon="$notification->type->icon()"
                    wire:key="bell-{{ $notification->id }}"
                >
                    <span class="line-clamp-2 text-sm">{{ $notification->message }}</span>
                </flux:menu.item>
            @endforeach
        @endif

        <flux:menu.separator />

        <flux:menu.item :href="route('notifications.index')" icon="inbox" wire:navigate>
            {{ __('View all notifications') }}
        </flux:menu.item>
    </flux:menu>
</flux:dropdown>

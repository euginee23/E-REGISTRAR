<?php

use App\Models\Notification;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Notifications')] class extends Component {
    use WithPagination;

    #[Url]
    public bool $unreadOnly = false;

    /**
     * Reset paging when the filter changes.
     */
    public function updatedUnreadOnly(): void
    {
        $this->resetPage();
    }

    /**
     * Get the signed-in user's notifications, newest first.
     *
     * @return LengthAwarePaginator<int, Notification>
     */
    #[Computed]
    public function notifications(): LengthAwarePaginator
    {
        return Auth::user()
            ->registrarNotifications()
            ->when($this->unreadOnly, fn ($query) => $query->unread())
            ->paginate(20);
    }

    /**
     * Count the notifications still unopened.
     */
    #[Computed]
    public function unreadCount(): int
    {
        return Auth::user()->unreadNotificationsCount();
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(int $notificationId): void
    {
        Auth::user()
            ->registrarNotifications()
            ->whereKey($notificationId)
            ->firstOrFail()
            ->markAsRead();

        unset($this->notifications, $this->unreadCount);

        $this->dispatch('notifications-read');
    }

    /**
     * Mark every notification as read.
     */
    public function markAllAsRead(): void
    {
        $marked = Auth::user()->markAllNotificationsRead();

        unset($this->notifications, $this->unreadCount);

        $this->dispatch('notifications-read');

        Flux::toast(variant: 'success', text: trans_choice(
            '{0} Nothing to mark.|{1} :count notification marked as read.|[2,*] :count notifications marked as read.',
            $marked,
            ['count' => $marked],
        ));
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <x-page-heading
        :heading="__('Notifications')"
        :subheading="__('Updates on your requests and appointments.')"
    >
        @if ($this->unreadCount > 0)
            <flux:button wire:click="markAllAsRead" variant="ghost" size="sm" data-test="mark-all-read">
                {{ __('Mark all as read') }}
            </flux:button>
        @endif
    </x-page-heading>

    <div class="flex items-center gap-3">
        <flux:switch wire:model.live="unreadOnly" :label="__('Unread only')" data-test="unread-only-toggle" />

        @if ($this->unreadCount > 0)
            <flux:badge color="blue" size="sm">
                {{ trans_choice('{1} :count unread|[2,*] :count unread', $this->unreadCount, ['count' => $this->unreadCount]) }}
            </flux:badge>
        @endif
    </div>

    @if ($this->notifications->isEmpty())
        <x-empty-state
            icon="bell"
            :heading="$unreadOnly ? __('Nothing unread') : __('No notifications yet')"
            :description="$unreadOnly
                ? __('You are all caught up.')
                : __('Updates about your requests will show up here.')"
        />
    @else
        <div class="flex flex-col gap-2">
            @foreach ($this->notifications as $notification)
                <div
                    @class([
                        'flex items-start gap-3 rounded-xl border px-4 py-3',
                        'border-zinc-200 bg-white' => $notification->is_read,
                        'border-accent/30 bg-accent/5' => ! $notification->is_read,
                    ])
                    wire:key="notification-{{ $notification->id }}"
                    data-test="notification-row"
                >
                    <flux:icon
                        :name="$notification->type->icon()"
                        variant="mini"
                        @class([
                            'mt-0.5 shrink-0',
                            'text-zinc-400' => $notification->is_read,
                            'text-accent' => ! $notification->is_read,
                        ])
                    />

                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <flux:text>{{ $notification->message }}</flux:text>

                        <div class="flex flex-wrap items-center gap-2">
                            <flux:text size="sm" class="text-zinc-500">
                                {{ $notification->created_at?->diffForHumans() }}
                            </flux:text>

                            @if ($notification->url)
                                <flux:link :href="$notification->url" class="text-sm" wire:navigate>
                                    {{ __('View') }}
                                </flux:link>
                            @endif
                        </div>
                    </div>

                    @unless ($notification->is_read)
                        <flux:button
                            wire:click="markAsRead({{ $notification->id }})"
                            size="xs"
                            variant="subtle"
                            data-test="mark-read-button"
                        >
                            {{ __('Mark read') }}
                        </flux:button>
                    @endunless
                </div>
            @endforeach

            @if ($this->notifications->hasPages())
                <flux:pagination :paginator="$this->notifications" />
            @endif
        </div>
    @endif
</div>

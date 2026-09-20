@php
    // Defaulted so the partial still renders if it is ever included directly
    // rather than through the polling sidebar component.
    $badges = $badges ?? [];
@endphp

<flux:sidebar.group :heading="__('My records')" class="grid">
    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
        {{ __('Dashboard') }}
    </flux:sidebar.item>

    <flux:sidebar.item icon="document-text" :href="route('student.requests.index')" :badge="($badges['openRequests'] ?? 0) ?: null" badge-color="blue" :current="request()->routeIs('student.requests.*')" wire:navigate>
        {{ __('My requests') }}
    </flux:sidebar.item>

    <flux:sidebar.item icon="calendar-days" :href="route('student.appointments.index')" :current="request()->routeIs('student.appointments.*')" wire:navigate>
        {{ __('Appointments') }}
    </flux:sidebar.item>

    <flux:sidebar.item icon="bell" :href="route('notifications.index')" :badge="($badges['unreadNotifications'] ?? 0) ?: null" badge-color="red" :current="request()->routeIs('notifications.*')" wire:navigate>
        {{ __('Notifications') }}
    </flux:sidebar.item>
</flux:sidebar.group>

@php
    // Defaulted so the partial still renders if it is ever included directly
    // rather than through the polling sidebar component.
    $badges = $badges ?? [];
@endphp

<flux:sidebar.group :heading="__('Registrar')" class="grid">
    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
        {{ __('Dashboard') }}
    </flux:sidebar.item>

    <flux:sidebar.item icon="inbox-stack" :href="route('registrar.requests.index')" :badge="($badges['pendingRequests'] ?? 0) ?: null" badge-color="amber" :current="request()->routeIs('registrar.requests.*')" wire:navigate>
        {{ __('Request queue') }}
    </flux:sidebar.item>

    <flux:sidebar.item icon="calendar-days" :href="route('registrar.appointments.index')" :badge="($badges['appointmentsToday'] ?? 0) ?: null" badge-color="blue" :current="request()->routeIs('registrar.appointments.*')" wire:navigate>
        {{ __('Appointments') }}
    </flux:sidebar.item>

    <flux:sidebar.item icon="clock" :href="route('registrar.time-slots.index')" :current="request()->routeIs('registrar.time-slots.*')" wire:navigate>
        {{ __('Time slots') }}
    </flux:sidebar.item>

    <flux:sidebar.item icon="chart-bar" :href="route('registrar.reports.index')" :current="request()->routeIs('registrar.reports.*')" wire:navigate>
        {{ __('Reports') }}
    </flux:sidebar.item>

    <flux:sidebar.item icon="user-plus" :href="route('registrar.pending-accounts.index')" :badge="($badges['pendingAccounts'] ?? 0) ?: null" badge-color="red" :current="request()->routeIs('registrar.pending-accounts.*')" wire:navigate>
        {{ __('Pending accounts') }}
    </flux:sidebar.item>
</flux:sidebar.group>

<flux:sidebar.group :heading="__('Administration')" class="grid">
    <flux:sidebar.item icon="users" :href="route('admin.users.index')" :current="request()->routeIs('admin.users.*')" wire:navigate>
        {{ __('User accounts') }}
    </flux:sidebar.item>

    <flux:sidebar.item icon="document-duplicate" :href="route('admin.document-types.index')" :current="request()->routeIs('admin.document-types.*')" wire:navigate>
        {{ __('Document types') }}
    </flux:sidebar.item>

    <flux:sidebar.item icon="identification" :href="route('admin.student-registry.index')" :current="request()->routeIs('admin.student-registry.*')" wire:navigate>
        {{ __('Student registry') }}
    </flux:sidebar.item>

    <flux:sidebar.item icon="bell" :href="route('notifications.index')" :badge="($badges['unreadNotifications'] ?? 0) ?: null" badge-color="red" :current="request()->routeIs('notifications.*')" wire:navigate>
        {{ __('Notifications') }}
    </flux:sidebar.item>
</flux:sidebar.group>

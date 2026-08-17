<flux:sidebar.group :heading="__('Registrar')" class="grid">
    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
        {{ __('Dashboard') }}
    </flux:sidebar.item>

    <flux:sidebar.item icon="inbox-stack" :href="route('registrar.requests.index')" :current="request()->routeIs('registrar.requests.*')" wire:navigate>
        {{ __('Request queue') }}
    </flux:sidebar.item>

    <flux:sidebar.item icon="calendar-days" :href="route('registrar.appointments.index')" :current="request()->routeIs('registrar.appointments.*')" wire:navigate>
        {{ __('Appointments') }}
    </flux:sidebar.item>

    <flux:sidebar.item icon="clock" :href="route('registrar.time-slots.index')" :current="request()->routeIs('registrar.time-slots.*')" wire:navigate>
        {{ __('Time slots') }}
    </flux:sidebar.item>

    <flux:sidebar.item icon="chart-bar" href="#" :current="request()->routeIs('registrar.reports.*')">
        {{ __('Reports') }}
    </flux:sidebar.item>

    <flux:sidebar.item icon="bell" :href="route('notifications.index')" :current="request()->routeIs('notifications.*')" wire:navigate>
        {{ __('Notifications') }}
    </flux:sidebar.item>
</flux:sidebar.group>

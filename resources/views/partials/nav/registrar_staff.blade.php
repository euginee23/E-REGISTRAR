<flux:sidebar.group :heading="__('Registrar')" class="grid">
    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
        {{ __('Dashboard') }}
    </flux:sidebar.item>

    <flux:sidebar.item icon="inbox-stack" href="#" :current="request()->routeIs('registrar.requests.*')">
        {{ __('Request queue') }}
    </flux:sidebar.item>

    <flux:sidebar.item icon="calendar-days" href="#" :current="request()->routeIs('registrar.appointments.*')">
        {{ __('Appointments') }}
    </flux:sidebar.item>

    <flux:sidebar.item icon="clock" href="#" :current="request()->routeIs('registrar.time-slots.*')">
        {{ __('Time slots') }}
    </flux:sidebar.item>

    <flux:sidebar.item icon="chart-bar" href="#" :current="request()->routeIs('registrar.reports.*')">
        {{ __('Reports') }}
    </flux:sidebar.item>

    <flux:sidebar.item icon="bell" href="#" :current="request()->routeIs('notifications.*')">
        {{ __('Notifications') }}
    </flux:sidebar.item>
</flux:sidebar.group>

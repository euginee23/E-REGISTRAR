<flux:sidebar.group :heading="__('My records')" class="grid">
    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
        {{ __('Dashboard') }}
    </flux:sidebar.item>

    <flux:sidebar.item icon="document-text" href="#" :current="request()->routeIs('student.requests.*')">
        {{ __('My requests') }}
    </flux:sidebar.item>

    <flux:sidebar.item icon="calendar-days" href="#" :current="request()->routeIs('student.appointments.*')">
        {{ __('Appointments') }}
    </flux:sidebar.item>

    <flux:sidebar.item icon="bell" href="#" :current="request()->routeIs('notifications.*')">
        {{ __('Notifications') }}
    </flux:sidebar.item>
</flux:sidebar.group>

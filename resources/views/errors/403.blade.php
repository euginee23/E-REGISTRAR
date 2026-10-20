<x-layouts::auth :title="__('Access denied')">
    <div class="flex flex-col gap-6" data-test="forbidden-page">
        <x-auth-header
            :title="__('You don\'t have access to this page')"
            :description="auth()->check()
                ? __('You are signed in as :name (:role).', [
                    'name' => auth()->user()->name,
                    'role' => auth()->user()->role->label(),
                ])
                : __('Sign in with an account that is allowed to open it.')"
        />

        @auth
            <flux:text class="text-center">
                {{ __('If this link was meant for a different account - for example, an email sent to a student opened where the registrar is signed in - sign out and sign in with that account. You will be brought straight back here.') }}
            </flux:text>

            <form method="POST" action="{{ route('switch-account') }}" class="flex flex-col gap-3">
                @csrf
                <input type="hidden" name="continue" value="{{ request()->getRequestUri() }}">

                <flux:button type="submit" variant="primary" class="w-full" data-test="switch-account-button">
                    {{ __('Sign out and continue') }}
                </flux:button>

                <flux:button :href="route('dashboard')" variant="ghost" class="w-full">
                    {{ __('Go to my dashboard') }}
                </flux:button>
            </form>
        @else
            <flux:button :href="route('login')" variant="primary" class="w-full">
                {{ __('Log in') }}
            </flux:button>
        @endauth
    </div>
</x-layouts::auth>

<x-layouts::auth :title="__('Register')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Create an account')" :description="__('Enter your details below to create your account')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf
            <!-- Name -->
            <flux:input
                name="name"
                :label="__('Name')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('Full name')"
            />

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Enrollment status -->
            <div x-data="{ status: '{{ old('enrollment_status', 'enrolled') }}' }" class="flex flex-col gap-6">
                <flux:select
                    name="enrollment_status"
                    :label="__('I am a')"
                    x-model="status"
                    required
                >
                    <flux:select.option value="enrolled">{{ __('Currently enrolled student') }}</flux:select.option>
                    <flux:select.option value="alumnus">{{ __('Alumnus') }}</flux:select.option>
                </flux:select>

                <!-- Course -->
                <flux:input
                    name="course"
                    :label="__('Course')"
                    :value="old('course')"
                    type="text"
                    required
                    :placeholder="__('BS Information Technology')"
                />

                <!-- Year graduated (alumni only) -->
                <div x-show="status === 'alumnus'" x-cloak>
                    <flux:input
                        name="year_graduated"
                        :label="__('Year graduated')"
                        :value="old('year_graduated')"
                        type="number"
                        min="1950"
                        max="{{ date('Y') }}"
                        :placeholder="date('Y') - 1"
                    />
                </div>
            </div>

            <!-- Student number -->
            <flux:input
                name="student_number"
                :label="__('Student number')"
                :value="old('student_number')"
                type="text"
                :description="__('Optional. Helps the registrar locate your records faster.')"
                :placeholder="__('2022-10231')"
            />

            <!-- Contact number -->
            <flux:input
                name="contact_number"
                :label="__('Contact number')"
                :value="old('contact_number')"
                type="tel"
                required
                placeholder="09171234567"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Confirm password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                    {{ __('Create account') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>

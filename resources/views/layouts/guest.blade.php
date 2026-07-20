<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-neutral-950">
        <flux:header container sticky class="bg-white/90 backdrop-blur border-b border-zinc-200 dark:bg-neutral-950/90 dark:border-neutral-800">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <a href="{{ route('home') }}" wire:navigate class="me-4 flex h-10 items-center">
                <img
                    src="{{ asset('logos/e-registrar-logo.png') }}"
                    alt="{{ __('e-Registrar') }}"
                    class="h-8 w-auto"
                    onerror="this.onerror=null; this.src='{{ asset('logos/e-registrar-logo.svg') }}';"
                >
            </a>

            <flux:navbar class="-mb-px max-lg:hidden">
                <flux:navbar.item href="{{ route('home') }}" wire:navigate :current="false" data-nav-link="home">{{ __('Home') }}</flux:navbar.item>
                <flux:navbar.item href="{{ route('home') }}#about" :current="false" data-nav-link="about">{{ __('About') }}</flux:navbar.item>
                <flux:navbar.item href="{{ route('home') }}#services" :current="false" data-nav-link="services">{{ __('Services') }}</flux:navbar.item>
                <flux:navbar.item href="{{ route('home') }}#contact" :current="false" data-nav-link="contact">{{ __('Contact') }}</flux:navbar.item>
            </flux:navbar>

            <flux:spacer />

            <div class="flex items-center gap-2 max-lg:hidden">
                <flux:button href="{{ route('login') }}" variant="ghost" wire:navigate>{{ __('Log in') }}</flux:button>
                <flux:button href="{{ route('register') }}" variant="primary" wire:navigate>{{ __('Get Started') }}</flux:button>
            </div>
        </flux:header>

        <flux:sidebar collapsible="mobile" class="lg:hidden bg-white dark:bg-neutral-950 border-r border-zinc-200 dark:border-neutral-800">
            <flux:sidebar.header>
                <a href="{{ route('home') }}" wire:navigate class="flex h-10 items-center px-2">
                    <img
                        src="{{ asset('logos/e-registrar-logo.png') }}"
                        alt="{{ __('e-Registrar') }}"
                        class="h-8 w-auto"
                        onerror="this.onerror=null; this.src='{{ asset('logos/e-registrar-logo.svg') }}';"
                    >
                </a>
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.item href="{{ route('home') }}" wire:navigate :current="false" data-nav-link="home">{{ __('Home') }}</flux:sidebar.item>
                <flux:sidebar.item href="{{ route('home') }}#about" :current="false" data-nav-link="about">{{ __('About') }}</flux:sidebar.item>
                <flux:sidebar.item href="{{ route('home') }}#services" :current="false" data-nav-link="services">{{ __('Services') }}</flux:sidebar.item>
                <flux:sidebar.item href="{{ route('home') }}#contact" :current="false" data-nav-link="contact">{{ __('Contact') }}</flux:sidebar.item>
            </flux:sidebar.nav>

            <flux:sidebar.spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item href="{{ route('login') }}" wire:navigate>{{ __('Log in') }}</flux:sidebar.item>
                <flux:sidebar.item href="{{ route('register') }}" wire:navigate>{{ __('Get Started') }}</flux:sidebar.item>
            </flux:sidebar.nav>
        </flux:sidebar>

        <main>
            {{ $slot }}
        </main>

        <footer class="border-t border-zinc-200 bg-zinc-50 dark:border-neutral-800 dark:bg-neutral-900">
            <div class="mx-auto max-w-7xl px-6 py-12 lg:px-8">
                <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <img
                            src="{{ asset('logos/e-registrar-logo.png') }}"
                            alt="{{ __('e-Registrar') }}"
                            class="h-8 w-auto"
                            onerror="this.onerror=null; this.src='{{ asset('logos/e-registrar-logo.svg') }}';"
                        >
                        <flux:text class="mt-3 max-w-xs">
                            {{ __('A simpler way for students and alumni to request academic documents and schedule pickup appointments online.') }}
                        </flux:text>
                    </div>

                    <div>
                        <flux:heading size="sm">{{ __('Navigation') }}</flux:heading>
                        <ul class="mt-3 space-y-2">
                            <li><flux:link href="{{ route('home') }}" variant="subtle" wire:navigate>{{ __('Home') }}</flux:link></li>
                            <li><flux:link href="{{ route('home') }}#about" variant="subtle">{{ __('About') }}</flux:link></li>
                            <li><flux:link href="{{ route('home') }}#services" variant="subtle">{{ __('Services') }}</flux:link></li>
                            <li><flux:link href="{{ route('home') }}#contact" variant="subtle">{{ __('Contact') }}</flux:link></li>
                        </ul>
                    </div>

                    <div>
                        <flux:heading size="sm">{{ __('Account') }}</flux:heading>
                        <ul class="mt-3 space-y-2">
                            <li><flux:link href="{{ route('login') }}" variant="subtle" wire:navigate>{{ __('Log in') }}</flux:link></li>
                            <li><flux:link href="{{ route('register') }}" variant="subtle" wire:navigate>{{ __('Create an account') }}</flux:link></li>
                        </ul>
                    </div>

                    <div>
                        <flux:heading size="sm">{{ __("Registrar's Office") }}</flux:heading>
                        <ul class="mt-3 space-y-2">
                            <li><flux:text>{{ __('Mon–Fri, 8:00 AM–5:00 PM') }}</flux:text></li>
                            <li><flux:text>registrar@example.edu</flux:text></li>
                        </ul>
                    </div>
                </div>

                <flux:separator class="my-8" variant="subtle" />

                <flux:text class="text-center">
                    &copy; {{ now()->year }} e-Registrar. {{ __('All rights reserved.') }}
                </flux:text>
            </div>
        </footer>

        <script>
            (function () {
                var ids = ['home', 'about', 'services', 'contact'];

                var sections = ids
                    .map(function (id) { return document.getElementById(id); })
                    .filter(Boolean);

                var links = {};
                ids.forEach(function (id) {
                    links[id] = document.querySelectorAll('[data-nav-link="' + id + '"]');
                });

                function setCurrent(id) {
                    ids.forEach(function (key) {
                        links[key].forEach(function (el) {
                            el.toggleAttribute('data-current', key === id);
                        });
                    });
                }

                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            setCurrent(entry.target.id);
                        }
                    });
                }, { rootMargin: '-40% 0px -55% 0px' });

                sections.forEach(function (section) { observer.observe(section); });
            })();
        </script>

        @fluxScripts
    </body>
</html>

<?php

use App\Mail\TestMail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Mail settings')] class extends Component {
    public string $recipient = '';

    public ?string $result = null;

    public bool $succeeded = false;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->authorizeAdministrator();

        $this->recipient = Auth::user()->email;
    }

    /**
     * Get the mail settings the application is currently running with.
     *
     * @return array{mailer: string, host: string|null, port: int|string|null, scheme: string|null, username: string|null, from_address: string|null, from_name: string|null, queued: bool, delivers: bool}
     */
    #[Computed]
    public function settings(): array
    {
        $mailer = (string) config('mail.default');

        return [
            'mailer' => $mailer,
            'host' => config("mail.mailers.{$mailer}.host"),
            'port' => config("mail.mailers.{$mailer}.port"),
            'scheme' => config("mail.mailers.{$mailer}.scheme"),
            'username' => config("mail.mailers.{$mailer}.username"),
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'queued' => (bool) config('registrar.mail.queued'),
            'delivers' => ! in_array($mailer, ['log', 'array'], true),
        ];
    }

    /**
     * Send a test email straight away, bypassing the queue.
     *
     * The send is always immediate so the administrator sees the mail
     * server's actual answer here, rather than a job that may fail later
     * where nobody is looking.
     */
    public function sendTestEmail(): void
    {
        $this->authorizeAdministrator();

        $this->validate([
            'recipient' => ['required', 'string', 'email', 'max:255'],
        ]);

        $throttleKey = 'test-email:'.Auth::id();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->addError('recipient', __('Too many test emails. Try again in :seconds seconds.', [
                'seconds' => RateLimiter::availableIn($throttleKey),
            ]));

            return;
        }

        RateLimiter::hit($throttleKey);

        try {
            Mail::to($this->recipient)->send(new TestMail(Auth::user()->name));
        } catch (\Throwable $exception) {
            report($exception);

            $this->succeeded = false;
            $this->result = $exception->getMessage();

            return;
        }

        $this->succeeded = true;
        $this->result = $this->settings['delivers']
            ? __('Test email sent to :email. Check the inbox and spam folder.', ['email' => $this->recipient])
            : __('The ":mailer" mailer accepted the email, but it does not deliver anything. Set MAIL_MAILER=smtp to send real email.', ['mailer' => $this->settings['mailer']]);
    }

    /**
     * Mail settings are for administrators only.
     */
    private function authorizeAdministrator(): void
    {
        abort_unless(Auth::user()?->isAdministrator(), 403);
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Mail settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Mail')" :subheading="__('Check how the system sends email and send a test message')">
        <div class="my-6 w-full space-y-6">
            @unless ($this->settings['delivers'])
                <flux:callout variant="warning" icon="exclamation-triangle" data-test="mailer-not-delivering">
                    <flux:callout.heading>{{ __('Email is not being delivered') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('The ":mailer" mailer only records emails and never sends them. Set MAIL_MAILER=smtp and the MAIL_* settings in .env, then run php artisan config:clear.', ['mailer' => $this->settings['mailer']]) }}
                    </flux:callout.text>
                </flux:callout>
            @endunless

            <dl class="grid grid-cols-[max-content_1fr] gap-x-6 gap-y-2 text-sm">
                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Mailer') }}</dt>
                <dd class="font-medium">{{ $this->settings['mailer'] }}</dd>

                @if ($this->settings['mailer'] === 'smtp')
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Host') }}</dt>
                    <dd class="font-medium break-all">{{ $this->settings['host'] ?: '—' }}:{{ $this->settings['port'] ?: '—' }}</dd>

                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Scheme') }}</dt>
                    <dd class="font-medium">{{ $this->settings['scheme'] ?: __('auto') }}</dd>

                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Username') }}</dt>
                    <dd class="font-medium break-all">{{ $this->settings['username'] ?: '—' }}</dd>
                @endif

                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('From') }}</dt>
                <dd class="font-medium break-all">{{ $this->settings['from_name'] }} &lt;{{ $this->settings['from_address'] }}&gt;</dd>

                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Delivery') }}</dt>
                <dd class="font-medium">
                    {{ $this->settings['queued'] ? __('Queued (requires a running queue worker)') : __('Immediate') }}
                </dd>
            </dl>

            <flux:separator variant="subtle" />

            <form wire:submit="sendTestEmail" class="space-y-6">
                <flux:input
                    wire:model="recipient"
                    :label="__('Recipient email')"
                    type="email"
                    required
                    autocomplete="email"
                    data-test="test-email-recipient"
                />

                <flux:button variant="primary" type="submit" data-test="send-test-email-button">
                    {{ __('Send test email') }}
                </flux:button>
            </form>

            @if ($result !== null)
                @if ($succeeded)
                    <flux:callout variant="success" icon="check-circle" data-test="test-email-result">
                        <flux:callout.text>{{ $result }}</flux:callout.text>
                    </flux:callout>
                @else
                    <flux:callout variant="danger" icon="x-circle" data-test="test-email-result">
                        <flux:callout.heading>{{ __('The test email could not be sent') }}</flux:callout.heading>
                        <flux:callout.text class="break-all">{{ $result }}</flux:callout.text>
                    </flux:callout>
                @endif
            @endif
        </div>
    </x-pages::settings.layout>
</section>

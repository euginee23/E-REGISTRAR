<x-layouts::guest>
    {{-- Hero --}}
    <section id="home" class="relative overflow-hidden bg-white dark:bg-neutral-950">
        <div class="absolute inset-0 -z-10 bg-linear-to-b from-brand-50 to-white dark:from-neutral-900 dark:to-neutral-950"></div>

        <div class="mx-auto max-w-7xl px-6 py-20 lg:flex lg:items-center lg:gap-x-12 lg:px-8 lg:py-28">
            <div class="max-w-2xl">
                <flux:badge color="blue" variant="pill">{{ __('For students & alumni') }}</flux:badge>

                <flux:heading size="xl" level="1" class="mt-6 text-4xl font-bold tracking-tight text-balance sm:text-5xl">
                    {{ __('Request academic documents online. Skip the queue.') }}
                </flux:heading>

                <flux:text class="mt-6 text-lg text-zinc-600 dark:text-zinc-400">
                    {{ __("e-Registrar lets students and alumni request Form 137, Transcript of Records, and other school documents from anywhere, schedule a pickup appointment, and track every request until it's ready.") }}
                </flux:text>

                <div class="mt-10 flex flex-wrap items-center gap-4">
                    <flux:button href="{{ route('register') }}" variant="primary" wire:navigate>
                        {{ __('Get Started') }}
                    </flux:button>
                    <flux:button href="#about" variant="ghost">
                        {{ __('See how it works') }}
                    </flux:button>
                </div>

                <div class="mt-10 flex flex-wrap items-center gap-x-8 gap-y-3 text-sm text-zinc-500 dark:text-zinc-400">
                    <div class="flex items-center gap-2">
                        <flux:icon.check-circle variant="mini" class="text-brand-600 dark:text-brand-400" />
                        {{ __('Available 24/7') }}
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:icon.check-circle variant="mini" class="text-brand-600 dark:text-brand-400" />
                        {{ __('Track requests in real time') }}
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:icon.check-circle variant="mini" class="text-brand-600 dark:text-brand-400" />
                        {{ __('No more waiting in line') }}
                    </div>
                </div>
            </div>

            <div class="mt-16 lg:mt-0 lg:flex-1">
                <flux:card class="mx-auto max-w-md border-brand-100 shadow-xl dark:border-neutral-800">
                    <flux:heading size="lg">{{ __('Transcript of Records') }}</flux:heading>
                    <flux:badge color="lime" class="mt-2">{{ __('Ready for pickup') }}</flux:badge>

                    <flux:separator class="my-4" variant="subtle" />

                    <div class="space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <flux:text>{{ __('Requested') }}</flux:text>
                            <flux:text class="font-medium text-zinc-900 dark:text-white">{{ __('Jul 14, 2026') }}</flux:text>
                        </div>
                        <div class="flex items-center justify-between">
                            <flux:text>{{ __('Appointment') }}</flux:text>
                            <flux:text class="font-medium text-zinc-900 dark:text-white">{{ __('Jul 22, 2026 · 10:00 AM') }}</flux:text>
                        </div>
                        <div class="flex items-center justify-between">
                            <flux:text>{{ __('Reference No.') }}</flux:text>
                            <flux:text class="font-medium text-zinc-900 dark:text-white">{{ __('REQ-2026-0143') }}</flux:text>
                        </div>
                    </div>
                </flux:card>
            </div>
        </div>
    </section>

    {{-- Features --}}
    <section class="mx-auto max-w-7xl px-6 py-20 lg:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <flux:heading size="xl" level="2">{{ __('Everything you need to get your documents') }}</flux:heading>
            <flux:text class="mt-4 text-lg">
                {{ __("Built to modernize the registrar's document request process from start to finish.") }}
            </flux:text>
        </div>

        <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <flux:card class="text-center sm:text-left">
                <span class="mx-auto flex size-11 items-center justify-center rounded-lg bg-brand-100 text-brand-700 sm:mx-0 dark:bg-brand-900/40 dark:text-brand-300">
                    <flux:icon.document-text variant="solid" />
                </span>
                <flux:heading class="mt-4">{{ __('Online Document Requests') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Request Form 137, TOR, Good Moral Certificate, Certificate of Enrollment, and more from any device.') }}
                </flux:text>
            </flux:card>

            <flux:card class="text-center sm:text-left">
                <span class="mx-auto flex size-11 items-center justify-center rounded-lg bg-brand-100 text-brand-700 sm:mx-0 dark:bg-brand-900/40 dark:text-brand-300">
                    <flux:icon.calendar-days variant="solid" />
                </span>
                <flux:heading class="mt-4">{{ __('Appointment Scheduling') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Pick a convenient date and time to claim your documents, so you spend less time waiting in line.') }}
                </flux:text>
            </flux:card>

            <flux:card class="text-center sm:text-left">
                <span class="mx-auto flex size-11 items-center justify-center rounded-lg bg-brand-100 text-brand-700 sm:mx-0 dark:bg-brand-900/40 dark:text-brand-300">
                    <flux:icon.arrow-path variant="solid" />
                </span>
                <flux:heading class="mt-4">{{ __('Status Tracking') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Follow your request from submission to approval to release, without calling the office to check.') }}
                </flux:text>
            </flux:card>

            <flux:card class="text-center sm:text-left">
                <span class="mx-auto flex size-11 items-center justify-center rounded-lg bg-brand-100 text-brand-700 sm:mx-0 dark:bg-brand-900/40 dark:text-brand-300">
                    <flux:icon.bell variant="solid" />
                </span>
                <flux:heading class="mt-4">{{ __('Request Notifications') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __("Get notified the moment your request is approved and your document is ready for release.") }}
                </flux:text>
            </flux:card>
        </div>
    </section>

    {{-- About --}}
    <section id="about" class="scroll-mt-24 bg-zinc-50 py-20 dark:bg-neutral-900">
        <div class="mx-auto max-w-5xl px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <flux:badge color="blue" variant="pill">{{ __('About e-Registrar') }}</flux:badge>
                <flux:heading size="xl" level="2" class="mt-4">{{ __('Modernizing how students get their documents') }}</flux:heading>
                <flux:text class="mt-4 text-lg">
                    {{ __("Requesting a Transcript of Records or Form 137 used to mean a trip to the registrar's office, a paper form, and a long queue. e-Registrar moves that entire process online.") }}
                </flux:text>
            </div>

            <div class="mt-14 grid gap-12 lg:grid-cols-2 lg:items-start">
                <div>
                    <flux:heading size="lg">{{ __('Why we built this') }}</flux:heading>
                    <flux:text class="mt-4">
                        {{ __('Academic documents like the Transcript of Records, Form 137, Good Moral Certificate, and Certificate of Enrollment are essential when applying for a job, transferring schools, or continuing your education. In many schools, getting them still means filling out paper forms and waiting in line — often more than once.') }}
                    </flux:text>
                    <flux:text class="mt-4">
                        {{ __("e-Registrar gives students and alumni a way to submit requests anytime, and gives the registrar's office a simpler, more organized way to manage, approve, and release them.") }}
                    </flux:text>
                </div>

                <div class="grid gap-4">
                    @foreach ([
                        __('Reduce time spent waiting in line at the registrar.'),
                        __('Let students track requests without calling the office.'),
                        __('Give registrar staff one place to manage every request.'),
                        __('Cut down on paper forms and manual record-keeping.'),
                    ] as $goal)
                        <div class="flex items-start gap-3">
                            <flux:icon.check-circle variant="solid" class="mt-0.5 shrink-0 text-brand-600 dark:text-brand-400" />
                            <flux:text class="text-zinc-900 dark:text-white">{{ $goal }}</flux:text>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-16">
                <flux:heading size="lg" class="text-center">{{ __('The request process, step by step') }}</flux:heading>

                <div class="mt-10 space-y-6">
                    @foreach ([
                        [
                            'title' => __('1. Create your account'),
                            'body' => __('Register as a current student or an alumnus using your basic details. This account is where you submit requests and follow their progress.'),
                        ],
                        [
                            'title' => __('2. Submit a document request'),
                            'body' => __('Select the document you need — Form 137, TOR, Good Moral Certificate, Certificate of Enrollment, or another academic record — and fill in the request form online.'),
                        ],
                        [
                            'title' => __('3. Registrar reviews and approves'),
                            'body' => __('The registrar reviews your request, verifies your records, and updates the status as it moves through processing.'),
                        ],
                        [
                            'title' => __('4. Schedule your pickup appointment'),
                            'body' => __('Once approved, choose a date and time to claim your document, so you avoid overcrowding at the registrar\'s office.'),
                        ],
                        [
                            'title' => __('5. Get notified and claim your document'),
                            'body' => __("You'll be notified when your document is ready. Documents must still be claimed in person, or by an authorized representative, following school policy."),
                        ],
                    ] as $index => $step)
                        <flux:card class="flex gap-5">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-600 text-sm font-semibold text-white dark:bg-brand-500">
                                {{ $index + 1 }}
                            </span>
                            <div>
                                <flux:heading>{{ $step['title'] }}</flux:heading>
                                <flux:text class="mt-1">{{ $step['body'] }}</flux:text>
                            </div>
                        </flux:card>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- Services --}}
    <section id="services" class="scroll-mt-24 mx-auto max-w-6xl px-6 py-20 lg:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <flux:badge color="blue" variant="pill">{{ __('Services') }}</flux:badge>
            <flux:heading size="xl" level="2" class="mt-4">{{ __('Documents you can request online') }}</flux:heading>
            <flux:text class="mt-4 text-lg">
                {{ __('Submit a request for any of the documents below. Once approved, schedule an appointment to claim it at the registrar\'s office.') }}
            </flux:text>
        </div>

        <div class="mt-14 grid gap-8 sm:grid-cols-2">
            @foreach ([
                [
                    'icon' => 'document-text',
                    'title' => __('Form 137'),
                    'body' => __("An official school document containing a student's permanent academic record, commonly required when transferring to another school."),
                ],
                [
                    'icon' => 'academic-cap',
                    'title' => __('Transcript of Records (TOR)'),
                    'body' => __("An official record of a student's academic performance and completed courses, typically required for employment or further studies."),
                ],
                [
                    'icon' => 'shield-check',
                    'title' => __('Good Moral Certificate'),
                    'body' => __('A certificate attesting to a student\'s good conduct while enrolled, often required by employers and other schools.'),
                ],
                [
                    'icon' => 'identification',
                    'title' => __('Certificate of Enrollment'),
                    'body' => __('A certificate confirming that a student is currently enrolled, often required for scholarships, visas, or allowances.'),
                ],
            ] as $doc)
                <flux:card class="flex gap-5">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-lg bg-brand-100 text-brand-700 dark:bg-brand-900/40 dark:text-brand-300">
                        <flux:icon :icon="$doc['icon']" variant="solid" class="size-6" />
                    </span>
                    <div>
                        <flux:heading size="lg">{{ $doc['title'] }}</flux:heading>
                        <flux:text class="mt-2">{{ $doc['body'] }}</flux:text>
                    </div>
                </flux:card>
            @endforeach
        </div>

        <flux:card class="mt-8 flex gap-5">
            <span class="flex size-12 shrink-0 items-center justify-center rounded-lg bg-brand-100 text-brand-700 dark:bg-brand-900/40 dark:text-brand-300">
                <flux:icon.folder variant="solid" class="size-6" />
            </span>
            <div>
                <flux:heading size="lg">{{ __('Other academic documents') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __("Need something not listed here? You can still submit a request describing the document you're after, and the registrar's office will review it.") }}
                </flux:text>
            </div>
        </flux:card>

        <flux:card class="mt-8">
            <div class="flex gap-4">
                <flux:icon.information-circle variant="solid" class="mt-0.5 size-6 shrink-0 text-brand-600 dark:text-brand-400" />
                <div>
                    <flux:heading>{{ __('How documents are released') }}</flux:heading>
                    <flux:text class="mt-2">
                        {{ __('Documents are claimed in person at the registrar\'s office, or by an authorized representative following school policy. e-Registrar does not process online payments or offer home delivery — it covers the request, approval, and appointment scheduling steps only.') }}
                    </flux:text>
                </div>
            </div>
        </flux:card>
    </section>

    {{-- Contact --}}
    <section id="contact" class="scroll-mt-24 bg-zinc-50 py-20 dark:bg-neutral-900">
        <div class="mx-auto max-w-6xl px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <flux:badge color="blue" variant="pill">{{ __('Contact') }}</flux:badge>
                <flux:heading size="xl" level="2" class="mt-4">{{ __("Get in touch with the registrar's office") }}</flux:heading>
                <flux:text class="mt-4 text-lg">
                    {{ __('Have a question about a request or your account? Reach out, or check the frequently asked questions below.') }}
                </flux:text>
            </div>

            <div class="mt-14 grid gap-12 lg:grid-cols-3">
                {{-- Contact details --}}
                <div class="space-y-6 lg:col-span-1">
                    <flux:card class="flex gap-4">
                        <flux:icon.map-pin variant="solid" class="size-6 shrink-0 text-brand-600 dark:text-brand-400" />
                        <div>
                            <flux:heading size="sm">{{ __('Office') }}</flux:heading>
                            <flux:text class="mt-1">{{ __("Registrar's Office, Main Campus Building") }}</flux:text>
                        </div>
                    </flux:card>

                    <flux:card class="flex gap-4">
                        <flux:icon.clock variant="solid" class="size-6 shrink-0 text-brand-600 dark:text-brand-400" />
                        <div>
                            <flux:heading size="sm">{{ __('Office Hours') }}</flux:heading>
                            <flux:text class="mt-1">{{ __('Monday–Friday, 8:00 AM–5:00 PM') }}</flux:text>
                        </div>
                    </flux:card>

                    <flux:card class="flex gap-4">
                        <flux:icon.envelope variant="solid" class="size-6 shrink-0 text-brand-600 dark:text-brand-400" />
                        <div>
                            <flux:heading size="sm">{{ __('Email') }}</flux:heading>
                            <flux:text class="mt-1">registrar@example.edu</flux:text>
                        </div>
                    </flux:card>

                    <flux:card class="flex gap-4">
                        <flux:icon.phone variant="solid" class="size-6 shrink-0 text-brand-600 dark:text-brand-400" />
                        <div>
                            <flux:heading size="sm">{{ __('Phone') }}</flux:heading>
                            <flux:text class="mt-1">(000) 000-0000</flux:text>
                        </div>
                    </flux:card>
                </div>

                {{-- FAQ --}}
                <div class="lg:col-span-2">
                    <flux:heading size="lg">{{ __('Frequently asked questions') }}</flux:heading>

                    <div class="mt-6 divide-y divide-zinc-200 dark:divide-neutral-800">
                        @foreach ([
                            [
                                'q' => __('What documents can I request online?'),
                                'a' => __('You can request Form 137, Transcript of Records, Good Moral Certificate, Certificate of Enrollment, and other academic documents through your account.'),
                            ],
                            [
                                'q' => __('Do I need to pay for my request online?'),
                                'a' => __('No. e-Registrar does not process online payments. Any applicable fees are handled directly with the registrar\'s office when you claim your document.'),
                            ],
                            [
                                'q' => __('Can someone else claim my document for me?'),
                                'a' => __('Yes, an authorized representative may claim your document on your behalf, following the school\'s standard authorization policy.'),
                            ],
                            [
                                'q' => __('Can I get my documents delivered to my home?'),
                                'a' => __('Not at this time. Documents must be claimed in person at the registrar\'s office during your scheduled appointment.'),
                            ],
                            [
                                'q' => __('How will I know when my request is ready?'),
                                'a' => __("You'll receive a notification as your request moves through review and once it's ready for pickup. You can also check its status anytime from your account."),
                            ],
                        ] as $faq)
                            <details class="group py-5">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-zinc-900 dark:text-white">
                                    <flux:heading size="sm" class="mb-0!">{{ $faq['q'] }}</flux:heading>
                                    <flux:icon.chevron-down variant="mini" class="shrink-0 text-zinc-400 transition-transform group-open:rotate-180" />
                                </summary>
                                <flux:text class="mt-3">{{ $faq['a'] }}</flux:text>
                            </details>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- CTA band --}}
    <section class="bg-brand-700 dark:bg-brand-900">
        <div class="mx-auto max-w-4xl px-6 py-16 text-center lg:px-8">
            <flux:heading size="xl" level="2" class="text-white">
                {{ __('Ready to request your documents?') }}
            </flux:heading>
            <flux:text class="mt-4 text-lg text-brand-100">
                {{ __('Create your free account and submit your first request today.') }}
            </flux:text>
            <div class="mt-8">
                <flux:button href="{{ route('register') }}" variant="primary" class="bg-white! text-brand-700! hover:bg-brand-50!" wire:navigate>
                    {{ __('Create an account') }}
                </flux:button>
            </div>
        </div>
    </section>
</x-layouts::guest>

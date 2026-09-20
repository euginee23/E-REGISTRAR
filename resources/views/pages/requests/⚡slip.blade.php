<?php

use App\Models\DocumentRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::print')] #[Title('Transaction slip')] class extends Component {
    public DocumentRequest $documentRequest;

    /**
     * Mount the component.
     *
     * One slip serves both audiences: the `view` ability already means "staff,
     * or the student the request belongs to".
     */
    public function mount(DocumentRequest $documentRequest): void
    {
        Gate::authorize('view', $documentRequest);

        $this->documentRequest = $documentRequest->load([
            'documentType',
            'student.user',
            'appointment.timeSlot',
            'recordedBy',
        ]);
    }
}; ?>

<div class="flex flex-col gap-4">
    <div class="flex flex-wrap justify-end gap-2 print:hidden">
        <flux:button
            :href="Auth::user()->isStaff()
                ? route('registrar.requests.show', $documentRequest)
                : route('student.requests.show', $documentRequest)"
            variant="ghost"
            size="sm"
        >
            {{ __('Back to request') }}
        </flux:button>

        <flux:button x-on:click="window.print()" variant="primary" size="sm" icon="printer" data-test="print-slip">
            {{ __('Print') }}
        </flux:button>
    </div>

    <div class="rounded-lg border border-zinc-300 bg-white p-8 text-zinc-900 print:rounded-none print:border-0 print:p-0">
        <header class="flex flex-col items-center gap-1 border-b border-zinc-300 pb-4 text-center">
            <h1 class="text-lg font-semibold">{{ config('registrar.office.name') }}</h1>
            <p class="text-sm text-zinc-600">{{ config('registrar.office.address') }}</p>
            <p class="text-sm text-zinc-600">{{ config('registrar.office.contact') }}</p>
            <h2 class="mt-3 text-base font-semibold uppercase tracking-wide">{{ __('Transaction Slip') }}</h2>
        </header>

        <section class="flex items-baseline justify-between gap-4 border-b border-zinc-300 py-4">
            <div>
                <p class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Reference number') }}</p>
                <p class="font-mono text-xl font-semibold" data-test="slip-reference">
                    {{ $documentRequest->reference_no }}
                </p>
            </div>
            <div class="text-right">
                <p class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Status') }}</p>
                <p class="font-semibold">{{ $documentRequest->status->label() }}</p>
            </div>
        </section>

        <section class="border-b border-zinc-300 py-4">
            <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Requested by') }}</h3>

            <dl class="grid gap-3 sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-zinc-500">{{ __('Name') }}</dt>
                    <dd>{{ $documentRequest->student->user->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-500">{{ __('Student number') }}</dt>
                    <dd class="font-mono text-sm">{{ $documentRequest->student->student_number }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-500">{{ __('Course') }}</dt>
                    <dd>{{ $documentRequest->student->course }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-500">{{ __('Contact number') }}</dt>
                    <dd>{{ $documentRequest->student->contact_number }}</dd>
                </div>
            </dl>
        </section>

        <section class="border-b border-zinc-300 py-4">
            <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Document requested') }}</h3>

            <dl class="grid gap-3 sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-zinc-500">{{ __('Document') }}</dt>
                    <dd>{{ $documentRequest->display_name }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-500">{{ __('Copies') }}</dt>
                    <dd>{{ $documentRequest->copies }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-500">{{ __('Date requested') }}</dt>
                    <dd>{{ $documentRequest->created_at?->format('F j, Y \a\t g:i A') }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-500">{{ __('Earliest claim date') }}</dt>
                    <dd>{{ $documentRequest->earliestClaimDate()->format('F j, Y') }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs text-zinc-500">{{ __('Purpose') }}</dt>
                    <dd>{{ $documentRequest->purpose }}</dd>
                </div>
            </dl>
        </section>

        <section class="border-b border-zinc-300 py-4" data-test="slip-payment">
            <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Payment') }}</h3>

            @if ($documentRequest->payment_status === App\Enums\PaymentStatus::NotRequired)
                <p>{{ __('No fee is charged for this document.') }}</p>
            @else
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-zinc-500">{{ __('Fee') }}</dt>
                        <dd>₱{{ number_format((float) $documentRequest->fee_amount, 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-500">{{ __('Payment status') }}</dt>
                        <dd>{{ $documentRequest->payment_status->label() }}</dd>
                    </div>

                    @if ($documentRequest->or_number !== null)
                        <div>
                            <dt class="text-xs text-zinc-500">{{ __('Official receipt') }}</dt>
                            <dd class="font-mono text-sm">{{ $documentRequest->or_number }}</dd>
                        </div>
                    @endif

                    @if ($documentRequest->amount_paid !== null)
                        <div>
                            <dt class="text-xs text-zinc-500">{{ __('Amount paid') }}</dt>
                            <dd>₱{{ number_format((float) $documentRequest->amount_paid, 2) }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($documentRequest->awaitsPayment())
                    <p class="mt-3 text-sm font-medium" data-test="slip-payment-due">
                        {{ __('Present this slip at the cashier to settle the fee. Processing begins once payment is recorded.') }}
                    </p>
                @endif
            @endif
        </section>

        <section class="border-b border-zinc-300 py-4" data-test="slip-appointment">
            <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Claiming appointment') }}</h3>

            @if ($documentRequest->appointment !== null)
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-zinc-500">{{ __('Date') }}</dt>
                        <dd>{{ $documentRequest->appointment->timeSlot->slot_date->format('l, F j, Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-500">{{ __('Time') }}</dt>
                        <dd>{{ $documentRequest->appointment->timeSlot->label }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-500">{{ __('Appointment status') }}</dt>
                        <dd>{{ $documentRequest->appointment->status->label() }}</dd>
                    </div>
                </dl>
            @else
                <p data-test="slip-no-appointment">
                    {{ __('No appointment has been booked yet. Book one from e-Registrar once the document is ready for release.') }}
                </p>
            @endif
        </section>

        <section class="py-4 text-sm text-zinc-600">
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('How to claim') }}</h3>

            <ol class="list-decimal space-y-1 ps-5">
                <li>{{ __('Bring this slip and your student ID to the registrar\'s office.') }}</li>
                <li>{{ __('Arrive within your booked time slot. The office is open :opens to :closes, Monday to Friday.', [
                    'opens' => CarbonImmutable::parse(config('registrar.office.opens_at'))->format('g:i A'),
                    'closes' => CarbonImmutable::parse(config('registrar.office.closes_at'))->format('g:i A'),
                ]) }}</li>
                <li>{{ __('An authorised representative must present this slip together with a signed authorisation letter and a valid ID.') }}</li>
            </ol>
        </section>

        <footer class="flex items-end justify-between gap-8 border-t border-zinc-300 pt-6">
            <p class="text-xs text-zinc-500">
                {{ __('Printed :timestamp', ['timestamp' => now()->format('F j, Y \a\t g:i A')]) }}
            </p>

            <div class="w-56 text-center">
                <div class="border-b border-zinc-400"></div>
                <p class="mt-1 text-xs text-zinc-500">{{ __('Received by (signature over printed name)') }}</p>
            </div>
        </footer>
    </div>
</div>

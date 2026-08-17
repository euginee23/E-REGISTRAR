<?php

use App\Actions\Requests\TransitionRequestStatus;
use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public DocumentRequest $documentRequest;

    /**
     * Mount the component.
     */
    public function mount(DocumentRequest $documentRequest): void
    {
        Gate::authorize('view', $documentRequest);

        $this->documentRequest = $documentRequest->load([
            'documentType',
            'attachments',
            'statusHistories.changedBy',
            'appointment.timeSlot',
        ]);
    }

    /**
     * Set the page title from the request's reference number.
     */
    public function render()
    {
        return $this->view()->title(__('Request :reference', [
            'reference' => $this->documentRequest->reference_no,
        ]));
    }

    /**
     * Determine whether the student may still withdraw this request.
     */
    #[Computed]
    public function canCancel(): bool
    {
        return Auth::user()->can('cancel', $this->documentRequest);
    }

    /**
     * Withdraw the request.
     */
    public function cancelRequest(TransitionRequestStatus $transitionRequestStatus): void
    {
        Gate::authorize('cancel', $this->documentRequest);

        $transitionRequestStatus(
            documentRequest: $this->documentRequest,
            to: RequestStatus::Cancelled,
            actor: Auth::user(),
        );

        $this->documentRequest->refresh();

        Flux::modal('cancel-request')->close();

        Flux::toast(variant: 'success', text: __('Request cancelled.'));
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <x-page-heading
        :heading="$documentRequest->display_name"
        :subheading="__('Reference :reference', ['reference' => $documentRequest->reference_no])"
    >
        <flux:button :href="route('student.requests.index')" variant="ghost" size="sm" wire:navigate>
            {{ __('Back to my requests') }}
        </flux:button>

        @if ($this->canCancel)
            <flux:modal.trigger name="cancel-request">
                <flux:button variant="danger" size="sm" data-test="cancel-request-trigger">
                    {{ __('Cancel request') }}
                </flux:button>
            </flux:modal.trigger>
        @endif
    </x-page-heading>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="flex flex-col gap-6 lg:col-span-2">
            <flux:card class="flex flex-col gap-4">
                <div class="flex items-center justify-between gap-4">
                    <flux:heading size="sm">{{ __('Request details') }}</flux:heading>
                    <x-status-badge :status="$documentRequest->status" />
                </div>

                <flux:separator />

                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt><flux:text size="sm" class="text-zinc-500">{{ __('Document') }}</flux:text></dt>
                        <dd><flux:text>{{ $documentRequest->display_name }}</flux:text></dd>
                    </div>

                    <div>
                        <dt><flux:text size="sm" class="text-zinc-500">{{ __('Copies') }}</flux:text></dt>
                        <dd><flux:text>{{ $documentRequest->copies }}</flux:text></dd>
                    </div>

                    <div>
                        <dt><flux:text size="sm" class="text-zinc-500">{{ __('Submitted') }}</flux:text></dt>
                        <dd><flux:text>{{ $documentRequest->created_at?->format('F j, Y \a\t g:i A') }}</flux:text></dd>
                    </div>

                    <div>
                        <dt><flux:text size="sm" class="text-zinc-500">{{ __('Earliest claim date') }}</flux:text></dt>
                        <dd><flux:text>{{ $documentRequest->earliestClaimDate()->format('F j, Y') }}</flux:text></dd>
                    </div>

                    <div class="sm:col-span-2">
                        <dt><flux:text size="sm" class="text-zinc-500">{{ __('Purpose') }}</flux:text></dt>
                        <dd><flux:text>{{ $documentRequest->purpose }}</flux:text></dd>
                    </div>

                    @if ($documentRequest->remarks)
                        <div class="sm:col-span-2">
                            <dt><flux:text size="sm" class="text-zinc-500">{{ __('Registrar remarks') }}</flux:text></dt>
                            <dd><flux:text>{{ $documentRequest->remarks }}</flux:text></dd>
                        </div>
                    @endif
                </dl>
            </flux:card>

            <flux:card class="flex flex-col gap-4">
                <flux:heading size="sm">{{ __('Supporting requirements') }}</flux:heading>

                @if ($documentRequest->attachments->isEmpty())
                    <flux:text size="sm" class="text-zinc-500">
                        {{ __('No files were attached to this request.') }}
                    </flux:text>
                @else
                    <ul class="flex flex-col gap-2">
                        @foreach ($documentRequest->attachments as $attachment)
                            <li
                                class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 px-3 py-2"
                                wire:key="attachment-{{ $attachment->id }}"
                            >
                                <div class="flex min-w-0 items-center gap-2">
                                    <flux:icon name="paper-clip" variant="mini" class="shrink-0 text-zinc-400" />
                                    <flux:text size="sm" class="truncate">{{ $attachment->original_name }}</flux:text>
                                    <flux:text size="sm" class="shrink-0 text-zinc-400">{{ $attachment->human_size }}</flux:text>
                                </div>

                                <flux:button
                                    :href="route('attachments.download', $attachment)"
                                    size="xs"
                                    variant="ghost"
                                    icon="arrow-down-tray"
                                >
                                    {{ __('Download') }}
                                </flux:button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </flux:card>
        </div>

        <div class="flex flex-col gap-6">
            <flux:card class="flex flex-col gap-4">
                <flux:heading size="sm">{{ __('Progress') }}</flux:heading>

                <x-student.request-timeline :histories="$documentRequest->statusHistories" />
            </flux:card>

            @if ($documentRequest->appointment)
                <flux:card class="flex flex-col gap-3">
                    <flux:heading size="sm">{{ __('Claiming appointment') }}</flux:heading>

                    <flux:text>
                        {{ $documentRequest->appointment->timeSlot->slot_date->format('l, F j, Y') }}
                    </flux:text>
                    <flux:text size="sm" class="text-zinc-500">
                        {{ $documentRequest->appointment->timeSlot->label }}
                    </flux:text>

                    <x-status-badge :status="$documentRequest->appointment->status" class="self-start" />
                </flux:card>
            @elseif ($documentRequest->status->isBookable())
                <flux:callout icon="calendar-days">
                    <flux:callout.heading>{{ __('Book your claiming appointment') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Reserve a time slot so you can collect this document without queueing.') }}
                    </flux:callout.text>
                </flux:callout>
            @endif
        </div>
    </div>

    @if ($this->canCancel)
        <flux:modal name="cancel-request" class="min-w-[22rem]">
            <div class="flex flex-col gap-6">
                <div class="flex flex-col gap-2">
                    <flux:heading size="lg">{{ __('Cancel this request?') }}</flux:heading>
                    <flux:text>
                        {{ __('Reference :reference will be withdrawn. This cannot be undone - you would need to submit a new request.', [
                            'reference' => $documentRequest->reference_no,
                        ]) }}
                    </flux:text>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Keep request') }}</flux:button>
                    </flux:modal.close>

                    <flux:button
                        variant="danger"
                        wire:click="cancelRequest"
                        data-test="confirm-cancel-request"
                    >
                        {{ __('Cancel request') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>

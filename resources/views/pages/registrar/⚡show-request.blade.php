<?php

use App\Models\DocumentRequest;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public DocumentRequest $documentRequest;

    /**
     * Mount the component.
     */
    public function mount(DocumentRequest $documentRequest): void
    {
        Gate::authorize('view', $documentRequest);

        $this->documentRequest = $documentRequest;

        $this->loadRelations();
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
     * Pull in fresh data after the status modal saves.
     */
    #[On('request-updated')]
    public function refreshRequest(): void
    {
        $this->documentRequest->refresh();

        $this->loadRelations();
    }

    /**
     * Eager-load everything the page renders.
     */
    private function loadRelations(): void
    {
        $this->documentRequest->load([
            'documentType',
            'student.user',
            'processedBy',
            'attachments',
            'statusHistories.changedBy',
            'appointment.timeSlot',
        ]);
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <x-page-heading
        :heading="$documentRequest->display_name"
        :subheading="__('Reference :reference', ['reference' => $documentRequest->reference_no])"
    >
        <flux:button :href="route('registrar.requests.index')" variant="ghost" size="sm" wire:navigate>
            {{ __('Back to queue') }}
        </flux:button>

        <livewire:registrar.update-request-status
            :document-request="$documentRequest"
            :wire:key="'update-status-' . $documentRequest->id . '-' . $documentRequest->status->value"
        />
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
                        <dt><flux:text size="sm" class="text-zinc-500">{{ __('Processing window') }}</flux:text></dt>
                        <dd><flux:text>{{ trans_choice('{1} :count working day|[2,*] :count working days', $documentRequest->documentType->processing_days, ['count' => $documentRequest->documentType->processing_days]) }}</flux:text></dd>
                    </div>

                    <div class="sm:col-span-2">
                        <dt><flux:text size="sm" class="text-zinc-500">{{ __('Purpose') }}</flux:text></dt>
                        <dd><flux:text>{{ $documentRequest->purpose }}</flux:text></dd>
                    </div>

                    @if ($documentRequest->remarks)
                        <div class="sm:col-span-2">
                            <dt><flux:text size="sm" class="text-zinc-500">{{ __('Remarks') }}</flux:text></dt>
                            <dd><flux:text>{{ $documentRequest->remarks }}</flux:text></dd>
                        </div>
                    @endif
                </dl>
            </flux:card>

            <flux:card class="flex flex-col gap-4">
                <flux:heading size="sm">{{ __('Supporting requirements') }}</flux:heading>

                @if ($documentRequest->attachments->isEmpty())
                    <flux:text size="sm" class="text-zinc-500">
                        {{ __('The student did not attach any files.') }}
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
                <flux:heading size="sm">{{ __('Requested by') }}</flux:heading>

                <div class="flex items-center gap-3">
                    <flux:avatar
                        :name="$documentRequest->student->user->name"
                        :initials="$documentRequest->student->user->initials()"
                    />

                    <div class="flex min-w-0 flex-col">
                        <flux:text class="truncate">{{ $documentRequest->student->user->name }}</flux:text>
                        <flux:text size="sm" class="truncate text-zinc-500">
                            {{ $documentRequest->student->user->email }}
                        </flux:text>
                    </div>
                </div>

                <flux:separator />

                <dl class="flex flex-col gap-2">
                    <div class="flex justify-between gap-3">
                        <dt><flux:text size="sm" class="text-zinc-500">{{ __('Student number') }}</flux:text></dt>
                        <dd><flux:text size="sm">{{ $documentRequest->student->student_number ?? '—' }}</flux:text></dd>
                    </div>

                    <div class="flex justify-between gap-3">
                        <dt><flux:text size="sm" class="text-zinc-500">{{ __('Course') }}</flux:text></dt>
                        <dd><flux:text size="sm" class="text-end">{{ $documentRequest->student->course }}</flux:text></dd>
                    </div>

                    <div class="flex justify-between gap-3">
                        <dt><flux:text size="sm" class="text-zinc-500">{{ __('Standing') }}</flux:text></dt>
                        <dd><x-status-badge :status="$documentRequest->student->enrollment_status" /></dd>
                    </div>

                    <div class="flex justify-between gap-3">
                        <dt><flux:text size="sm" class="text-zinc-500">{{ __('Contact') }}</flux:text></dt>
                        <dd><flux:text size="sm">{{ $documentRequest->student->contact_number }}</flux:text></dd>
                    </div>
                </dl>
            </flux:card>

            <flux:card class="flex flex-col gap-4">
                <flux:heading size="sm">{{ __('Audit trail') }}</flux:heading>

                <x-student.request-timeline :histories="$documentRequest->statusHistories" />
            </flux:card>

            @if ($documentRequest->appointment)
                <flux:card class="flex flex-col gap-3">
                    <flux:heading size="sm">{{ __('Claiming appointment') }}</flux:heading>

                    <flux:text>{{ $documentRequest->appointment->timeSlot->slot_date->format('l, F j, Y') }}</flux:text>
                    <flux:text size="sm" class="text-zinc-500">{{ $documentRequest->appointment->timeSlot->label }}</flux:text>

                    <x-status-badge :status="$documentRequest->appointment->status" class="self-start" />
                </flux:card>
            @endif
        </div>
    </div>
</div>

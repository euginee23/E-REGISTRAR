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

    public string $to = '';
    public string $remarks = '';

    /**
     * Get the statuses this request may legally move to.
     *
     * Sourced from the enum's state machine, so the form can only ever offer
     * a legal move - and the action re-checks it server side regardless.
     *
     * @return array<int, RequestStatus>
     */
    #[Computed]
    public function availableTransitions(): array
    {
        return array_values(array_filter(
            $this->documentRequest->status->allowedTransitions(),
            fn (RequestStatus $status): bool => Auth::user()->can('transition', [$this->documentRequest, $status]),
        ));
    }

    /**
     * Determine whether remarks must accompany the chosen status.
     */
    #[Computed]
    public function remarksRequired(): bool
    {
        return $this->to === RequestStatus::Rejected->value;
    }

    /**
     * Get the name of this request's modal.
     */
    #[Computed]
    public function modalName(): string
    {
        return 'update-status-'.$this->documentRequest->id;
    }

    /**
     * Apply the chosen status change.
     */
    public function updateStatus(TransitionRequestStatus $transitionRequestStatus): void
    {
        $validated = $this->validate([
            'to' => ['required', Illuminate\Validation\Rule::enum(RequestStatus::class)],
            'remarks' => [$this->remarksRequired ? 'required' : 'nullable', 'string', 'max:500'],
        ], [
            'to.required' => __('Choose the status to move this request to.'),
            'remarks.required' => __('Please explain why the request is being rejected.'),
        ]);

        $to = RequestStatus::from($validated['to']);

        Gate::authorize('transition', [$this->documentRequest, $to]);

        $transitionRequestStatus(
            documentRequest: $this->documentRequest,
            to: $to,
            actor: Auth::user(),
            remarks: $validated['remarks'] ?: null,
        );

        $this->documentRequest->refresh();
        $this->reset('to', 'remarks');

        Flux::modal($this->modalName)->close();
        Flux::toast(variant: 'success', text: __('Request updated to :status.', ['status' => $to->label()]));

        $this->dispatch('request-updated');
    }
}; ?>

<div>
    @if ($this->availableTransitions === [])
        <flux:text size="sm" class="text-zinc-500">
            {{ __('This request is finished; no further changes are possible.') }}
        </flux:text>
    @else
        <flux:modal.trigger :name="$this->modalName">
            <flux:button variant="primary" size="sm" data-test="update-status-trigger">
                {{ __('Update status') }}
            </flux:button>
        </flux:modal.trigger>

        <flux:modal :name="$this->modalName" class="min-w-[24rem]">
            <form wire:submit="updateStatus" class="flex flex-col gap-6">
                <div class="flex flex-col gap-2">
                    <flux:heading size="lg">{{ __('Update request status') }}</flux:heading>
                    <flux:text>
                        {{ __('Reference :reference is currently :status.', [
                            'reference' => $documentRequest->reference_no,
                            'status' => $documentRequest->status->label(),
                        ]) }}
                    </flux:text>
                </div>

                <flux:select
                    wire:model.live="to"
                    :label="__('Move to')"
                    :placeholder="__('Choose a status')"
                    required
                    data-test="transition-select"
                >
                    @foreach ($this->availableTransitions as $status)
                        <flux:select.option :value="$status->value" wire:key="to-{{ $status->value }}">
                            {{ $status === App\Enums\RequestStatus::Processing ? __('Approve & start processing') : $status->label() }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:textarea
                    wire:model="remarks"
                    :label="$this->remarksRequired ? __('Reason for rejection') : __('Remarks (optional)')"
                    :description="__('Shown to the student on their request page.')"
                    rows="3"
                    data-test="remarks-input"
                />

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>

                    <flux:button type="submit" variant="primary" data-test="confirm-update-status">
                        {{ __('Save') }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>

<?php

use App\Actions\Requests\RecordPayment;
use App\Exceptions\PaymentAlreadyRecordedException;
use App\Models\DocumentRequest;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public DocumentRequest $documentRequest;

    public string $amount = '';

    public string $orNumber = '';

    public bool $waive = false;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->amount = (string) $this->documentRequest->fee_amount;
    }

    /**
     * Get the name of this request's modal.
     */
    #[Computed]
    public function modalName(): string
    {
        return 'record-payment-'.$this->documentRequest->id;
    }

    /**
     * Determine whether this request is waiting for its fee.
     */
    #[Computed]
    public function awaitsPayment(): bool
    {
        return $this->documentRequest->awaitsPayment()
            && Auth::user()->can('recordPayment', $this->documentRequest);
    }

    /**
     * Write down the payment the cashier has already taken.
     */
    public function recordPayment(RecordPayment $recordPayment): void
    {
        Gate::authorize('recordPayment', $this->documentRequest);

        $validated = $this->validate([
            'amount' => [$this->waive ? 'nullable' : 'required', 'numeric', 'min:0', 'max:99999.99'],
            'orNumber' => [$this->waive ? 'nullable' : 'required', 'string', 'max:32'],
            'waive' => ['boolean'],
        ], [
            'orNumber.required' => __('Enter the official receipt number from the cashier.'),
            'amount.required' => __('Enter the amount that was paid.'),
        ]);

        try {
            $recordPayment(
                documentRequest: $this->documentRequest,
                actor: Auth::user(),
                amount: (float) ($validated['amount'] ?: 0),
                orNumber: $validated['orNumber'] ?: null,
                waive: $this->waive,
            );
        } catch (PaymentAlreadyRecordedException $exception) {
            $this->addError('amount', $exception->getMessage());

            return;
        }

        $this->documentRequest->refresh();
        unset($this->awaitsPayment);

        Flux::modal($this->modalName)->close();
        Flux::toast(variant: 'success', text: $this->waive
            ? __('The fee has been waived.')
            : __('Payment recorded.'));

        $this->dispatch('request-updated');
    }
}; ?>

<div>
    @if ($this->awaitsPayment)
        <flux:modal.trigger :name="$this->modalName">
            <flux:button variant="filled" size="sm" icon="banknotes" data-test="record-payment-trigger">
                {{ __('Record payment') }}
            </flux:button>
        </flux:modal.trigger>

        <flux:modal :name="$this->modalName" class="min-w-[24rem]">
            <form wire:submit="recordPayment" class="flex flex-col gap-6">
                <div class="flex flex-col gap-2">
                    <flux:heading size="lg">{{ __('Record payment') }}</flux:heading>
                    <flux:text>
                        {{ __(':document (:reference) carries a fee of :amount for :copies copy/copies.', [
                            'document' => $documentRequest->display_name,
                            'reference' => $documentRequest->reference_no,
                            'amount' => '₱' . number_format((float) $documentRequest->fee_amount, 2),
                            'copies' => $documentRequest->copies,
                        ]) }}
                    </flux:text>
                </div>

                <flux:switch
                    wire:model.live="waive"
                    :label="__('Waive the fee instead')"
                    :description="__('Use this when the registrar has decided not to charge for this request.')"
                    data-test="waive-switch"
                />

                @unless ($waive)
                    <flux:input
                        wire:model="amount"
                        :label="__('Amount paid')"
                        type="number"
                        step="0.01"
                        min="0"
                        required
                        data-test="amount-input"
                    />

                    <flux:input
                        wire:model="orNumber"
                        :label="__('Official receipt number')"
                        :description="__('As printed on the cashier\'s receipt.')"
                        required
                        data-test="or-number-input"
                    />
                @endunless

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>

                    <flux:button type="submit" variant="primary" data-test="confirm-record-payment">
                        {{ $waive ? __('Waive fee') : __('Record payment') }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>

<?php

use App\Actions\Requests\SubmitDocumentRequest;
use App\Models\DocumentType;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Request a document')] class extends Component {
    use WithFileUploads;

    public string $document_type_id = '';
    public string $other_document_name = '';
    public string $purpose = '';
    public int $copies = 1;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $attachments = [];

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        Gate::authorize('create', App\Models\DocumentRequest::class);
    }

    /**
     * Get the documents the registrar currently issues.
     *
     * @return Collection<int, DocumentType>
     */
    #[Computed]
    public function documentTypes(): Collection
    {
        return DocumentType::query()->active()->orderBy('name')->get();
    }

    /**
     * Get the document type currently chosen, if any.
     */
    #[Computed]
    public function selectedType(): ?DocumentType
    {
        return $this->documentTypes->firstWhere('id', (int) $this->document_type_id);
    }

    /**
     * Estimate when the finished document could be collected.
     */
    #[Computed]
    public function estimatedReadyDate(): ?string
    {
        if ($this->selectedType === null) {
            return null;
        }

        return now()->addWeekdays($this->selectedType->processing_days)->format('F j, Y');
    }

    /**
     * Submit the request to the registrar's office.
     */
    public function submit(SubmitDocumentRequest $submitDocumentRequest): void
    {
        Gate::authorize('create', App\Models\DocumentRequest::class);

        $this->validate(
            $submitDocumentRequest->rules($this->selectedType),
            $submitDocumentRequest->messages(),
        );

        $documentRequest = $submitDocumentRequest(
            student: Auth::user()->student,
            documentType: $this->selectedType,
            purpose: $this->purpose,
            copies: $this->copies,
            otherDocumentName: $this->other_document_name ?: null,
            attachments: $this->attachments,
        );

        Flux::toast(
            variant: 'success',
            text: __('Request submitted. Your reference number is :reference.', [
                'reference' => $documentRequest->reference_no,
            ]),
        );

        $this->redirectRoute('student.requests.show', $documentRequest, navigate: true);
    }

    /**
     * Drop a file from the pending upload list.
     */
    public function removeAttachment(int $index): void
    {
        unset($this->attachments[$index]);

        $this->attachments = array_values($this->attachments);
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <x-page-heading
        :heading="__('Request a document')"
        :subheading="__('Fill in the details below. You will receive a reference number to track your request.')"
    >
        <flux:button :href="route('student.requests.index')" variant="ghost" size="sm" wire:navigate>
            {{ __('Back to my requests') }}
        </flux:button>
    </x-page-heading>

    <flux:card>
        <form wire:submit="submit" class="flex max-w-2xl flex-col gap-6">
            <flux:select
                wire:model.live="document_type_id"
                :label="__('Document needed')"
                :placeholder="__('Choose a document')"
                required
                data-test="document-type-select"
            >
                @foreach ($this->documentTypes as $type)
                    <flux:select.option :value="$type->id" wire:key="type-{{ $type->id }}">
                        {{ $type->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            @if ($this->selectedType?->requires_custom_name)
                <flux:input
                    wire:model="other_document_name"
                    :label="__('Name of document')"
                    :description="__('Tell us exactly which record you need.')"
                    :placeholder="__('Certificate of Units Earned')"
                    required
                    data-test="other-document-name-input"
                />
            @endif

            @if ($this->selectedType)
                <flux:callout icon="information-circle">
                    <flux:callout.text>
                        {{ __('Processing usually takes :days working days. Estimated ready by :date.', [
                            'days' => $this->selectedType->processing_days,
                            'date' => $this->estimatedReadyDate,
                        ]) }}
                    </flux:callout.text>
                </flux:callout>
            @endif

            <flux:textarea
                wire:model="purpose"
                :label="__('Purpose')"
                :description="__('Why do you need this document? For example: employment requirement.')"
                rows="3"
                required
                data-test="purpose-input"
            />

            <flux:input
                wire:model="copies"
                :label="__('Number of copies')"
                type="number"
                min="1"
                max="10"
                required
                data-test="copies-input"
            />

            <div class="flex flex-col gap-2">
                <flux:input
                    type="file"
                    wire:model="attachments"
                    :label="__('Supporting requirements')"
                    :description="__('Optional. Up to :count files (:mimes), :size MB each.', [
                        'count' => config('registrar.attachments.max_files'),
                        'mimes' => strtoupper(implode(', ', config('registrar.attachments.mimes'))),
                        'size' => round(config('registrar.attachments.max_kb') / 1024),
                    ])"
                    multiple
                    data-test="attachments-input"
                />

                <div wire:loading wire:target="attachments">
                    <flux:text size="sm" class="text-zinc-500">{{ __('Uploading…') }}</flux:text>
                </div>

                @if ($this->attachments !== [])
                    <ul class="flex flex-col gap-1">
                        @foreach ($this->attachments as $index => $file)
                            <li class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 px-3 py-2" wire:key="file-{{ $index }}">
                                <flux:text size="sm" class="truncate">{{ $file->getClientOriginalName() }}</flux:text>

                                <flux:button
                                    type="button"
                                    size="xs"
                                    variant="subtle"
                                    icon="x-mark"
                                    wire:click="removeAttachment({{ $index }})"
                                    :aria-label="__('Remove file')"
                                />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="flex items-center gap-3">
                <flux:button type="submit" variant="primary" data-test="submit-request-button">
                    {{ __('Submit request') }}
                </flux:button>

                <flux:button :href="route('student.requests.index')" variant="ghost" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </form>
    </flux:card>
</div>

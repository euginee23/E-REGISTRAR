<?php

use App\Models\DocumentType;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Document types')] class extends Component {
    public ?int $editingId = null;
    public string $name = '';
    public string $description = '';
    public int $processingDays = 3;
    public bool $requiresCustomName = false;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        Gate::authorize('viewAny', DocumentType::class);
    }

    /**
     * Get every document type with its usage count.
     *
     * @return Collection<int, DocumentType>
     */
    #[Computed]
    public function documentTypes(): Collection
    {
        return DocumentType::query()
            ->withCount('documentRequests')
            ->orderBy('name')
            ->get();
    }

    /**
     * Open the modal ready to define a new document.
     */
    public function createType(): void
    {
        Gate::authorize('create', DocumentType::class);

        $this->reset('editingId', 'name', 'description', 'requiresCustomName');
        $this->processingDays = 3;
        $this->resetValidation();

        Flux::modal('document-type-form')->show();
    }

    /**
     * Open the modal to edit an existing document type.
     */
    public function editType(int $typeId): void
    {
        $type = DocumentType::query()->findOrFail($typeId);

        Gate::authorize('update', $type);

        $this->editingId = $type->id;
        $this->name = $type->name;
        $this->description = $type->description ?? '';
        $this->processingDays = $type->processing_days;
        $this->requiresCustomName = $type->requires_custom_name;
        $this->resetValidation();

        Flux::modal('document-type-form')->show();
    }

    /**
     * Create or update the document type.
     */
    public function saveType(): void
    {
        $type = $this->editingId === null ? null : DocumentType::query()->findOrFail($this->editingId);

        Gate::authorize($type === null ? 'create' : 'update', $type ?? DocumentType::class);

        $validated = $this->validate([
            'name' => [
                'required', 'string', 'max:150',
                $this->editingId === null
                    ? Rule::unique(DocumentType::class, 'name')
                    : Rule::unique(DocumentType::class, 'name')->ignore($this->editingId),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'processingDays' => ['required', 'integer', 'min:1', 'max:60'],
            'requiresCustomName' => ['boolean'],
        ]);

        $attributes = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'processing_days' => $validated['processingDays'],
            'requires_custom_name' => $validated['requiresCustomName'],
        ];

        if ($type === null) {
            DocumentType::create([...$attributes, 'slug' => Str::slug($validated['name']), 'is_active' => true]);
        } else {
            $type->update($attributes);
        }

        unset($this->documentTypes);

        Flux::modal('document-type-form')->close();
        Flux::toast(variant: 'success', text: $type === null
            ? __('Document type added.')
            : __('Document type updated.'));
    }

    /**
     * Retire or reinstate a document type.
     *
     * Types are never hard deleted: historical requests point at them, and
     * the foreign key is restricted so a delete would fail anyway.
     */
    public function toggleActive(int $typeId): void
    {
        $type = DocumentType::query()->findOrFail($typeId);

        Gate::authorize('update', $type);

        $type->forceFill(['is_active' => ! $type->is_active])->save();

        unset($this->documentTypes);

        Flux::toast(variant: 'success', text: $type->is_active
            ? __('Document type reinstated.')
            : __('Document type retired. Existing requests are unaffected.'));
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <x-page-heading
        :heading="__('Document types')"
        :subheading="__('The academic documents students may request, and how long each takes.')"
    >
        <flux:button wire:click="createType" variant="primary" size="sm" icon="plus" data-test="create-type">
            {{ __('New document type') }}
        </flux:button>
    </x-page-heading>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Document') }}</flux:table.column>
            <flux:table.column>{{ __('Processing days') }}</flux:table.column>
            <flux:table.column>{{ __('Requests') }}</flux:table.column>
            <flux:table.column>{{ __('State') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->documentTypes as $type)
                <flux:table.row wire:key="type-{{ $type->id }}">
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <flux:text size="sm">{{ $type->name }}</flux:text>
                            @if ($type->requires_custom_name)
                                <flux:text size="sm" class="text-zinc-400">
                                    {{ __('Requester names the document') }}
                                </flux:text>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="tabular-nums">{{ $type->processing_days }}</flux:table.cell>
                    <flux:table.cell class="tabular-nums">{{ $type->document_requests_count }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="$type->is_active ? 'green' : 'zinc'" size="sm">
                            {{ $type->is_active ? __('Available') : __('Retired') }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-1">
                            <flux:button
                                wire:click="editType({{ $type->id }})"
                                size="xs"
                                variant="ghost"
                                data-test="edit-type"
                            >
                                {{ __('Edit') }}
                            </flux:button>

                            <flux:button
                                wire:click="toggleActive({{ $type->id }})"
                                size="xs"
                                variant="subtle"
                                data-test="toggle-type"
                            >
                                {{ $type->is_active ? __('Retire') : __('Reinstate') }}
                            </flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:modal name="document-type-form" class="min-w-[26rem]">
        <form wire:submit="saveType" class="flex flex-col gap-6">
            <div class="flex flex-col gap-2">
                <flux:heading size="lg">
                    {{ $editingId === null ? __('New document type') : __('Edit document type') }}
                </flux:heading>
                <flux:text>{{ __('Processing days are counted as working days.') }}</flux:text>
            </div>

            <flux:input wire:model="name" :label="__('Name')" required data-test="type-name-input" />

            <flux:textarea
                wire:model="description"
                :label="__('Description')"
                rows="2"
                data-test="type-description-input"
            />

            <flux:input
                wire:model="processingDays"
                :label="__('Processing days')"
                type="number"
                min="1"
                max="60"
                required
                data-test="type-days-input"
            />

            <flux:switch
                wire:model="requiresCustomName"
                :label="__('Requester names the document')"
                :description="__('Use this for a catch-all such as \'Other Academic Document\'.')"
                data-test="type-custom-name-switch"
            />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary" data-test="save-type">
                    {{ __('Save') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>

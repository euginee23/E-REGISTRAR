<?php

use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Request queue')] class extends Component {
    use WithPagination;

    #[Url]
    public string $status = '';

    #[Url]
    public string $documentType = '';

    #[Url]
    public string $search = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $until = '';

    /**
     * Reset paging whenever a filter narrows the result set.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'documentType', 'search', 'from', 'until'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Refresh the queue after a child component changes a request.
     */
    #[On('request-updated')]
    public function refreshQueue(): void
    {
        unset($this->requests);
    }

    /**
     * Clear every filter.
     */
    public function clearFilters(): void
    {
        $this->reset('status', 'documentType', 'search', 'from', 'until');
        $this->resetPage();
    }

    /**
     * Get the filtered request queue, oldest first so nothing is forgotten.
     *
     * @return LengthAwarePaginator<int, DocumentRequest>
     */
    #[Computed]
    public function requests(): LengthAwarePaginator
    {
        return DocumentRequest::query()
            ->with(['documentType', 'student.user', 'processedBy', 'appointment.timeSlot'])
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->documentType !== '', fn ($query) => $query->where('document_type_id', $this->documentType))
            ->when($this->from !== '', fn ($query) => $query->whereDate('created_at', '>=', $this->from))
            ->when($this->until !== '', fn ($query) => $query->whereDate('created_at', '<=', $this->until))
            ->when($this->search !== '', fn ($query) => $query->where(function ($query): void {
                $query->where('reference_no', 'like', '%'.$this->search.'%')
                    ->orWhereHas('student.user', fn ($user) => $user->where('name', 'like', '%'.$this->search.'%'))
                    ->orWhereHas('student', fn ($student) => $student->where('student_number', 'like', '%'.$this->search.'%'));
            }))
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [RequestStatus::Pending->value])
            ->oldest()
            ->paginate(15);
    }

    /**
     * Get the document types available as a filter.
     *
     * @return Collection<int, DocumentType>
     */
    #[Computed]
    public function documentTypes(): Collection
    {
        return DocumentType::query()->orderBy('name')->get();
    }

    /**
     * Determine whether any filter is currently applied.
     */
    #[Computed]
    public function isFiltered(): bool
    {
        return $this->status !== ''
            || $this->documentType !== ''
            || $this->search !== ''
            || $this->from !== ''
            || $this->until !== '';
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <x-page-heading
        :heading="__('Request queue')"
        :subheading="__('Pending requests are listed first, then the longest waiting.')"
    />

    <div class="flex flex-wrap items-end gap-3">
        <flux:input
            wire:model.live.debounce.300ms="search"
            :label="__('Search')"
            :placeholder="__('Reference, student name, or number')"
            icon="magnifying-glass"
            class="max-w-xs"
            data-test="search-input"
        />

        <flux:select wire:model.live="status" :label="__('Status')" class="max-w-44" data-test="status-filter">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach (App\Enums\RequestStatus::cases() as $status)
                <flux:select.option :value="$status->value" wire:key="status-{{ $status->value }}">
                    {{ $status->label() }}
                </flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="documentType" :label="__('Document')" class="max-w-52" data-test="document-type-filter">
            <flux:select.option value="">{{ __('All documents') }}</flux:select.option>
            @foreach ($this->documentTypes as $type)
                <flux:select.option :value="$type->id" wire:key="doctype-{{ $type->id }}">
                    {{ $type->name }}
                </flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model.live="from" :label="__('From')" type="date" class="max-w-40" data-test="from-filter" />
        <flux:input wire:model.live="until" :label="__('Until')" type="date" class="max-w-40" data-test="until-filter" />

        @if ($this->isFiltered)
            <flux:button wire:click="clearFilters" variant="ghost" size="sm" data-test="clear-filters">
                {{ __('Clear') }}
            </flux:button>
        @endif
    </div>

    @if ($this->requests->isEmpty())
        <x-empty-state
            icon="inbox"
            :heading="$this->isFiltered ? __('No matching requests') : __('The queue is empty')"
            :description="$this->isFiltered
                ? __('Try widening your filters.')
                : __('Every submitted request has been dealt with.')"
        />
    @else
        <flux:table :paginate="$this->requests">
            <flux:table.columns>
                <flux:table.column>{{ __('Reference') }}</flux:table.column>
                <flux:table.column>{{ __('Student') }}</flux:table.column>
                <flux:table.column>{{ __('Document') }}</flux:table.column>
                <flux:table.column>{{ __('Submitted') }}</flux:table.column>
                <flux:table.column>{{ __('Handled by') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->requests as $request)
                    <flux:table.row wire:key="request-{{ $request->id }}">
                        <flux:table.cell class="font-mono text-xs">{{ $request->reference_no }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <flux:text size="sm">{{ $request->student->user->name }}</flux:text>
                                @if ($request->student->student_number)
                                    <flux:text size="sm" class="text-zinc-400">{{ $request->student->student_number }}</flux:text>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $request->display_name }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <flux:text size="sm">{{ $request->created_at?->format('M j, Y') }}</flux:text>
                                <flux:text size="sm" class="text-zinc-400">{{ $request->created_at?->diffForHumans() }}</flux:text>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text size="sm" class="text-zinc-500">
                                {{ $request->processedBy?->name ?? '—' }}
                            </flux:text>
                        </flux:table.cell>
                        <flux:table.cell><x-status-badge :status="$request->status" /></flux:table.cell>
                        <flux:table.cell>
                            <flux:button
                                :href="route('registrar.requests.show', $request)"
                                size="xs"
                                variant="ghost"
                                wire:navigate
                            >
                                {{ __('Open') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>

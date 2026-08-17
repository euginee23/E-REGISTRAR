<?php

use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('My requests')] class extends Component {
    use WithPagination;

    #[Url]
    public string $status = '';

    #[Url]
    public string $search = '';

    /**
     * Reset paging whenever the filters narrow the result set.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'search'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Get the signed-in student's requests, newest first.
     *
     * @return LengthAwarePaginator<int, DocumentRequest>
     */
    #[Computed]
    public function requests(): LengthAwarePaginator
    {
        return DocumentRequest::query()
            ->forStudent(Auth::user()->student)
            ->with(['documentType', 'appointment.timeSlot'])
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->search !== '', fn ($query) => $query->where(function ($query): void {
                $query->where('reference_no', 'like', '%'.$this->search.'%')
                    ->orWhere('purpose', 'like', '%'.$this->search.'%')
                    ->orWhereHas('documentType', fn ($type) => $type->where('name', 'like', '%'.$this->search.'%'));
            }))
            ->latest()
            ->paginate(10);
    }

    /**
     * Determine whether any filter is currently narrowing the list.
     */
    #[Computed]
    public function isFiltered(): bool
    {
        return $this->status !== '' || $this->search !== '';
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <x-page-heading
        :heading="__('My requests')"
        :subheading="__('Every document you have requested from the registrar.')"
    >
        <flux:button :href="route('student.requests.create')" variant="primary" icon="plus" size="sm" wire:navigate>
            {{ __('New request') }}
        </flux:button>
    </x-page-heading>

    <div class="flex flex-wrap items-end gap-3">
        <flux:input
            wire:model.live.debounce.300ms="search"
            :placeholder="__('Search reference, document, or purpose')"
            icon="magnifying-glass"
            class="max-w-xs"
            :label="__('Search')"
            data-test="search-input"
        />

        <flux:select
            wire:model.live="status"
            :label="__('Status')"
            class="max-w-48"
            data-test="status-filter"
        >
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach (App\Enums\RequestStatus::cases() as $status)
                <flux:select.option :value="$status->value" wire:key="status-{{ $status->value }}">
                    {{ $status->label() }}
                </flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if ($this->requests->isEmpty())
        <x-empty-state
            icon="document-text"
            :heading="$this->isFiltered ? __('No matching requests') : __('No requests yet')"
            :description="$this->isFiltered
                ? __('Try a different search term or status.')
                : __('Request a Form 137, Transcript of Records, or any other academic document to get started.')"
        >
            @unless ($this->isFiltered)
                <flux:button :href="route('student.requests.create')" variant="primary" size="sm" wire:navigate>
                    {{ __('Request a document') }}
                </flux:button>
            @endunless
        </x-empty-state>
    @else
        <flux:table :paginate="$this->requests">
            <flux:table.columns>
                <flux:table.column>{{ __('Reference') }}</flux:table.column>
                <flux:table.column>{{ __('Document') }}</flux:table.column>
                <flux:table.column>{{ __('Copies') }}</flux:table.column>
                <flux:table.column>{{ __('Submitted') }}</flux:table.column>
                <flux:table.column>{{ __('Appointment') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->requests as $request)
                    <flux:table.row wire:key="request-{{ $request->id }}">
                        <flux:table.cell class="font-mono text-xs">{{ $request->reference_no }}</flux:table.cell>
                        <flux:table.cell>{{ $request->display_name }}</flux:table.cell>
                        <flux:table.cell>{{ $request->copies }}</flux:table.cell>
                        <flux:table.cell>{{ $request->created_at?->format('M j, Y') }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($request->appointment)
                                {{ $request->appointment->timeSlot->slot_date->format('M j') }},
                                {{ $request->appointment->timeSlot->label }}
                            @else
                                <flux:text size="sm" class="text-zinc-400">{{ __('Not booked') }}</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell><x-status-badge :status="$request->status" /></flux:table.cell>
                        <flux:table.cell>
                            <flux:button
                                :href="route('student.requests.show', $request)"
                                size="xs"
                                variant="ghost"
                                wire:navigate
                            >
                                {{ __('View') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>

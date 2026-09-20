<?php

use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
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

    #[Url]
    public string $paymentStatus = '';

    #[Url]
    public string $assignment = '';

    #[Url]
    public string $appointment = '';

    #[Url]
    public string $sort = self::DEFAULT_SORT;

    #[Url]
    public string $direction = 'asc';

    #[Url]
    public int $perPage = self::DEFAULT_PER_PAGE;

    /**
     * The column the queue falls back to, and the one that keeps the
     * pending-first rule the desk relies on.
     */
    private const DEFAULT_SORT = 'submitted';

    private const DEFAULT_PER_PAGE = 15;

    /**
     * The sortable columns, mapped to what they order by.
     *
     * Whitelisted so a hand-edited query string can never reach the database.
     *
     * @var array<string, string>
     */
    private const SORTABLE = [
        'submitted' => 'document_requests.created_at',
        'status' => 'document_requests.status',
        'payment' => 'document_requests.payment_status',
        'student' => 'users.name',
        'document' => 'document_types.name',
    ];

    /**
     * The page sizes the queue offers.
     *
     * @var array<int, int>
     */
    private const PER_PAGE_OPTIONS = [10, 15, 25, 50, 100];

    /**
     * Reset paging whenever a filter narrows the result set.
     */
    public function updated(string $property): void
    {
        if (in_array($property, [
            'status', 'documentType', 'search', 'from', 'until',
            'paymentStatus', 'assignment', 'appointment', 'perPage',
        ], true)) {
            $this->resetPage();
        }
    }

    /**
     * Keep the page size to one of the offered options.
     */
    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, self::PER_PAGE_OPTIONS, true)) {
            $this->perPage = self::DEFAULT_PER_PAGE;
        }
    }

    /**
     * Sort by a column, flipping the direction when it is already sorted.
     */
    public function sortBy(string $column): void
    {
        if (! array_key_exists($column, self::SORTABLE)) {
            return;
        }

        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $column;
            $this->direction = 'asc';
        }

        $this->resetPage();
    }

    /**
     * Get the column the queue is sorted by, falling back when forged.
     */
    #[Computed]
    public function sortColumn(): string
    {
        return array_key_exists($this->sort, self::SORTABLE) ? $this->sort : self::DEFAULT_SORT;
    }

    /**
     * Get the sort direction, falling back when forged.
     */
    #[Computed]
    public function sortDirection(): string
    {
        return in_array($this->direction, ['asc', 'desc'], true) ? $this->direction : 'asc';
    }

    /**
     * Get the page size, falling back when forged.
     */
    #[Computed]
    public function pageSize(): int
    {
        return in_array($this->perPage, self::PER_PAGE_OPTIONS, true)
            ? $this->perPage
            : self::DEFAULT_PER_PAGE;
    }

    /**
     * Get the page sizes offered in the picker.
     *
     * @return array<int, int>
     */
    #[Computed]
    public function perPageOptions(): array
    {
        return self::PER_PAGE_OPTIONS;
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
        // Sorting and page size are how the user likes to read the queue
        // rather than what they are looking for, so they survive a clear.
        $this->reset('status', 'documentType', 'search', 'from', 'until', 'paymentStatus', 'assignment', 'appointment');
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
        $query = DocumentRequest::query()
            ->select('document_requests.*')
            ->with(['documentType', 'student.user', 'processedBy', 'appointment.timeSlot'])
            ->when($this->status !== '', fn ($query) => $query->where('document_requests.status', $this->status))
            ->when($this->documentType !== '', fn ($query) => $query->where('document_type_id', $this->documentType))
            ->when($this->paymentStatus !== '', fn ($query) => $query->where('payment_status', $this->paymentStatus))
            ->when($this->from !== '', fn ($query) => $query->whereDate('document_requests.created_at', '>=', $this->from))
            ->when($this->until !== '', fn ($query) => $query->whereDate('document_requests.created_at', '<=', $this->until))
            ->when($this->search !== '', fn ($query) => $query->where(function ($query): void {
                $query->where('reference_no', 'like', '%'.$this->search.'%')
                    ->orWhereHas('student.user', fn ($user) => $user->where('name', 'like', '%'.$this->search.'%'))
                    ->orWhereHas('student', fn ($student) => $student->where('student_number', 'like', '%'.$this->search.'%'));
            }));

        $this->applyAssignmentFilter($query);
        $this->applyAppointmentFilter($query);
        $this->applySorting($query);

        return $query->paginate($this->pageSize);
    }

    /**
     * Narrow the queue by who is handling the request.
     *
     * @param  Builder<DocumentRequest>  $query
     */
    private function applyAssignmentFilter(Builder $query): void
    {
        match ($this->assignment) {
            'mine' => $query->where('processed_by_user_id', Auth::id()),
            'unassigned' => $query->whereNull('processed_by_user_id'),
            'others' => $query->whereNotNull('processed_by_user_id')
                ->where('processed_by_user_id', '!=', Auth::id()),
            default => null,
        };
    }

    /**
     * Narrow the queue by whether a claiming appointment is held.
     *
     * A cancelled appointment does not count as booked, so this looks for one
     * that still occupies a seat rather than merely existing.
     *
     * @param  Builder<DocumentRequest>  $query
     */
    private function applyAppointmentFilter(Builder $query): void
    {
        match ($this->appointment) {
            'booked' => $query->whereHas('appointment', fn ($appointment) => $appointment->occupying()),
            'none' => $query->whereDoesntHave('appointment', fn ($appointment) => $appointment->occupying()),
            default => null,
        };
    }

    /**
     * Order the queue by the chosen column.
     *
     * The default sort keeps the pending-first rule the desk works to; an
     * explicit sort is taken at face value, because the user asked for it.
     *
     * @param  Builder<DocumentRequest>  $query
     */
    private function applySorting(Builder $query): void
    {
        $column = $this->sortColumn;
        $direction = $this->sortDirection;

        if ($column === 'student') {
            $query->leftJoin('students', 'students.id', '=', 'document_requests.student_id')
                ->leftJoin('users', 'users.id', '=', 'students.user_id');
        }

        if ($column === 'document') {
            $query->leftJoin('document_types', 'document_types.id', '=', 'document_requests.document_type_id');
        }

        if ($column === self::DEFAULT_SORT) {
            $query->orderByRaw('CASE WHEN document_requests.status = ? THEN 0 ELSE 1 END', [RequestStatus::Pending->value]);
        }

        $query->orderBy(self::SORTABLE[$column], $direction);
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
            || $this->until !== ''
            || $this->paymentStatus !== ''
            || $this->assignment !== ''
            || $this->appointment !== '';
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <x-page-heading
        :heading="__('Request queue')"
        :subheading="$this->sortColumn === 'submitted'
            ? __('Pending requests are listed first, then the longest waiting.')
            : __('Sorted by :column.', ['column' => $this->sortColumn])"
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

        <flux:select wire:model.live="paymentStatus" :label="__('Payment')" class="max-w-44" data-test="payment-filter">
            <flux:select.option value="">{{ __('Any payment') }}</flux:select.option>
            @foreach (App\Enums\PaymentStatus::cases() as $payment)
                <flux:select.option :value="$payment->value" wire:key="payment-{{ $payment->value }}">
                    {{ $payment->label() }}
                </flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="assignment" :label="__('Handled by')" class="max-w-44" data-test="assignment-filter">
            <flux:select.option value="">{{ __('Anyone') }}</flux:select.option>
            <flux:select.option value="mine">{{ __('Me') }}</flux:select.option>
            <flux:select.option value="unassigned">{{ __('Nobody yet') }}</flux:select.option>
            <flux:select.option value="others">{{ __('Other staff') }}</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="appointment" :label="__('Appointment')" class="max-w-44" data-test="appointment-filter">
            <flux:select.option value="">{{ __('Any') }}</flux:select.option>
            <flux:select.option value="booked">{{ __('Booked') }}</flux:select.option>
            <flux:select.option value="none">{{ __('Not booked') }}</flux:select.option>
        </flux:select>

        <flux:input wire:model.live="from" :label="__('From')" type="date" class="max-w-40" data-test="from-filter" />
        <flux:input wire:model.live="until" :label="__('Until')" type="date" class="max-w-40" data-test="until-filter" />

        <flux:select wire:model.live="perPage" :label="__('Per page')" class="max-w-28" data-test="per-page-filter">
            @foreach ($this->perPageOptions as $option)
                <flux:select.option :value="$option" wire:key="per-page-{{ $option }}">{{ $option }}</flux:select.option>
            @endforeach
        </flux:select>

        @if ($this->isFiltered)
            <flux:button wire:click="clearFilters" variant="ghost" size="sm" data-test="clear-filters">
                {{ __('Clear') }}
            </flux:button>
        @endif
    </div>

    @unless ($this->requests->isEmpty())
        <flux:text size="sm" class="text-zinc-500" data-test="result-count">
            {{ __('Showing :first–:last of :total requests', [
                'first' => $this->requests->firstItem(),
                'last' => $this->requests->lastItem(),
                'total' => $this->requests->total(),
            ]) }}
        </flux:text>
    @endunless

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

                <flux:table.column
                    sortable
                    :sorted="$this->sortColumn === 'student'"
                    :direction="$this->sortDirection"
                    wire:click="sortBy('student')"
                    data-test="sort-student"
                >{{ __('Student') }}</flux:table.column>

                <flux:table.column
                    sortable
                    :sorted="$this->sortColumn === 'document'"
                    :direction="$this->sortDirection"
                    wire:click="sortBy('document')"
                    data-test="sort-document"
                >{{ __('Document') }}</flux:table.column>

                <flux:table.column
                    sortable
                    :sorted="$this->sortColumn === 'submitted'"
                    :direction="$this->sortDirection"
                    wire:click="sortBy('submitted')"
                    data-test="sort-submitted"
                >{{ __('Submitted') }}</flux:table.column>

                <flux:table.column>{{ __('Handled by') }}</flux:table.column>

                <flux:table.column
                    sortable
                    :sorted="$this->sortColumn === 'payment'"
                    :direction="$this->sortDirection"
                    wire:click="sortBy('payment')"
                    data-test="sort-payment"
                >{{ __('Payment') }}</flux:table.column>

                <flux:table.column
                    sortable
                    :sorted="$this->sortColumn === 'status'"
                    :direction="$this->sortDirection"
                    wire:click="sortBy('status')"
                    data-test="sort-status"
                >{{ __('Status') }}</flux:table.column>

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
                        <flux:table.cell>
                            @if ($request->payment_status === App\Enums\PaymentStatus::NotRequired)
                                <flux:text size="sm" class="text-zinc-400">{{ __('No fee') }}</flux:text>
                            @else
                                <x-status-badge :status="$request->payment_status" />
                            @endif
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

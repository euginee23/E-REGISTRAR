<?php

use App\Actions\Registry\ImportStudentRegistryCsv;
use App\Models\StudentRegistryEntry;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Student registry')] class extends Component {
    use WithFileUploads, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $claimed = '';

    public ?int $editingId = null;

    public string $student_number = '';

    public string $name = '';

    public string $course = '';

    public ?int $year_graduated = null;

    public ?TemporaryUploadedFile $csv = null;

    /**
     * The outcome of the most recent import, kept to show the summary.
     *
     * @var array{imported: int, updated: int, skipped: int, errors: array<int, string>}|null
     */
    public ?array $importResult = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        Gate::authorize('viewAny', StudentRegistryEntry::class);
    }

    /**
     * Reset paging whenever a filter narrows the result set.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'claimed'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Get the filtered roster.
     *
     * @return LengthAwarePaginator<int, StudentRegistryEntry>
     */
    #[Computed]
    public function entries(): LengthAwarePaginator
    {
        return StudentRegistryEntry::query()
            ->with('claimedBy')
            ->when($this->search !== '', fn ($query) => $query->search($this->search))
            ->when($this->claimed === 'claimed', fn ($query) => $query->claimed())
            ->when($this->claimed === 'unclaimed', fn ($query) => $query->unclaimed())
            ->orderBy('student_number')
            ->paginate(15);
    }

    /**
     * Determine whether any filter is currently applied.
     */
    #[Computed]
    public function isFiltered(): bool
    {
        return $this->search !== '' || $this->claimed !== '';
    }

    /**
     * Clear every filter.
     */
    public function clearFilters(): void
    {
        $this->reset('search', 'claimed');
        $this->resetPage();
    }

    /**
     * Open the modal ready to add a student to the roster.
     */
    public function createEntry(): void
    {
        Gate::authorize('create', StudentRegistryEntry::class);

        $this->reset('editingId', 'student_number', 'name', 'course', 'year_graduated');
        $this->resetValidation();

        Flux::modal('registry-form')->show();
    }

    /**
     * Open the modal to correct an existing roster entry.
     */
    public function editEntry(int $entryId): void
    {
        $entry = StudentRegistryEntry::query()->findOrFail($entryId);

        Gate::authorize('update', $entry);

        $this->editingId = $entry->id;
        $this->student_number = $entry->student_number;
        $this->name = $entry->name;
        $this->course = $entry->course;
        $this->year_graduated = $entry->year_graduated;
        $this->resetValidation();

        Flux::modal('registry-form')->show();
    }

    /**
     * Add or correct a roster entry.
     */
    public function saveEntry(): void
    {
        $entry = $this->editingId === null
            ? null
            : StudentRegistryEntry::query()->findOrFail($this->editingId);

        $entry === null
            ? Gate::authorize('create', StudentRegistryEntry::class)
            : Gate::authorize('update', $entry);

        $validated = $this->validate([
            'student_number' => [
                'required', 'string', 'max:32',
                $this->editingId === null
                    ? Rule::unique(StudentRegistryEntry::class, 'student_number')
                    : Rule::unique(StudentRegistryEntry::class, 'student_number')->ignore($this->editingId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'course' => ['required', 'string', 'max:150'],
            'year_graduated' => ['nullable', 'integer', 'min:1950', 'max:'.date('Y')],
        ]);

        $attributes = [
            'student_number' => Str::upper(trim($validated['student_number'])),
            'name' => $validated['name'],
            'course' => $validated['course'],
            'year_graduated' => $validated['year_graduated'],
        ];

        $entry === null
            ? StudentRegistryEntry::create($attributes)
            : $entry->update($attributes);

        unset($this->entries);

        Flux::modal('registry-form')->close();
        Flux::toast(variant: 'success', text: $entry === null
            ? __('Student added to the registry.')
            : __('Registry entry updated.'));
    }

    /**
     * Remove an unclaimed student from the roster.
     */
    public function deleteEntry(int $entryId): void
    {
        $entry = StudentRegistryEntry::query()->findOrFail($entryId);

        Gate::authorize('delete', $entry);

        $entry->delete();

        unset($this->entries);

        Flux::toast(variant: 'success', text: __('Student removed from the registry.'));
    }

    /**
     * Load a roster export into the registry.
     */
    public function importCsv(ImportStudentRegistryCsv $importStudentRegistryCsv): void
    {
        Gate::authorize('create', StudentRegistryEntry::class);

        $this->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        try {
            $this->importResult = $importStudentRegistryCsv($this->csv->getRealPath());
        } catch (\Throwable $exception) {
            $this->addError('csv', $exception->getMessage());

            return;
        }

        $this->reset('csv');
        unset($this->entries);

        Flux::modal('registry-import')->close();
        Flux::toast(variant: 'success', text: __('Registry import finished.'));
    }

    /**
     * Open the import modal.
     */
    public function startImport(): void
    {
        Gate::authorize('create', StudentRegistryEntry::class);

        $this->reset('csv', 'importResult');
        $this->resetValidation();

        Flux::modal('registry-import')->show();
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <x-page-heading
        :heading="__('Student registry')"
        :subheading="__('The roster registration is checked against, so a student number can only be claimed by the student it belongs to.')"
    >
        <flux:button wire:click="startImport" size="sm" variant="subtle" icon="arrow-up-tray" data-test="start-import">
            {{ __('Import CSV') }}
        </flux:button>

        <flux:button wire:click="createEntry" variant="primary" size="sm" icon="plus" data-test="create-entry">
            {{ __('Add student') }}
        </flux:button>
    </x-page-heading>

    @if ($importResult !== null)
        <flux:callout
            :variant="$importResult['errors'] === [] ? 'success' : 'warning'"
            icon="document-check"
            data-test="import-summary"
        >
            <flux:callout.heading>{{ __('Import finished') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __(':imported added, :updated updated, :skipped skipped.', [
                    'imported' => $importResult['imported'],
                    'updated' => $importResult['updated'],
                    'skipped' => $importResult['skipped'],
                ]) }}
            </flux:callout.text>

            @if ($importResult['errors'] !== [])
                <flux:callout.text>
                    <ul class="list-disc ps-4">
                        @foreach (array_slice($importResult['errors'], 0, 10) as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </flux:callout.text>
            @endif
        </flux:callout>
    @endif

    <div class="flex flex-wrap items-end gap-3">
        <flux:input
            wire:model.live.debounce.300ms="search"
            :label="__('Search')"
            :placeholder="__('Student number, name, or course')"
            class="max-w-xs"
            data-test="registry-search"
        />

        <flux:select wire:model.live="claimed" :label="__('Account')" class="max-w-[12rem]" data-test="registry-claimed-filter">
            <flux:select.option value="">{{ __('All entries') }}</flux:select.option>
            <flux:select.option value="unclaimed">{{ __('No account yet') }}</flux:select.option>
            <flux:select.option value="claimed">{{ __('Registered') }}</flux:select.option>
        </flux:select>

        @if ($this->isFiltered)
            <flux:button wire:click="clearFilters" size="sm" variant="ghost" data-test="clear-registry-filters">
                {{ __('Clear') }}
            </flux:button>
        @endif
    </div>

    <flux:table :paginate="$this->entries">
        <flux:table.columns>
            <flux:table.column>{{ __('Student number') }}</flux:table.column>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Course') }}</flux:table.column>
            <flux:table.column>{{ __('Account') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->entries as $entry)
                <flux:table.row wire:key="entry-{{ $entry->id }}">
                    <flux:table.cell class="font-mono text-xs">{{ $entry->student_number }}</flux:table.cell>
                    <flux:table.cell>{{ $entry->name }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <flux:text size="sm">{{ $entry->course }}</flux:text>
                            @if ($entry->year_graduated !== null)
                                <flux:text size="sm" class="text-zinc-400">
                                    {{ __('Graduated :year', ['year' => $entry->year_graduated]) }}
                                </flux:text>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($entry->isClaimed())
                            <flux:badge color="green" size="sm" data-test="entry-claimed">
                                {{ $entry->claimedBy?->email ?? __('Registered') }}
                            </flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">{{ __('No account yet') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-1">
                            <flux:button
                                wire:click="editEntry({{ $entry->id }})"
                                size="xs"
                                variant="ghost"
                                data-test="edit-entry"
                            >
                                {{ __('Edit') }}
                            </flux:button>

                            @can('delete', $entry)
                                <flux:button
                                    wire:click="deleteEntry({{ $entry->id }})"
                                    wire:confirm="{{ __('Remove this student from the registry?') }}"
                                    size="xs"
                                    variant="subtle"
                                    data-test="delete-entry"
                                >
                                    {{ __('Remove') }}
                                </flux:button>
                            @endcan
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">
                        <flux:text class="text-zinc-400">
                            {{ $this->isFiltered
                                ? __('No registry entries match those filters.')
                                : __('The registry is empty. Import a roster export to get started.') }}
                        </flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="registry-form" class="min-w-[26rem]">
        <form wire:submit="saveEntry" class="flex flex-col gap-6">
            <div class="flex flex-col gap-2">
                <flux:heading size="lg">
                    {{ $editingId === null ? __('Add student') : __('Edit registry entry') }}
                </flux:heading>
                <flux:text>
                    {{ __('The name here is what a registration must match, so record it as it appears on the student\'s records.') }}
                </flux:text>
            </div>

            <flux:input
                wire:model="student_number"
                :label="__('Student number')"
                required
                data-test="entry-number-input"
            />

            <flux:input wire:model="name" :label="__('Full name')" required data-test="entry-name-input" />

            <flux:input wire:model="course" :label="__('Course')" required data-test="entry-course-input" />

            <flux:input
                wire:model="year_graduated"
                :label="__('Year graduated')"
                :description="__('Leave blank for a currently enrolled student.')"
                type="number"
                min="1950"
                :max="date('Y')"
                data-test="entry-year-input"
            />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary" data-test="save-entry">
                    {{ __('Save') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="registry-import" class="min-w-[26rem]">
        <form wire:submit="importCsv" class="flex flex-col gap-6">
            <div class="flex flex-col gap-2">
                <flux:heading size="lg">{{ __('Import registry') }}</flux:heading>
                <flux:text>
                    {{ __('A CSV with a header row of: student_number, name, course, year_graduated. Entries that already have an account are left untouched.') }}
                </flux:text>
            </div>

            <flux:input
                wire:model="csv"
                type="file"
                accept=".csv,text/csv"
                :label="__('Roster file')"
                required
                data-test="registry-csv-input"
            />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary" data-test="run-import">
                    {{ __('Import') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>

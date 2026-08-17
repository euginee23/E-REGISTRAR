<?php

use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    /**
     * Get the signed-in user's student profile, if they have one.
     *
     * Registration always creates a profile, but an administrator can create
     * a student account without one, so this must degrade gracefully.
     */
    #[Computed]
    public function student(): ?Student
    {
        return Auth::user()->student;
    }

    /**
     * Count the signed-in student's requests grouped by status.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        if ($this->student === null) {
            return [];
        }

        return DocumentRequest::query()
            ->forStudent($this->student)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
    }

    /**
     * Get the student's most recent requests.
     *
     * @return Collection<int, DocumentRequest>
     */
    #[Computed]
    public function recentRequests(): Collection
    {
        if ($this->student === null) {
            return new Collection;
        }

        return DocumentRequest::query()
            ->forStudent($this->student)
            ->with('documentType')
            ->latest()
            ->take(5)
            ->get();
    }

    /**
     * Count how many of the student's requests are still in progress.
     */
    #[Computed]
    public function openCount(): int
    {
        return collect(RequestStatus::open())
            ->sum(fn (RequestStatus $status): int => $this->counts[$status->value] ?? 0);
    }
}; ?>

<div class="flex flex-col gap-6" data-test="student-dashboard">
    <x-page-heading
        :heading="__('Welcome back, :name', ['name' => auth()->user()->name])"
        :subheading="__('Track your document requests and claiming appointments.')"
    />

    @if ($this->student === null)
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>{{ __('Complete your student profile') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('The registrar needs your course and contact details before you can request documents.') }}
            </flux:callout.text>
            <x-slot name="actions">
                <flux:button :href="route('student-profile.edit')" size="sm" wire:navigate>
                    {{ __('Complete profile') }}
                </flux:button>
            </x-slot>
        </flux:callout>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card
            :label="__('In progress')"
            :value="$this->openCount"
            icon="clock"
            :hint="__('Requests still being handled')"
        />

        <x-stat-card
            :label="__('Ready for release')"
            :value="$this->counts[App\Enums\RequestStatus::ReadyForRelease->value] ?? 0"
            icon="check-badge"
            :hint="__('Waiting to be claimed')"
        />

        <x-stat-card
            :label="__('Released')"
            :value="$this->counts[App\Enums\RequestStatus::Released->value] ?? 0"
            icon="document-check"
            :hint="__('Documents you have received')"
        />

        <x-stat-card
            :label="__('Total requests')"
            :value="array_sum($this->counts)"
            icon="document-text"
            :hint="__('All time')"
        />
    </div>

    <flux:card class="flex flex-col gap-4">
        <flux:heading size="sm">{{ __('Recent requests') }}</flux:heading>

        @if ($this->recentRequests->isEmpty())
            <x-empty-state
                icon="document-text"
                :heading="__('No requests yet')"
                :description="__('When you request a document from the registrar, it will appear here.')"
            />
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Reference') }}</flux:table.column>
                    <flux:table.column>{{ __('Document') }}</flux:table.column>
                    <flux:table.column>{{ __('Submitted') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->recentRequests as $request)
                        <flux:table.row :key="$request->id">
                            <flux:table.cell class="font-mono text-xs">{{ $request->reference_no }}</flux:table.cell>
                            <flux:table.cell>{{ $request->display_name }}</flux:table.cell>
                            <flux:table.cell>{{ $request->created_at?->format('M j, Y') }}</flux:table.cell>
                            <flux:table.cell><x-status-badge :status="$request->status" /></flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</div>

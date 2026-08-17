<?php

use App\Enums\RequestStatus;
use App\Models\Appointment;
use App\Models\DocumentRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    /**
     * Count all requests grouped by status.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        return DocumentRequest::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
    }

    /**
     * Get the requests waiting longest for a first response.
     *
     * @return Collection<int, DocumentRequest>
     */
    #[Computed]
    public function oldestPending(): Collection
    {
        return DocumentRequest::query()
            ->withStatus(RequestStatus::Pending)
            ->with(['documentType', 'student.user'])
            ->oldest()
            ->take(5)
            ->get();
    }

    /**
     * Count the requests this staff member is handling.
     */
    #[Computed]
    public function assignedToMe(): int
    {
        return DocumentRequest::query()
            ->where('processed_by_user_id', Auth::id())
            ->open()
            ->count();
    }

    /**
     * Count the appointments scheduled for today.
     */
    #[Computed]
    public function appointmentsToday(): int
    {
        return Appointment::query()
            ->onDate(CarbonImmutable::today())
            ->occupying()
            ->count();
    }

    /**
     * Count the documents released today.
     */
    #[Computed]
    public function releasedToday(): int
    {
        return DocumentRequest::query()
            ->whereDate('released_at', CarbonImmutable::today())
            ->count();
    }
}; ?>

<div class="flex flex-col gap-6" data-test="registrar-dashboard">
    <x-page-heading
        :heading="__('Registrar dashboard')"
        :subheading="__('Today\'s workload across the document request queue.')"
    />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card
            :label="__('Pending review')"
            :value="$this->counts[App\Enums\RequestStatus::Pending->value] ?? 0"
            icon="inbox-arrow-down"
            :hint="__('Awaiting a first look')"
        />

        <x-stat-card
            :label="__('Ready for release')"
            :value="$this->counts[App\Enums\RequestStatus::ReadyForRelease->value] ?? 0"
            icon="check-badge"
            :hint="__('Waiting to be claimed')"
        />

        <x-stat-card
            :label="__('Assigned to me')"
            :value="$this->assignedToMe"
            icon="user"
            :hint="__('Open requests you are handling')"
        />

        <x-stat-card
            :label="__('Appointments today')"
            :value="$this->appointmentsToday"
            icon="calendar-days"
            :hint="__(':count released today', ['count' => $this->releasedToday])"
        />
    </div>

    <flux:card class="flex flex-col gap-4">
        <flux:heading size="sm">{{ __('Longest waiting requests') }}</flux:heading>

        @if ($this->oldestPending->isEmpty())
            <x-empty-state
                icon="check-circle"
                :heading="__('Nothing pending')"
                :description="__('Every submitted request has been picked up.')"
            />
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Reference') }}</flux:table.column>
                    <flux:table.column>{{ __('Student') }}</flux:table.column>
                    <flux:table.column>{{ __('Document') }}</flux:table.column>
                    <flux:table.column>{{ __('Waiting') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->oldestPending as $request)
                        <flux:table.row :key="$request->id">
                            <flux:table.cell class="font-mono text-xs">{{ $request->reference_no }}</flux:table.cell>
                            <flux:table.cell>{{ $request->student->user->name }}</flux:table.cell>
                            <flux:table.cell>{{ $request->display_name }}</flux:table.cell>
                            <flux:table.cell>{{ $request->created_at?->diffForHumans() }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</div>

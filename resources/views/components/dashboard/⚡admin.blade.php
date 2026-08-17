<?php

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
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
     * Get request volume per document type.
     *
     * @return Collection<int, object{name: string, total: int}>
     */
    #[Computed]
    public function volumeByType(): Collection
    {
        return DocumentType::query()
            ->withCount('documentRequests')
            ->orderByDesc('document_requests_count')
            ->take(6)
            ->get()
            ->map(fn (DocumentType $type): object => (object) [
                'name' => $type->name,
                'total' => $type->document_requests_count,
            ]);
    }

    /**
     * Count accounts grouped by role.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function usersByRole(): array
    {
        return User::query()
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role')
            ->all();
    }

    /**
     * Get this week's appointment slot utilisation as a percentage.
     */
    #[Computed]
    public function slotUtilisation(): int
    {
        $slots = TimeSlot::query()
            ->whereBetween('slot_date', [
                CarbonImmutable::today()->startOfWeek(),
                CarbonImmutable::today()->endOfWeek(),
            ])
            ->selectRaw('sum(capacity) as capacity, sum(booked_count) as booked')
            ->first();

        $capacity = (int) ($slots?->capacity ?? 0);

        if ($capacity === 0) {
            return 0;
        }

        return (int) round(((int) $slots->booked / $capacity) * 100);
    }

    /**
     * Get the average turnaround time in days for released documents.
     *
     * Computed in PHP rather than SQL so the query behaves identically on
     * MySQL and SQLite.
     */
    #[Computed]
    public function averageTurnaround(): ?float
    {
        $released = DocumentRequest::query()
            ->withStatus(RequestStatus::Released)
            ->whereNotNull('released_at')
            ->get(['created_at', 'released_at']);

        if ($released->isEmpty()) {
            return null;
        }

        return round(
            $released->avg(fn (DocumentRequest $request): float => $request->created_at->diffInDays($request->released_at)),
            1,
        );
    }
}; ?>

<div class="flex flex-col gap-6" data-test="admin-dashboard">
    <x-page-heading
        :heading="__('Administration overview')"
        :subheading="__('System-wide activity across requests, appointments, and accounts.')"
    />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card
            :label="__('Total requests')"
            :value="array_sum($this->counts)"
            icon="document-text"
            :hint="__('All time')"
        />

        <x-stat-card
            :label="__('Open requests')"
            :value="collect(App\Enums\RequestStatus::open())->sum(fn ($status) => $this->counts[$status->value] ?? 0)"
            icon="clock"
            :hint="__('Still in the queue')"
        />

        <x-stat-card
            :label="__('Avg. turnaround')"
            :value="$this->averageTurnaround === null ? '—' : __(':days days', ['days' => $this->averageTurnaround])"
            icon="chart-bar"
            :hint="__('Submission to release')"
        />

        <x-stat-card
            :label="__('Slot utilisation')"
            :value="$this->slotUtilisation . '%'"
            icon="calendar-days"
            :hint="__('Booked seats this week')"
        />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <flux:card class="flex flex-col gap-4">
            <flux:heading size="sm">{{ __('Requests by document type') }}</flux:heading>

            @if ($this->volumeByType->isEmpty())
                <x-empty-state
                    icon="document-duplicate"
                    :heading="__('No document types yet')"
                />
            @else
                @php
                    $maxVolume = max(1, $this->volumeByType->max('total'));
                @endphp

                <div class="flex flex-col gap-3">
                    @foreach ($this->volumeByType as $type)
                        <x-bar-meter
                            :key="$type->name"
                            :label="$type->name"
                            :value="$type->total"
                            :max="$maxVolume"
                        />
                    @endforeach
                </div>
            @endif
        </flux:card>

        <flux:card class="flex flex-col gap-4">
            <flux:heading size="sm">{{ __('Accounts by role') }}</flux:heading>

            <div class="flex flex-col gap-3">
                @foreach (App\Enums\UserRole::cases() as $role)
                    <x-bar-meter
                        :key="$role->value"
                        :label="$role->label()"
                        :value="$this->usersByRole[$role->value] ?? 0"
                        :max="max(1, array_sum($this->usersByRole))"
                    />
                @endforeach
            </div>
        </flux:card>
    </div>
</div>

<?php

use App\Actions\Reports\BuildAppointmentReport;
use App\Actions\Reports\BuildRegistrarSummary;
use App\Actions\Reports\BuildRequestVolumeReport;
use App\Actions\Reports\BuildTurnaroundReport;
use App\Actions\Reports\ExportReportToCsv;
use App\Enums\RequestStatus;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Title('Reports')] class extends Component {
    #[Url]
    public string $tab = 'volume';

    #[Url]
    public string $from = '';

    #[Url]
    public string $until = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        if ($this->from === '') {
            $this->from = CarbonImmutable::today()->startOfMonth()->subMonths(2)->toDateString();
        }

        if ($this->until === '') {
            $this->until = CarbonImmutable::today()->toDateString();
        }
    }

    /**
     * Get the start of the reporting window.
     */
    public function fromDate(): ?CarbonImmutable
    {
        return $this->from === '' ? null : CarbonImmutable::parse($this->from);
    }

    /**
     * Get the end of the reporting window.
     */
    public function untilDate(): ?CarbonImmutable
    {
        return $this->until === '' ? null : CarbonImmutable::parse($this->until);
    }

    /**
     * Get the request volume figures.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function volume(): array
    {
        return app(BuildRequestVolumeReport::class)($this->fromDate(), $this->untilDate());
    }

    /**
     * Get the turnaround figures.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function turnaround(): array
    {
        return app(BuildTurnaroundReport::class)($this->fromDate(), $this->untilDate());
    }

    /**
     * Get the appointment usage figures.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function appointments(): array
    {
        return app(BuildAppointmentReport::class)($this->fromDate(), $this->untilDate());
    }

    /**
     * Get the headline operational figures.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function summary(): array
    {
        return app(BuildRegistrarSummary::class)();
    }

    /**
     * Download the current report as a spreadsheet.
     */
    public function export(ExportReportToCsv $exportReportToCsv): StreamedResponse
    {
        $suffix = $this->from.'_to_'.$this->until;

        return match ($this->tab) {
            'turnaround' => $exportReportToCsv(
                "turnaround-report_{$suffix}.csv",
                ['Document type', 'Released', 'Average days', 'Median days', 'Fastest', 'Slowest', 'Target days', 'Met', 'Missed'],
                array_map(fn (array $row): array => [
                    $row['type'], $row['released'], $row['average'], $row['median'],
                    $row['fastest'], $row['slowest'], $row['sla'], $row['met'], $row['missed'],
                ], $this->turnaround['rows']),
            ),
            'appointments' => $exportReportToCsv(
                "appointment-report_{$suffix}.csv",
                ['Date', 'Slots', 'Capacity', 'Booked', 'Completed', 'No shows', 'Cancelled', 'Utilisation %'],
                array_map(fn (array $row): array => [
                    $row['date'], $row['slots'], $row['capacity'], $row['booked'],
                    $row['completed'], $row['noShows'], $row['cancelled'], $row['utilisation'],
                ], $this->appointments['rows']),
            ),
            default => $exportReportToCsv(
                "request-volume_{$suffix}.csv",
                ['Document type', ...array_map(fn (RequestStatus $s): string => $s->label(), RequestStatus::cases()), 'Total'],
                array_map(fn (array $row): array => [
                    $row['type'],
                    ...array_values($row['statuses']),
                    $row['total'],
                ], $this->volume['rows']),
            ),
        };
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <x-page-heading
        :heading="__('Reports')"
        :subheading="__('Operational figures for the registrar\'s office.')"
    >
        <flux:button wire:click="export" variant="ghost" size="sm" icon="arrow-down-tray" data-test="export-button">
            {{ __('Export CSV') }}
        </flux:button>

        <flux:button onclick="window.print()" variant="ghost" size="sm" icon="printer" class="print:hidden">
            {{ __('Print') }}
        </flux:button>
    </x-page-heading>

    <div class="flex flex-wrap items-end gap-3 print:hidden">
        <flux:input wire:model.live="from" :label="__('From')" type="date" class="max-w-40" data-test="from-filter" />
        <flux:input wire:model.live="until" :label="__('Until')" type="date" class="max-w-40" data-test="until-filter" />
    </div>

    <flux:navbar class="print:hidden">
        <flux:navbar.item wire:click="$set('tab', 'volume')" :current="$tab === 'volume'" data-test="tab-volume">
            {{ __('Request volume') }}
        </flux:navbar.item>
        <flux:navbar.item wire:click="$set('tab', 'turnaround')" :current="$tab === 'turnaround'" data-test="tab-turnaround">
            {{ __('Turnaround time') }}
        </flux:navbar.item>
        <flux:navbar.item wire:click="$set('tab', 'appointments')" :current="$tab === 'appointments'" data-test="tab-appointments">
            {{ __('Appointments') }}
        </flux:navbar.item>
        <flux:navbar.item wire:click="$set('tab', 'summary')" :current="$tab === 'summary'" data-test="tab-summary">
            {{ __('Summary') }}
        </flux:navbar.item>
    </flux:navbar>

    @if ($tab === 'volume')
        <flux:card class="flex flex-col gap-4" data-test="volume-report">
            <flux:heading size="sm">{{ __('Requests by document type and status') }}</flux:heading>

            @if ($this->volume['grandTotal'] === 0)
                <x-empty-state icon="chart-bar" :heading="__('No requests in this period')" />
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Document type') }}</flux:table.column>
                        @foreach (App\Enums\RequestStatus::cases() as $status)
                            <flux:table.column>{{ $status->label() }}</flux:table.column>
                        @endforeach
                        <flux:table.column>{{ __('Total') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->volume['rows'] as $row)
                            <flux:table.row wire:key="volume-{{ $loop->index }}">
                                <flux:table.cell>{{ $row['type'] }}</flux:table.cell>
                                @foreach (App\Enums\RequestStatus::cases() as $status)
                                    <flux:table.cell class="tabular-nums">{{ $row['statuses'][$status->value] }}</flux:table.cell>
                                @endforeach
                                <flux:table.cell class="font-medium tabular-nums">{{ $row['total'] }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach

                        <flux:table.row>
                            <flux:table.cell class="font-medium">{{ __('All documents') }}</flux:table.cell>
                            @foreach (App\Enums\RequestStatus::cases() as $status)
                                <flux:table.cell class="font-medium tabular-nums">{{ $this->volume['totals'][$status->value] }}</flux:table.cell>
                            @endforeach
                            <flux:table.cell class="font-medium tabular-nums">{{ $this->volume['grandTotal'] }}</flux:table.cell>
                        </flux:table.row>
                    </flux:table.rows>
                </flux:table>

                <flux:separator />

                <div class="flex flex-col gap-3">
                    @php $maxVolume = max(1, collect($this->volume['rows'])->max('total')); @endphp

                    @foreach ($this->volume['rows'] as $row)
                        <x-bar-meter
                            wire:key="volume-bar-{{ $loop->index }}"
                            :label="$row['type']"
                            :value="$row['total']"
                            :max="$maxVolume"
                        />
                    @endforeach
                </div>
            @endif
        </flux:card>
    @elseif ($tab === 'turnaround')
        <flux:card class="flex flex-col gap-4" data-test="turnaround-report">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:heading size="sm">{{ __('Days from submission to release') }}</flux:heading>

                @if ($this->turnaround['overallAverage'] !== null)
                    <div class="flex items-center gap-2">
                        <flux:badge color="blue" size="sm">
                            {{ __('Average :days days', ['days' => $this->turnaround['overallAverage']]) }}
                        </flux:badge>
                        <flux:badge :color="$this->turnaround['metRate'] >= 80 ? 'green' : 'amber'" size="sm">
                            {{ __(':rate% within target', ['rate' => $this->turnaround['metRate']]) }}
                        </flux:badge>
                    </div>
                @endif
            </div>

            @if ($this->turnaround['rows'] === [])
                <x-empty-state
                    icon="clock"
                    :heading="__('Nothing released in this period')"
                    :description="__('Turnaround is measured once a document has been handed over.')"
                />
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Document type') }}</flux:table.column>
                        <flux:table.column>{{ __('Released') }}</flux:table.column>
                        <flux:table.column>{{ __('Average (calendar days)') }}</flux:table.column>
                        <flux:table.column>{{ __('Median') }}</flux:table.column>
                        <flux:table.column>{{ __('Fastest') }}</flux:table.column>
                        <flux:table.column>{{ __('Slowest') }}</flux:table.column>
                        <flux:table.column>{{ __('Target (working days)') }}</flux:table.column>
                        <flux:table.column>{{ __('Within target') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->turnaround['rows'] as $row)
                            <flux:table.row wire:key="turnaround-{{ $loop->index }}">
                                <flux:table.cell>{{ $row['type'] }}</flux:table.cell>
                                <flux:table.cell class="tabular-nums">{{ $row['released'] }}</flux:table.cell>
                                <flux:table.cell class="tabular-nums">{{ $row['average'] }}</flux:table.cell>
                                <flux:table.cell class="tabular-nums">{{ $row['median'] }}</flux:table.cell>
                                <flux:table.cell class="tabular-nums">{{ $row['fastest'] }}</flux:table.cell>
                                <flux:table.cell class="tabular-nums">{{ $row['slowest'] }}</flux:table.cell>
                                <flux:table.cell class="tabular-nums">{{ $row['sla'] }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="$row['missed'] === 0 ? 'green' : 'amber'" size="sm">
                                        {{ $row['met'] }} / {{ $row['released'] }}
                                    </flux:badge>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    @elseif ($tab === 'appointments')
        <flux:card class="flex flex-col gap-4" data-test="appointment-report">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:heading size="sm">{{ __('Slot usage and attendance') }}</flux:heading>

                <flux:badge color="blue" size="sm">
                    {{ __(':rate% of seats used', ['rate' => $this->appointments['utilisation']]) }}
                </flux:badge>
            </div>

            @if ($this->appointments['rows'] === [])
                <x-empty-state icon="calendar" :heading="__('No slots in this period')" />
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Date') }}</flux:table.column>
                        <flux:table.column>{{ __('Slots') }}</flux:table.column>
                        <flux:table.column>{{ __('Capacity') }}</flux:table.column>
                        <flux:table.column>{{ __('Booked') }}</flux:table.column>
                        <flux:table.column>{{ __('Claimed') }}</flux:table.column>
                        <flux:table.column>{{ __('No shows') }}</flux:table.column>
                        <flux:table.column>{{ __('Utilisation') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->appointments['rows'] as $row)
                            <flux:table.row wire:key="appt-{{ $row['date'] }}">
                                <flux:table.cell>{{ Carbon\CarbonImmutable::parse($row['date'])->format('M j, Y') }}</flux:table.cell>
                                <flux:table.cell class="tabular-nums">{{ $row['slots'] }}</flux:table.cell>
                                <flux:table.cell class="tabular-nums">{{ $row['capacity'] }}</flux:table.cell>
                                <flux:table.cell class="tabular-nums">{{ $row['booked'] }}</flux:table.cell>
                                <flux:table.cell class="tabular-nums">{{ $row['completed'] }}</flux:table.cell>
                                <flux:table.cell class="tabular-nums">{{ $row['noShows'] }}</flux:table.cell>
                                <flux:table.cell class="w-40">
                                    <x-bar-meter
                                        :label="$row['utilisation'] . '%'"
                                        :value="$row['booked']"
                                        :max="max(1, $row['capacity'])"
                                    />
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-test="summary-report">
            <x-stat-card :label="__('Total requests')" :value="$this->summary['total']" icon="document-text" />
            <x-stat-card :label="__('Still open')" :value="$this->summary['open']" icon="clock" />
            <x-stat-card
                :label="__('Overdue')"
                :value="$this->summary['overdue']"
                icon="exclamation-triangle"
                :hint="__('Open for more than 7 days')"
            />
            <x-stat-card
                :label="__('This month')"
                :value="$this->summary['thisMonth']"
                icon="chart-bar"
                :hint="$this->summary['monthDelta'] === null
                    ? __('No comparison available')
                    : __(':delta% vs last month', ['delta' => $this->summary['monthDelta']])"
            />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <flux:card class="flex flex-col gap-4">
                <flux:heading size="sm">{{ __('Requests by status') }}</flux:heading>

                <div class="flex flex-col gap-3">
                    @foreach (App\Enums\RequestStatus::cases() as $status)
                        <x-bar-meter
                            wire:key="summary-status-{{ $status->value }}"
                            :label="$status->label()"
                            :value="$this->summary['byStatus'][$status->value]"
                            :max="max(1, $this->summary['total'])"
                        />
                    @endforeach
                </div>
            </flux:card>

            <flux:card class="flex flex-col gap-4">
                <flux:heading size="sm">{{ __('Most requested documents') }}</flux:heading>

                @if ($this->summary['topDocuments'] === [])
                    <x-empty-state icon="document-duplicate" :heading="__('No requests yet')" />
                @else
                    <div class="flex flex-col gap-3">
                        @php $topMax = max(1, collect($this->summary['topDocuments'])->max('total')); @endphp

                        @foreach ($this->summary['topDocuments'] as $document)
                            <x-bar-meter
                                wire:key="summary-doc-{{ $loop->index }}"
                                :label="$document['name']"
                                :value="$document['total']"
                                :max="$topMax"
                            />
                        @endforeach
                    </div>
                @endif
            </flux:card>
        </div>
    @endif
</div>

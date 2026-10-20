<?php

use App\Actions\Reports\BuildRegistrarSummary;
use App\Actions\Reports\ListClaimedDocuments;
use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2026-10-20 10:00:00');
});

test('the summary counts only requests submitted in the window', function () {
    DocumentRequest::factory()->count(2)->pending()->create(['created_at' => '2026-10-20 09:00:00']);
    DocumentRequest::factory()->released()->create(['created_at' => '2026-10-20 08:00:00']);
    DocumentRequest::factory()->count(4)->pending()->create(['created_at' => '2026-10-19 09:00:00']);

    $today = CarbonImmutable::parse('2026-10-20');
    $summary = app(BuildRegistrarSummary::class)($today, $today);

    expect($summary['total'])->toBe(3)
        ->and($summary['open'])->toBe(2)
        ->and($summary['byStatus'][RequestStatus::Released->value])->toBe(1);
});

test('a single day is compared with the day before', function () {
    DocumentRequest::factory()->count(6)->create(['created_at' => '2026-10-20 09:00:00']);
    DocumentRequest::factory()->count(4)->create(['created_at' => '2026-10-19 09:00:00']);

    $today = CarbonImmutable::parse('2026-10-20');
    $summary = app(BuildRegistrarSummary::class)($today, $today);

    expect($summary['previousFrom'])->toBe('2026-10-19')
        ->and($summary['previousUntil'])->toBe('2026-10-19')
        ->and($summary['previousTotal'])->toBe(4)
        ->and($summary['delta'])->toBe(50);
});

test('a week is compared with the week before', function () {
    $summary = app(BuildRegistrarSummary::class)(
        CarbonImmutable::parse('2026-10-14'),
        CarbonImmutable::parse('2026-10-20'),
    );

    expect($summary['previousFrom'])->toBe('2026-10-07')
        ->and($summary['previousUntil'])->toBe('2026-10-13');
});

test('without a full window there is nothing to compare', function () {
    $summary = app(BuildRegistrarSummary::class)();

    expect($summary['previousTotal'])->toBeNull()
        ->and($summary['delta'])->toBeNull();
});

test('the most requested documents are ranked within the window only', function () {
    $busyToday = DocumentType::factory()->create(['name' => 'Form 137']);
    $busyLastMonth = DocumentType::factory()->create(['name' => 'Diploma']);

    DocumentRequest::factory()->for($busyToday)->count(2)->create(['created_at' => '2026-10-20 09:00:00']);
    DocumentRequest::factory()->for($busyLastMonth)->count(5)->create(['created_at' => '2026-09-10 09:00:00']);

    $today = CarbonImmutable::parse('2026-10-20');
    $summary = app(BuildRegistrarSummary::class)($today, $today);

    expect($summary['topDocuments'])->toBe([['name' => 'Form 137', 'total' => 2]]);
});

test('the yesterday preset narrows the summary to the previous day', function () {
    DocumentRequest::factory()->count(3)->create(['created_at' => '2026-10-19 09:00:00']);
    DocumentRequest::factory()->create(['created_at' => '2026-10-20 09:00:00']);

    $this->actingAs(registrarStaff());

    $component = Livewire::test('pages::registrar.reports')
        ->set('tab', 'summary')
        ->call('usePreset', 'yesterday')
        ->assertSet('from', '2026-10-19')
        ->assertSet('until', '2026-10-19')
        ->assertSee('Requests submitted: Oct 19, 2026')
        ->assertSee('Oct 18, 2026');

    expect($component->instance()->summary['total'])->toBe(3);
});

test('the presets cover the common ranges', function (string $preset, string $from, string $until) {
    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.reports')
        ->call('usePreset', $preset)
        ->assertSet('from', $from)
        ->assertSet('until', $until);
})->with([
    'today' => ['today', '2026-10-20', '2026-10-20'],
    'last 7 days' => ['last7', '2026-10-14', '2026-10-20'],
    'this month' => ['this-month', '2026-10-01', '2026-10-20'],
    'last month' => ['last-month', '2026-09-01', '2026-09-30'],
]);

test('an unknown preset leaves the window alone', function () {
    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.reports')
        ->set('from', '2026-10-01')
        ->call('usePreset', 'forever')
        ->assertSet('from', '2026-10-01');
});

test('the claim log lists who claimed what on the chosen day', function () {
    $staff = registrarStaff();
    $type = DocumentType::factory()->create(['name' => 'Form 137']);

    $claimed = DocumentRequest::factory()->for($type)->released()->create([
        'created_at' => '2026-10-01 09:00:00',
        'released_at' => '2026-10-19 14:30:00',
        'processed_by_user_id' => $staff->id,
    ]);
    DocumentRequest::factory()->released()->create(['released_at' => '2026-10-20 09:00:00']);
    DocumentRequest::factory()->readyForRelease()->create(['created_at' => '2026-10-19 09:00:00']);

    $yesterday = CarbonImmutable::parse('2026-10-19');
    $claims = app(ListClaimedDocuments::class)($yesterday, $yesterday);

    expect($claims)->toHaveCount(1)
        ->and($claims[0])->toMatchArray([
            'released_at' => '2026-10-19 14:30',
            'student' => $claimed->student->user->name,
            'student_number' => $claimed->student->student_number,
            'document' => 'Form 137',
            'reference' => $claimed->reference_no,
            'released_by' => $staff->name,
        ]);
});

test('a claim is dated by its release, not its submission', function () {
    DocumentRequest::factory()->released()->create([
        'created_at' => '2026-10-19 09:00:00',
        'released_at' => '2026-10-20 09:00:00',
    ]);

    $yesterday = CarbonImmutable::parse('2026-10-19');

    expect(app(ListClaimedDocuments::class)($yesterday, $yesterday))->toBe([]);
});

test('the summary tab shows the names of students who claimed', function () {
    $claimed = DocumentRequest::factory()->released()->create(['released_at' => '2026-10-19 14:30:00']);

    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.reports')
        ->set('tab', 'summary')
        ->call('usePreset', 'yesterday')
        ->assertSee('Claimed documents')
        ->assertSee($claimed->student->user->name)
        ->assertSee($claimed->reference_no)
        ->assertSee('Oct 19, 2026 2:30 PM')
        ->call('usePreset', 'today')
        ->assertDontSee($claimed->student->user->name)
        ->assertSee('Nothing was claimed in this period');
});

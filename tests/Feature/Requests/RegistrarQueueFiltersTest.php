<?php

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Models\Appointment;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\TimeSlot;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

test('the queue can be filtered by payment state', function () {
    DocumentRequest::factory()->unpaid()->create();
    DocumentRequest::factory()->paid()->create();

    $this->actingAs(registrarStaff());

    $component = Livewire::test('pages::registrar.requests')
        ->set('paymentStatus', PaymentStatus::Unpaid->value);

    expect($component->instance()->requests->total())->toBe(1);
});

test('the queue can be narrowed to requests I am handling', function () {
    $staff = registrarStaff();

    DocumentRequest::factory()->create(['processed_by_user_id' => $staff->id]);
    DocumentRequest::factory()->create(['processed_by_user_id' => registrarStaff()->id]);
    DocumentRequest::factory()->create();

    $this->actingAs($staff);

    expect(Livewire::test('pages::registrar.requests')->set('assignment', 'mine')->instance()->requests->total())
        ->toBe(1);
});

test('the queue can show what nobody has picked up', function () {
    DocumentRequest::factory()->create(['processed_by_user_id' => registrarStaff()->id]);
    DocumentRequest::factory()->create();
    DocumentRequest::factory()->create();

    $this->actingAs(registrarStaff());

    expect(Livewire::test('pages::registrar.requests')->set('assignment', 'unassigned')->instance()->requests->total())
        ->toBe(2);
});

test('the queue can show what other staff are handling', function () {
    $staff = registrarStaff();

    DocumentRequest::factory()->create(['processed_by_user_id' => $staff->id]);
    DocumentRequest::factory()->create(['processed_by_user_id' => registrarStaff()->id]);

    $this->actingAs($staff);

    expect(Livewire::test('pages::registrar.requests')->set('assignment', 'others')->instance()->requests->total())
        ->toBe(1);
});

test('the queue can be split by whether an appointment is held', function () {
    $booked = DocumentRequest::factory()->readyForRelease()->create();
    $slot = TimeSlot::factory()->onDate(CarbonImmutable::today()->addWeekday())->startingAt(9)->create();
    Appointment::factory()->create(['document_request_id' => $booked->id, 'time_slot_id' => $slot->id]);

    DocumentRequest::factory()->readyForRelease()->create();

    $this->actingAs(registrarStaff());

    expect(Livewire::test('pages::registrar.requests')->set('appointment', 'booked')->instance()->requests->total())
        ->toBe(1)
        ->and(Livewire::test('pages::registrar.requests')->set('appointment', 'none')->instance()->requests->total())
        ->toBe(1);
});

test('a cancelled appointment does not count as booked', function () {
    $request = DocumentRequest::factory()->readyForRelease()->create();
    $slot = TimeSlot::factory()->onDate(CarbonImmutable::today()->addWeekday())->startingAt(9)->create();

    Appointment::factory()->create([
        'document_request_id' => $request->id,
        'time_slot_id' => $slot->id,
        'status' => AppointmentStatus::Cancelled,
    ]);

    $this->actingAs(registrarStaff());

    expect(Livewire::test('pages::registrar.requests')->set('appointment', 'booked')->instance()->requests->total())
        ->toBe(0)
        ->and(Livewire::test('pages::registrar.requests')->set('appointment', 'none')->instance()->requests->total())
        ->toBe(1);
});

test('the queue can be sorted by student name in either direction', function () {
    $first = DocumentRequest::factory()->create();
    $first->student->user->update(['name' => 'Aaron Abad']);

    $second = DocumentRequest::factory()->create();
    $second->student->user->update(['name' => 'Zeny Zamora']);

    $this->actingAs(registrarStaff());

    $ascending = Livewire::test('pages::registrar.requests')->call('sortBy', 'student');

    expect($ascending->instance()->requests->first()->id)->toBe($first->id);

    $descending = $ascending->call('sortBy', 'student');

    expect($descending->get('direction'))->toBe('desc')
        ->and($descending->instance()->requests->first()->id)->toBe($second->id);
});

test('the queue can be sorted by document name', function () {
    $alpha = DocumentRequest::factory()->for(DocumentType::factory()->create(['name' => 'Alpha Document']))->create();
    DocumentRequest::factory()->for(DocumentType::factory()->create(['name' => 'Zulu Document']))->create();

    $this->actingAs(registrarStaff());

    $component = Livewire::test('pages::registrar.requests')->call('sortBy', 'document');

    expect($component->instance()->requests->first()->id)->toBe($alpha->id);
});

test('the queue can be sorted by submission date newest first', function () {
    $older = DocumentRequest::factory()->create(['created_at' => now()->subWeek()]);
    $newer = DocumentRequest::factory()->create(['created_at' => now()]);

    $this->actingAs(registrarStaff());

    // 'submitted' is already the default sort, so the first click flips it
    // rather than selecting it.
    $component = Livewire::test('pages::registrar.requests')
        ->set('status', RequestStatus::Pending->value);

    expect($component->instance()->requests->first()->id)->toBe($older->id);

    $component->call('sortBy', 'submitted');

    expect($component->get('direction'))->toBe('desc')
        ->and($component->instance()->requests->first()->id)->toBe($newer->id);
});

test('an explicit sort drops the pending-first rule', function () {
    DocumentRequest::factory()->released()->create();
    DocumentRequest::factory()->create(['status' => RequestStatus::Pending]);

    $this->actingAs(registrarStaff());

    $component = Livewire::test('pages::registrar.requests')->call('sortBy', 'status');

    // Ordered by the status column itself rather than pending-first.
    expect($component->instance()->requests->first()->status)->toBe(RequestStatus::Pending);
});

test('sorting by a column the queue does not offer is ignored', function () {
    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.requests')
        ->call('sortBy', 'reference_no; drop table users')
        ->assertSet('sort', 'submitted');
});

test('a forged sort or direction falls back to the default', function () {
    DocumentRequest::factory()->count(2)->create();

    $this->actingAs(registrarStaff());

    $component = Livewire::withQueryParams(['sort' => 'nonsense', 'direction' => 'sideways'])
        ->test('pages::registrar.requests');

    expect($component->instance()->sortColumn)->toBe('submitted')
        ->and($component->instance()->sortDirection)->toBe('asc')
        ->and($component->instance()->requests->total())->toBe(2);
});

test('the page size can be changed and resets to the first page', function () {
    DocumentRequest::factory()->count(12)->create();

    $this->actingAs(registrarStaff());

    $component = Livewire::test('pages::registrar.requests')
        ->call('gotoPage', 2)
        ->set('perPage', 10);

    expect($component->instance()->requests->perPage())->toBe(10)
        ->and($component->instance()->requests->currentPage())->toBe(1);
});

test('a forged page size falls back to the default', function () {
    DocumentRequest::factory()->count(3)->create();

    $this->actingAs(registrarStaff());

    $component = Livewire::withQueryParams(['perPage' => 9999])->test('pages::registrar.requests');

    expect($component->instance()->requests->perPage())->toBe(15);
});

test('the queue reports how many requests match', function () {
    DocumentRequest::factory()->count(3)->create();

    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.requests')
        ->assertSeeHtml('data-test="result-count"')
        ->assertSee('of 3 requests');
});

test('sort state survives in the url', function () {
    DocumentRequest::factory()->count(2)->create();

    $this->actingAs(registrarStaff());

    $component = Livewire::withQueryParams(['sort' => 'status', 'direction' => 'desc'])
        ->test('pages::registrar.requests');

    expect($component->instance()->sortColumn)->toBe('status')
        ->and($component->instance()->sortDirection)->toBe('desc');
});

test('clearing filters keeps the chosen sort and page size', function () {
    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.requests')
        ->set('search', 'anything')
        ->set('paymentStatus', PaymentStatus::Unpaid->value)
        ->call('sortBy', 'status')
        ->set('perPage', 50)
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('paymentStatus', '')
        ->assertSet('sort', 'status')
        ->assertSet('perPage', 50);
});

test('the new filters count towards the clear button', function (string $property, string $value) {
    $this->actingAs(registrarStaff());

    $component = Livewire::test('pages::registrar.requests')->set($property, $value);

    expect($component->instance()->isFiltered)->toBeTrue();
})->with([
    'payment' => ['paymentStatus', 'unpaid'],
    'assignment' => ['assignment', 'mine'],
    'appointment' => ['appointment', 'booked'],
]);

<?php

use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use Livewire\Livewire;

test('staff can open the request queue', function (string $helper) {
    $this->actingAs($helper());

    $this->get(route('registrar.requests.index'))->assertOk();
})->with(['registrarStaff', 'administrator']);

test('students cannot reach the request queue', function () {
    $this->actingAs(student());

    $this->get(route('registrar.requests.index'))->assertForbidden();
});

test('guests are redirected away from the queue', function () {
    $this->get(route('registrar.requests.index'))->assertRedirect(route('login'));
});

test('the queue shows requests from every student', function () {
    $a = DocumentRequest::factory()->for(student()->student)->create();
    $b = DocumentRequest::factory()->for(student()->student)->create();

    $this->actingAs(registrarStaff());

    $this->get(route('registrar.requests.index'))
        ->assertOk()
        ->assertSee($a->reference_no)
        ->assertSee($b->reference_no);
});

test('the queue can be filtered by status', function () {
    $pending = DocumentRequest::factory()->pending()->create();
    $released = DocumentRequest::factory()->released()->create();

    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.requests')
        ->set('status', RequestStatus::Pending->value)
        ->assertSee($pending->reference_no)
        ->assertDontSee($released->reference_no);
});

test('the queue can be filtered by document type', function () {
    $tor = DocumentType::factory()->create(['name' => 'Transcript of Records']);
    $form = DocumentType::factory()->create(['name' => 'Form 137']);

    $wanted = DocumentRequest::factory()->for($tor)->create();
    $other = DocumentRequest::factory()->for($form)->create();

    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.requests')
        ->set('documentType', (string) $tor->id)
        ->assertSee($wanted->reference_no)
        ->assertDontSee($other->reference_no);
});

test('the queue can be searched by student name', function () {
    $user = student();
    $user->update(['name' => 'Juana Distinctive']);

    $wanted = DocumentRequest::factory()->for($user->student)->create();
    $other = DocumentRequest::factory()->create();

    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.requests')
        ->set('search', 'Distinctive')
        ->assertSee($wanted->reference_no)
        ->assertDontSee($other->reference_no);
});

test('the queue can be searched by reference number', function () {
    $wanted = DocumentRequest::factory()->create();
    $other = DocumentRequest::factory()->create();

    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.requests')
        ->set('search', $wanted->reference_no)
        ->assertSee($wanted->reference_no)
        ->assertDontSee($other->reference_no);
});

test('the queue can be filtered to a date range', function () {
    $this->travelTo('2026-05-10 09:00:00');
    $inRange = DocumentRequest::factory()->create();

    $this->travelTo('2026-06-20 09:00:00');
    $outOfRange = DocumentRequest::factory()->create();

    $this->travelBack();
    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.requests')
        ->set('from', '2026-05-01')
        ->set('until', '2026-05-31')
        ->assertSee($inRange->reference_no)
        ->assertDontSee($outOfRange->reference_no);
});

test('clearing the filters shows everything again', function () {
    $pending = DocumentRequest::factory()->pending()->create();
    $released = DocumentRequest::factory()->released()->create();

    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.requests')
        ->set('status', RequestStatus::Pending->value)
        ->assertDontSee($released->reference_no)
        ->call('clearFilters')
        ->assertSee($pending->reference_no)
        ->assertSee($released->reference_no);
});

test('pending requests are surfaced ahead of finished ones', function () {
    $this->travelTo('2026-01-01 09:00:00');
    $oldReleased = DocumentRequest::factory()->released()->create();

    $this->travelTo('2026-06-01 09:00:00');
    $newPending = DocumentRequest::factory()->pending()->create();

    $this->travelBack();
    $this->actingAs(registrarStaff());

    // The pending request appears before the older released one in the markup.
    $html = Livewire::test('pages::registrar.requests')->html();

    expect(strpos($html, $newPending->reference_no))
        ->toBeLessThan(strpos($html, $oldReleased->reference_no));
});

test('staff can open a request detail page', function () {
    $request = DocumentRequest::factory()->create();

    $this->actingAs(registrarStaff());

    $this->get(route('registrar.requests.show', $request))
        ->assertOk()
        ->assertSee($request->reference_no)
        ->assertSee($request->student->user->name);
});

test('students cannot open the registrar detail page', function () {
    $user = student();
    $request = DocumentRequest::factory()->for($user->student)->create();

    $this->actingAs($user);

    $this->get(route('registrar.requests.show', $request))->assertForbidden();
});

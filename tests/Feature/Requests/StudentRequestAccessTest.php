<?php

use App\Enums\RequestStatus;
use App\Models\DocumentRequest;

test('a student sees only their own requests in the list', function () {
    $user = student();
    $other = student();

    $mine = DocumentRequest::factory()->for($user->student)->create();
    $theirs = DocumentRequest::factory()->for($other->student)->create();

    $this->actingAs($user);

    $this->get(route('student.requests.index'))
        ->assertOk()
        ->assertSee($mine->reference_no)
        ->assertDontSee($theirs->reference_no);
});

test('a student can open their own request', function () {
    $user = student();
    $request = DocumentRequest::factory()->for($user->student)->create();

    $this->actingAs($user);

    $this->get(route('student.requests.show', $request))
        ->assertOk()
        ->assertSee($request->reference_no);
});

test('a student cannot open another student\'s request', function () {
    $user = student();
    $request = DocumentRequest::factory()->for(student()->student)->create();

    $this->actingAs($user);

    $this->get(route('student.requests.show', $request))->assertForbidden();
});

test('guests are redirected away from the request screens', function () {
    $request = DocumentRequest::factory()->create();

    $this->get(route('student.requests.index'))->assertRedirect(route('login'));
    $this->get(route('student.requests.show', $request))->assertRedirect(route('login'));
});

test('staff cannot reach the student request screens', function (string $helper) {
    $this->actingAs($helper());

    $this->get(route('student.requests.index'))->assertForbidden();
})->with(['registrarStaff', 'administrator']);

test('the list can be filtered by status', function () {
    $user = student();

    $pending = DocumentRequest::factory()->for($user->student)->pending()->create();
    $released = DocumentRequest::factory()->for($user->student)->released()->create();

    $this->actingAs($user);

    Livewire\Livewire::test('pages::student.requests')
        ->set('status', RequestStatus::Pending->value)
        ->assertSee($pending->reference_no)
        ->assertDontSee($released->reference_no);
});

test('the list can be searched by reference number', function () {
    $user = student();

    $wanted = DocumentRequest::factory()->for($user->student)->create();
    $other = DocumentRequest::factory()->for($user->student)->create();

    $this->actingAs($user);

    Livewire\Livewire::test('pages::student.requests')
        ->set('search', $wanted->reference_no)
        ->assertSee($wanted->reference_no)
        ->assertDontSee($other->reference_no);
});

test('an empty search shows every request again', function () {
    $user = student();
    DocumentRequest::factory()->count(3)->for($user->student)->create();

    $this->actingAs($user);

    Livewire\Livewire::test('pages::student.requests')
        ->set('search', 'nothing-matches-this')
        ->set('search', '')
        ->assertSee(DocumentRequest::query()->first()->reference_no);
});

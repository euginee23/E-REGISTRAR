<?php

use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use Livewire\Livewire;

test('the modal offers only legal transitions', function () {
    $request = DocumentRequest::factory()->pending()->create();

    $this->actingAs(registrarStaff());

    $component = Livewire::test('registrar.update-request-status', ['documentRequest' => $request]);

    $available = collect($component->instance()->availableTransitions)->pluck('value')->all();

    expect($available)->toEqualCanonicalizing([
        RequestStatus::Processing->value,
        RequestStatus::Rejected->value,
        RequestStatus::Cancelled->value,
    ]);
});

test('a finished request offers no transitions at all', function (RequestStatus $status) {
    $request = DocumentRequest::factory()->create(['status' => $status]);

    $this->actingAs(registrarStaff());

    Livewire::test('registrar.update-request-status', ['documentRequest' => $request])
        ->assertSee('no further changes are possible')
        ->assertDontSee('data-test="update-status-trigger"', escape: false);
})->with([RequestStatus::Released, RequestStatus::Rejected, RequestStatus::Cancelled]);

test('staff can approve a pending request through the modal', function () {
    $staff = registrarStaff();
    $request = DocumentRequest::factory()->pending()->create();

    $this->actingAs($staff);

    Livewire::test('registrar.update-request-status', ['documentRequest' => $request])
        ->set('to', RequestStatus::Processing->value)
        ->call('updateStatus')
        ->assertHasNoErrors()
        ->assertDispatched('request-updated');

    expect($request->fresh()->status)->toBe(RequestStatus::Processing)
        ->and($request->fresh()->processed_by_user_id)->toBe($staff->id);
});

test('a rejection requires a reason', function () {
    $request = DocumentRequest::factory()->pending()->create();

    $this->actingAs(registrarStaff());

    Livewire::test('registrar.update-request-status', ['documentRequest' => $request])
        ->set('to', RequestStatus::Rejected->value)
        ->set('remarks', '')
        ->call('updateStatus')
        ->assertHasErrors('remarks');

    expect($request->fresh()->status)->toBe(RequestStatus::Pending);
});

test('a rejection reason is stored and shown to the student', function () {
    $request = DocumentRequest::factory()->pending()->create();

    $this->actingAs(registrarStaff());

    Livewire::test('registrar.update-request-status', ['documentRequest' => $request])
        ->set('to', RequestStatus::Rejected->value)
        ->set('remarks', 'Incomplete supporting requirements.')
        ->call('updateStatus')
        ->assertHasNoErrors();

    expect($request->fresh()->remarks)->toBe('Incomplete supporting requirements.');
});

test('a target status is required', function () {
    $request = DocumentRequest::factory()->pending()->create();

    $this->actingAs(registrarStaff());

    Livewire::test('registrar.update-request-status', ['documentRequest' => $request])
        ->set('to', '')
        ->call('updateStatus')
        ->assertHasErrors('to');
});

test('an illegal transition is refused even if the payload is forged', function () {
    $request = DocumentRequest::factory()->pending()->create();

    $this->actingAs(registrarStaff());

    Livewire::test('registrar.update-request-status', ['documentRequest' => $request])
        ->set('to', RequestStatus::Released->value)
        ->call('updateStatus')
        ->assertForbidden();

    expect($request->fresh()->status)->toBe(RequestStatus::Pending);
});

test('students cannot use the staff status modal', function () {
    $user = student();
    $request = DocumentRequest::factory()->for($user->student)->pending()->create();

    $this->actingAs($user);

    Livewire::test('registrar.update-request-status', ['documentRequest' => $request])
        ->set('to', RequestStatus::Processing->value)
        ->call('updateStatus')
        ->assertForbidden();

    expect($request->fresh()->status)->toBe(RequestStatus::Pending);
});

test('the detail page reflects the new status after an update', function () {
    $staff = registrarStaff();
    $request = DocumentRequest::factory()->pending()->create();

    $this->actingAs($staff);

    Livewire::test('pages::registrar.show-request', ['documentRequest' => $request])
        ->assertSee(RequestStatus::Pending->label())
        ->call('refreshRequest')
        ->assertOk();

    Livewire::test('registrar.update-request-status', ['documentRequest' => $request])
        ->set('to', RequestStatus::Processing->value)
        ->call('updateStatus');

    Livewire::test('pages::registrar.show-request', ['documentRequest' => $request->fresh()])
        ->assertSee(RequestStatus::Processing->label());
});

<?php

use App\Models\Appointment;
use App\Models\DocumentRequest;
use App\Models\TimeSlot;
use Carbon\CarbonImmutable;

test('a student can open the slip for their own request', function () {
    $documentRequest = DocumentRequest::factory()->create([
        'purpose' => 'Employment requirement',
        'copies' => 2,
    ]);

    $this->actingAs($documentRequest->student->user);

    $this->get(route('requests.slip', $documentRequest))
        ->assertOk()
        ->assertSee($documentRequest->reference_no)
        ->assertSee($documentRequest->display_name)
        ->assertSee($documentRequest->student->student_number)
        ->assertSee($documentRequest->student->user->name)
        ->assertSee('Employment requirement')
        ->assertSee('Transaction Slip', escape: false);
});

test('registrar staff can open any slip', function (string $helper) {
    $documentRequest = DocumentRequest::factory()->create();

    $this->actingAs($helper());

    $this->get(route('requests.slip', $documentRequest))->assertOk();
})->with(['registrarStaff', 'administrator']);

test('a student cannot open another student\'s slip', function () {
    $documentRequest = DocumentRequest::factory()->create();

    $this->actingAs(student());

    $this->get(route('requests.slip', $documentRequest))->assertForbidden();
});

test('a guest is sent to log in', function () {
    $documentRequest = DocumentRequest::factory()->create();

    $this->get(route('requests.slip', $documentRequest))->assertRedirect(route('login'));
});

test('an unpaid slip tells the student to pay at the cashier', function () {
    $documentRequest = DocumentRequest::factory()->unpaid(200)->create();

    $this->actingAs($documentRequest->student->user);

    $this->get(route('requests.slip', $documentRequest))
        ->assertOk()
        ->assertSee('200.00')
        ->assertSeeHtml('data-test="slip-payment-due"');
});

test('a paid slip shows the receipt number instead', function () {
    $documentRequest = DocumentRequest::factory()->paid(200)->create();

    $this->actingAs($documentRequest->student->user);

    $this->get(route('requests.slip', $documentRequest))
        ->assertOk()
        ->assertSee($documentRequest->or_number)
        ->assertDontSeeHtml('data-test="slip-payment-due"');
});

test('a free slip says there is no fee', function () {
    $documentRequest = DocumentRequest::factory()->create();

    $this->actingAs($documentRequest->student->user);

    $this->get(route('requests.slip', $documentRequest))
        ->assertOk()
        ->assertSee('No fee is charged');
});

test('the slip shows the booked appointment', function () {
    $documentRequest = DocumentRequest::factory()->readyForRelease()->create();

    $slot = TimeSlot::factory()->create([
        'slot_date' => CarbonImmutable::today()->addWeekday(),
        'start_time' => '09:00',
        'end_time' => '10:00',
    ]);

    Appointment::factory()->create([
        'document_request_id' => $documentRequest->id,
        'time_slot_id' => $slot->id,
    ]);

    $this->actingAs($documentRequest->student->user);

    $this->get(route('requests.slip', $documentRequest))
        ->assertOk()
        ->assertSee($slot->slot_date->format('l, F j, Y'))
        ->assertDontSeeHtml('data-test="slip-no-appointment"');
});

test('the slip says so when no appointment is booked yet', function () {
    $documentRequest = DocumentRequest::factory()->create();

    $this->actingAs($documentRequest->student->user);

    $this->get(route('requests.slip', $documentRequest))
        ->assertOk()
        ->assertSeeHtml('data-test="slip-no-appointment"');
});

test('the slip is printed on a bare layout with no sidebar', function () {
    $documentRequest = DocumentRequest::factory()->create();

    $this->actingAs($documentRequest->student->user);

    $response = $this->get(route('requests.slip', $documentRequest));

    $response->assertOk()
        ->assertDontSee('data-flux-sidebar', escape: false)
        ->assertSeeHtml('data-test="print-slip"');
});

test('the slip carries the registrar office details and claim instructions', function () {
    $documentRequest = DocumentRequest::factory()->create();

    $this->actingAs($documentRequest->student->user);

    $this->get(route('requests.slip', $documentRequest))
        ->assertOk()
        ->assertSee(config('registrar.office.name'))
        ->assertSee(config('registrar.office.contact'))
        ->assertSee('How to claim');
});

test('both request pages link to the slip', function () {
    $documentRequest = DocumentRequest::factory()->create();

    $this->actingAs($documentRequest->student->user);
    $this->get(route('student.requests.show', $documentRequest))
        ->assertSeeHtml('data-test="print-slip-link"');

    $this->actingAs(registrarStaff());
    $this->get(route('registrar.requests.show', $documentRequest))
        ->assertSeeHtml('data-test="print-slip-link"');
});

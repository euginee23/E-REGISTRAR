<?php

use App\Actions\Requests\RecordPayment;
use App\Actions\Requests\SubmitDocumentRequest;
use App\Actions\Requests\TransitionRequestStatus;
use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTiming;
use App\Enums\RequestStatus;
use App\Exceptions\PaymentRequiredException;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->transition = app(TransitionRequestStatus::class);
});

test('submitting a pay-on-release document snapshots the timing onto the request', function () {
    $type = DocumentType::factory()->chargeable(120)->payableOnRelease()->create();

    $documentRequest = app(SubmitDocumentRequest::class)(
        student: student()->student,
        documentType: $type,
        purpose: 'Employment requirement',
    );

    expect($documentRequest->payment_status)->toBe(PaymentStatus::Unpaid)
        ->and($documentRequest->payment_timing)->toBe(PaymentTiming::OnRelease);

    // Changing the document type later never moves the gate on this request.
    $type->update(['payment_timing' => PaymentTiming::BeforeProcessing]);

    expect($documentRequest->fresh()->payment_timing)->toBe(PaymentTiming::OnRelease);
});

test('an unpaid pay-on-release request can be processed and made ready', function () {
    $documentRequest = DocumentRequest::factory()->unpaid()->payableOnRelease()->create();
    $staff = registrarStaff();

    ($this->transition)($documentRequest, RequestStatus::Processing, $staff);
    ($this->transition)($documentRequest, RequestStatus::ReadyForRelease, $staff);

    expect($documentRequest->fresh()->status)->toBe(RequestStatus::ReadyForRelease);
});

test('an unpaid pay-on-release request cannot be released', function () {
    $documentRequest = DocumentRequest::factory()->unpaid(120)->payableOnRelease()->readyForRelease()->create();

    expect(fn () => ($this->transition)($documentRequest, RequestStatus::Released, registrarStaff()))
        ->toThrow(PaymentRequiredException::class, 'before releasing it');

    expect($documentRequest->fresh()->status)->toBe(RequestStatus::ReadyForRelease);
});

test('recording the payment on claiming unblocks the release', function () {
    $documentRequest = DocumentRequest::factory()->unpaid(120)->payableOnRelease()->readyForRelease()->create();
    $staff = registrarStaff();

    app(RecordPayment::class)($documentRequest, $staff, 120, 'OR-777777');
    ($this->transition)($documentRequest->fresh(), RequestStatus::Released, $staff);

    expect($documentRequest->fresh()->status)->toBe(RequestStatus::Released);
});

test('the payment notice does not promise processing for a pay-on-release fee', function () {
    $documentRequest = DocumentRequest::factory()->unpaid()->payableOnRelease()->readyForRelease()->create();

    app(RecordPayment::class)($documentRequest, registrarStaff(), 150, 'OR-777777');

    $notification = Notification::query()
        ->where('type', NotificationType::PaymentRecorded)
        ->firstOrFail();

    expect($notification->message)->not->toContain('will now be processed');
});

test('the ready notice reminds the student to bring the fee', function () {
    $documentRequest = DocumentRequest::factory()->unpaid(120)->payableOnRelease()->processing()->create();

    ($this->transition)($documentRequest, RequestStatus::ReadyForRelease, registrarStaff());

    $notification = Notification::query()
        ->where('user_id', $documentRequest->student->user_id)
        ->where('type', NotificationType::RequestStatusChanged)
        ->latest('id')
        ->firstOrFail();

    expect($notification->message)->toContain('bring ₱120.00 for the fee');
});

test('the status modal offers processing but not release while the fee is outstanding', function () {
    $pending = DocumentRequest::factory()->unpaid()->payableOnRelease()->create();
    $ready = DocumentRequest::factory()->unpaid()->payableOnRelease()->readyForRelease()->create();

    $this->actingAs(registrarStaff());

    expect(Livewire::test('registrar.update-request-status', ['documentRequest' => $pending])->instance()->availableTransitions)
        ->toContain(RequestStatus::Processing);

    expect(Livewire::test('registrar.update-request-status', ['documentRequest' => $ready])->instance()->availableTransitions)
        ->not->toContain(RequestStatus::Released)
        ->toContain(RequestStatus::Cancelled);
});

test('the registrar is told to collect the fee on claiming', function () {
    $documentRequest = DocumentRequest::factory()->unpaid()->payableOnRelease()->create();

    $this->actingAs(registrarStaff());

    $this->get(route('registrar.requests.show', $documentRequest))
        ->assertOk()
        ->assertSeeHtml('data-test="payment-on-release-callout"')
        ->assertDontSeeHtml('data-test="payment-blocking-callout"')
        ->assertSeeHtml('data-test="record-payment-trigger"');
});

test('the student is told to pay on the day they claim', function () {
    $documentRequest = DocumentRequest::factory()->unpaid()->payableOnRelease()->create();

    $this->actingAs($documentRequest->student->user);

    $this->get(route('student.requests.show', $documentRequest))
        ->assertOk()
        ->assertSee('Pay on the day you claim your document');
});

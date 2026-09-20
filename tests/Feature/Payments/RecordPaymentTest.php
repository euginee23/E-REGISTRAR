<?php

use App\Actions\Requests\RecordPayment;
use App\Actions\Requests\TransitionRequestStatus;
use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Exceptions\PaymentAlreadyRecordedException;
use App\Exceptions\PaymentRequiredException;
use App\Models\DocumentRequest;
use App\Models\Notification;
use Illuminate\Validation\UnauthorizedException;

beforeEach(function () {
    $this->record = app(RecordPayment::class);
    $this->transition = app(TransitionRequestStatus::class);
});

test('a payment is recorded against the request', function () {
    $documentRequest = DocumentRequest::factory()->unpaid(200)->create();
    $staff = registrarStaff();

    ($this->record)($documentRequest, $staff, 200, 'OR-123456');

    $documentRequest->refresh();

    expect($documentRequest->payment_status)->toBe(PaymentStatus::Paid)
        ->and((float) $documentRequest->amount_paid)->toBe(200.0)
        ->and($documentRequest->or_number)->toBe('OR-123456')
        ->and($documentRequest->paid_at)->not->toBeNull()
        ->and($documentRequest->recorded_by_user_id)->toBe($staff->id);
});

test('recording a payment tells the student', function () {
    $documentRequest = DocumentRequest::factory()->unpaid()->create();

    ($this->record)($documentRequest, registrarStaff(), 150, 'OR-123456');

    $notification = Notification::query()
        ->where('user_id', $documentRequest->student->user_id)
        ->where('type', NotificationType::PaymentRecorded)
        ->first();

    expect($notification)->not->toBeNull()
        ->and($notification->message)->toContain($documentRequest->reference_no);
});

test('a fee can be waived without inventing a receipt number', function () {
    $documentRequest = DocumentRequest::factory()->unpaid()->create();

    ($this->record)($documentRequest, registrarStaff(), 0, null, waive: true);

    $documentRequest->refresh();

    expect($documentRequest->payment_status)->toBe(PaymentStatus::Waived)
        ->and($documentRequest->or_number)->toBeNull()
        ->and($documentRequest->isPaymentSettled())->toBeTrue();
});

test('the same fee cannot be collected twice', function () {
    $documentRequest = DocumentRequest::factory()->unpaid()->create();
    $staff = registrarStaff();

    ($this->record)($documentRequest, $staff, 150, 'OR-123456');

    expect(fn () => ($this->record)($documentRequest, $staff, 150, 'OR-999999'))
        ->toThrow(PaymentAlreadyRecordedException::class);

    expect($documentRequest->refresh()->or_number)->toBe('OR-123456');
});

test('a student cannot record their own payment', function () {
    $documentRequest = DocumentRequest::factory()->unpaid()->create();

    expect(fn () => ($this->record)($documentRequest, $documentRequest->student->user, 150, 'OR-123456'))
        ->toThrow(UnauthorizedException::class);

    expect($documentRequest->refresh()->payment_status)->toBe(PaymentStatus::Unpaid);
});

test('a free request has no payment to record', function () {
    $documentRequest = DocumentRequest::factory()->create();

    expect(fn () => ($this->record)($documentRequest, registrarStaff(), 0, null))
        ->toThrow(UnauthorizedException::class);
});

test('an unpaid request cannot be moved into processing', function () {
    $documentRequest = DocumentRequest::factory()->unpaid(200)->create();

    expect(fn () => ($this->transition)($documentRequest, RequestStatus::Processing, registrarStaff()))
        ->toThrow(PaymentRequiredException::class);

    expect($documentRequest->refresh()->status)->toBe(RequestStatus::Pending);
});

test('the blocked message names the amount and the reference', function () {
    $documentRequest = DocumentRequest::factory()->unpaid(200)->create();

    try {
        ($this->transition)($documentRequest, RequestStatus::Processing, registrarStaff());
    } catch (PaymentRequiredException $exception) {
        expect($exception->getMessage())
            ->toContain($documentRequest->reference_no)
            ->toContain('200.00');

        return;
    }

    $this->fail('The unpaid request was allowed into processing.');
});

test('recording the payment unblocks processing', function () {
    $documentRequest = DocumentRequest::factory()->unpaid()->create();
    $staff = registrarStaff();

    ($this->record)($documentRequest, $staff, 150, 'OR-123456');
    ($this->transition)($documentRequest, RequestStatus::Processing, $staff);

    expect($documentRequest->refresh()->status)->toBe(RequestStatus::Processing);
});

test('waiving the fee also unblocks processing', function () {
    $documentRequest = DocumentRequest::factory()->unpaid()->create();
    $staff = registrarStaff();

    ($this->record)($documentRequest, $staff, 0, null, waive: true);
    ($this->transition)($documentRequest, RequestStatus::Processing, $staff);

    expect($documentRequest->refresh()->status)->toBe(RequestStatus::Processing);
});

test('an unpaid request can still be rejected or cancelled', function (RequestStatus $to) {
    $documentRequest = DocumentRequest::factory()->unpaid()->create();

    ($this->transition)($documentRequest, $to, registrarStaff(), 'Closing this out.');

    expect($documentRequest->refresh()->status)->toBe($to);
})->with([
    RequestStatus::Rejected,
    RequestStatus::Cancelled,
]);

test('a free request is never blocked from processing', function () {
    $documentRequest = DocumentRequest::factory()->create();

    ($this->transition)($documentRequest, RequestStatus::Processing, registrarStaff());

    expect($documentRequest->refresh()->status)->toBe(RequestStatus::Processing);
});

test('the awaiting payment scope finds only outstanding fees', function () {
    DocumentRequest::factory()->unpaid()->create();
    DocumentRequest::factory()->paid()->create();
    DocumentRequest::factory()->create();

    expect(DocumentRequest::query()->awaitingPayment()->count())->toBe(1);
});

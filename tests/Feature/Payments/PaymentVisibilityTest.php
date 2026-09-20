<?php

use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use Livewire\Livewire;

test('a student sees the fee and is told where to pay it', function () {
    $documentRequest = DocumentRequest::factory()->unpaid(200)->create();

    $this->actingAs($documentRequest->student->user);

    $this->get(route('student.requests.show', $documentRequest))
        ->assertOk()
        ->assertSee('200.00')
        ->assertSee('Fee &amp; payment', escape: false)
        ->assertSee('cashier', escape: false);
});

test('a student sees the receipt number once the payment is recorded', function () {
    $documentRequest = DocumentRequest::factory()->paid(200)->create();

    $this->actingAs($documentRequest->student->user);

    $this->get(route('student.requests.show', $documentRequest))
        ->assertOk()
        ->assertSee($documentRequest->or_number)
        ->assertSee(PaymentStatus::Paid->label());
});

test('a free request shows no payment block at all', function () {
    $documentRequest = DocumentRequest::factory()->create();

    $this->actingAs($documentRequest->student->user);

    $this->get(route('student.requests.show', $documentRequest))
        ->assertOk()
        ->assertDontSeeHtml('data-test="student-payment-card"');
});

test('the registrar is warned that payment blocks processing', function () {
    $documentRequest = DocumentRequest::factory()->unpaid()->create();

    $this->actingAs(registrarStaff());

    $this->get(route('registrar.requests.show', $documentRequest))
        ->assertOk()
        ->assertSeeHtml('data-test="payment-blocking-callout"')
        ->assertSeeHtml('data-test="record-payment-trigger"');
});

test('the record payment trigger disappears once the fee is settled', function () {
    $documentRequest = DocumentRequest::factory()->paid()->create();

    $this->actingAs(registrarStaff());

    $this->get(route('registrar.requests.show', $documentRequest))
        ->assertOk()
        ->assertSeeHtml('data-test="payment-card"')
        ->assertDontSeeHtml('data-test="record-payment-trigger"');
});

test('a free request offers the registrar nothing to record', function () {
    $documentRequest = DocumentRequest::factory()->create();

    $this->actingAs(registrarStaff());

    $this->get(route('registrar.requests.show', $documentRequest))
        ->assertOk()
        ->assertDontSeeHtml('data-test="record-payment-trigger"')
        ->assertDontSeeHtml('data-test="payment-card"');
});

test('the status modal never offers processing while the fee is outstanding', function () {
    $documentRequest = DocumentRequest::factory()->unpaid()->create();

    $this->actingAs(registrarStaff());

    $component = Livewire::test('registrar.update-request-status', [
        'documentRequest' => $documentRequest,
    ]);

    expect($component->instance()->availableTransitions)
        ->not->toContain(RequestStatus::Processing)
        ->toContain(RequestStatus::Rejected);
});

test('the status modal offers processing again once payment is recorded', function () {
    $documentRequest = DocumentRequest::factory()->paid()->create();

    $this->actingAs(registrarStaff());

    $component = Livewire::test('registrar.update-request-status', [
        'documentRequest' => $documentRequest,
    ]);

    expect($component->instance()->availableTransitions)->toContain(RequestStatus::Processing);
});

test('staff record a payment through the modal', function () {
    $documentRequest = DocumentRequest::factory()->unpaid(200)->create();

    $this->actingAs(registrarStaff());

    Livewire::test('registrar.record-payment', ['documentRequest' => $documentRequest])
        ->assertSet('amount', (string) $documentRequest->fee_amount)
        ->set('orNumber', 'OR-778899')
        ->call('recordPayment')
        ->assertHasNoErrors();

    $documentRequest->refresh();

    expect($documentRequest->payment_status)->toBe(PaymentStatus::Paid)
        ->and($documentRequest->or_number)->toBe('OR-778899');
});

test('the modal demands a receipt number unless the fee is waived', function () {
    $documentRequest = DocumentRequest::factory()->unpaid()->create();

    $this->actingAs(registrarStaff());

    Livewire::test('registrar.record-payment', ['documentRequest' => $documentRequest])
        ->set('orNumber', '')
        ->call('recordPayment')
        ->assertHasErrors('orNumber');

    Livewire::test('registrar.record-payment', ['documentRequest' => $documentRequest])
        ->set('waive', true)
        ->call('recordPayment')
        ->assertHasNoErrors();

    expect($documentRequest->refresh()->payment_status)->toBe(PaymentStatus::Waived);
});

test('a student sees the payment state in their request list', function () {
    DocumentRequest::factory()->unpaid()->create();
    $student = DocumentRequest::query()->firstOrFail()->student->user;

    $this->actingAs($student);

    $this->get(route('student.requests.index'))
        ->assertOk()
        ->assertSee(PaymentStatus::Unpaid->label());
});

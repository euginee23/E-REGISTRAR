<?php

use App\Enums\RequestStatus;
use App\Models\Appointment;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\RequestAttachment;
use App\Models\Student;
use App\Models\User;

test('a request resolves its student, document type, and owning user', function () {
    $user = student();
    $request = DocumentRequest::factory()->for($user->student)->create();

    expect($request->student)->toBeInstanceOf(Student::class)
        ->and($request->student->user->id)->toBe($user->id)
        ->and($request->documentType)->toBeInstanceOf(DocumentType::class);
});

test('a request exposes its attachments, appointment, and status history', function () {
    $request = DocumentRequest::factory()->processing()->create();
    RequestAttachment::factory()->count(2)->for($request)->create();
    Appointment::factory()->for($request)->create();

    expect($request->attachments)->toHaveCount(2)
        ->and($request->appointment)->toBeInstanceOf(Appointment::class);
});

test('the display name falls back to the document type name', function () {
    $type = DocumentType::factory()->create(['name' => 'Transcript of Records']);
    $request = DocumentRequest::factory()->for($type)->create(['other_document_name' => null]);

    expect($request->display_name)->toBe('Transcript of Records');
});

test('the display name uses the free-text name when one is supplied', function () {
    $type = DocumentType::factory()->customNamed()->create(['name' => 'Other Academic Document']);
    $request = DocumentRequest::factory()->for($type)->create([
        'other_document_name' => 'Certificate of Units Earned',
    ]);

    expect($request->display_name)->toBe('Certificate of Units Earned');
});

test('the earliest claim date counts processing days as weekdays', function () {
    // Friday 2026-08-14 + 3 weekdays lands on Wednesday 2026-08-19, skipping the weekend.
    $type = DocumentType::factory()->create(['processing_days' => 3]);
    $request = DocumentRequest::factory()->for($type)->create([
        'created_at' => '2026-08-14 09:00:00',
    ]);

    expect($request->earliestClaimDate()->toDateString())->toBe('2026-08-19');
});

test('a new request defaults to pending with a single copy', function () {
    $request = new DocumentRequest;

    expect($request->status)->toBe(RequestStatus::Pending)
        ->and($request->copies)->toBe(1);
});

test('the forStudent scope only returns that student\'s requests', function () {
    $mine = student()->student;
    $theirs = student()->student;

    DocumentRequest::factory()->count(3)->for($mine)->create();
    DocumentRequest::factory()->count(2)->for($theirs)->create();

    expect(DocumentRequest::query()->forStudent($mine)->count())->toBe(3);
});

test('the open scope excludes finished requests', function () {
    DocumentRequest::factory()->pending()->create();
    DocumentRequest::factory()->processing()->create();
    DocumentRequest::factory()->readyForRelease()->create();
    DocumentRequest::factory()->released()->create();
    DocumentRequest::factory()->rejected()->create();
    DocumentRequest::factory()->cancelled()->create();

    expect(DocumentRequest::query()->open()->count())->toBe(3);
});

test('the withStatus scope filters to one status', function () {
    DocumentRequest::factory()->count(2)->released()->create();
    DocumentRequest::factory()->pending()->create();

    expect(DocumentRequest::query()->withStatus(RequestStatus::Released)->count())->toBe(2);
});

test('a request is routed by its reference number', function () {
    $request = DocumentRequest::factory()->create();

    expect($request->getRouteKeyName())->toBe('reference_no')
        ->and($request->getRouteKey())->toBe($request->reference_no);
});

test('deleting a student removes their requests', function () {
    $user = student();
    DocumentRequest::factory()->count(2)->for($user->student)->create();

    $user->delete();

    expect(DocumentRequest::query()->count())->toBe(0)
        ->and(Student::query()->count())->toBe(0)
        ->and(User::query()->count())->toBe(0);
});

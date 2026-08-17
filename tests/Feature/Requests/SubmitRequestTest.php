<?php

use App\Enums\NotificationType;
use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\Notification;
use App\Models\RequestAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('requirements');

    $this->documentType = DocumentType::factory()->create([
        'name' => 'Transcript of Records',
        'processing_days' => 7,
    ]);
});

test('the request form renders for a student', function () {
    $this->actingAs(student());

    $this->get(route('student.requests.create'))->assertOk();
});

test('staff cannot open the student request form', function (string $helper) {
    $this->actingAs($helper());

    $this->get(route('student.requests.create'))->assertForbidden();
})->with(['registrarStaff', 'administrator']);

test('a student can submit a request', function () {
    $user = student();
    $this->actingAs($user);

    Livewire::test('pages::student.create-request')
        ->set('document_type_id', $this->documentType->id)
        ->set('purpose', 'Employment requirement')
        ->set('copies', 2)
        ->call('submit')
        ->assertHasNoErrors();

    $request = DocumentRequest::query()->firstOrFail();

    expect($request->student_id)->toBe($user->student->id)
        ->and($request->document_type_id)->toBe($this->documentType->id)
        ->and($request->purpose)->toBe('Employment requirement')
        ->and($request->copies)->toBe(2)
        ->and($request->status)->toBe(RequestStatus::Pending)
        ->and($request->reference_no)->toMatch('/^REG-\d{4}-\d{6}$/');
});

test('submitting records the opening status history entry', function () {
    $user = student();
    $this->actingAs($user);

    Livewire::test('pages::student.create-request')
        ->set('document_type_id', $this->documentType->id)
        ->set('purpose', 'Employment requirement')
        ->call('submit');

    $request = DocumentRequest::query()->firstOrFail();

    expect($request->statusHistories)->toHaveCount(1)
        ->and($request->statusHistories->first()->from_status)->toBeNull()
        ->and($request->statusHistories->first()->to_status)->toBe(RequestStatus::Pending)
        ->and($request->statusHistories->first()->changed_by_user_id)->toBe($user->id);
});

test('submitting notifies the student and the registrar personnel', function () {
    $staff = registrarStaff();
    $admin = administrator();
    $user = student();

    $this->actingAs($user);

    Livewire::test('pages::student.create-request')
        ->set('document_type_id', $this->documentType->id)
        ->set('purpose', 'Employment requirement')
        ->call('submit');

    expect(Notification::query()->where('user_id', $user->id)->where('type', NotificationType::RequestSubmitted)->exists())->toBeTrue()
        ->and(Notification::query()->where('user_id', $staff->id)->where('type', NotificationType::RequestReceived)->exists())->toBeTrue()
        ->and(Notification::query()->where('user_id', $admin->id)->where('type', NotificationType::RequestReceived)->exists())->toBeTrue()
        ->and(Notification::query()->where('user_id', $user->id)->where('type', NotificationType::RequestReceived)->exists())->toBeFalse();
});

test('uploaded requirements are stored on the private disk', function () {
    $this->actingAs(student());

    Livewire::test('pages::student.create-request')
        ->set('document_type_id', $this->documentType->id)
        ->set('purpose', 'Employment requirement')
        ->set('attachments', [
            UploadedFile::fake()->create('valid-id.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('clearance.pdf', 120, 'application/pdf'),
        ])
        ->call('submit')
        ->assertHasNoErrors();

    $attachments = RequestAttachment::query()->get();

    expect($attachments)->toHaveCount(2);

    foreach ($attachments as $attachment) {
        expect($attachment->disk)->toBe('requirements');
        Storage::disk('requirements')->assertExists($attachment->path);
    }

    expect($attachments->pluck('original_name')->all())
        ->toEqualCanonicalizing(['valid-id.pdf', 'clearance.pdf']);
});

test('a purpose is required', function () {
    $this->actingAs(student());

    Livewire::test('pages::student.create-request')
        ->set('document_type_id', $this->documentType->id)
        ->set('purpose', '')
        ->call('submit')
        ->assertHasErrors('purpose');

    expect(DocumentRequest::query()->count())->toBe(0);
});

test('the number of copies must be sensible', function (int $copies) {
    $this->actingAs(student());

    Livewire::test('pages::student.create-request')
        ->set('document_type_id', $this->documentType->id)
        ->set('purpose', 'Employment requirement')
        ->set('copies', $copies)
        ->call('submit')
        ->assertHasErrors('copies');
})->with([0, 11]);

test('a retired document type cannot be requested', function () {
    $retired = DocumentType::factory()->inactive()->create();

    $this->actingAs(student());

    Livewire::test('pages::student.create-request')
        ->set('document_type_id', $retired->id)
        ->set('purpose', 'Employment requirement')
        ->call('submit')
        ->assertHasErrors('document_type_id');
});

test('a free-text name is required only for the other-document type', function () {
    $other = DocumentType::factory()->customNamed()->create();

    $this->actingAs(student());

    Livewire::test('pages::student.create-request')
        ->set('document_type_id', $other->id)
        ->set('purpose', 'Employment requirement')
        ->call('submit')
        ->assertHasErrors('other_document_name');
});

test('the free-text name is kept for the other-document type', function () {
    $other = DocumentType::factory()->customNamed()->create();

    $this->actingAs(student());

    Livewire::test('pages::student.create-request')
        ->set('document_type_id', $other->id)
        ->set('other_document_name', 'Certificate of Units Earned')
        ->set('purpose', 'Employment requirement')
        ->call('submit');

    $request = DocumentRequest::query()->firstOrFail();

    expect($request->other_document_name)->toBe('Certificate of Units Earned')
        ->and($request->display_name)->toBe('Certificate of Units Earned');
});

test('a free-text name is discarded for a standard document type', function () {
    $this->actingAs(student());

    Livewire::test('pages::student.create-request')
        ->set('document_type_id', $this->documentType->id)
        ->set('other_document_name', 'Something else entirely')
        ->set('purpose', 'Employment requirement')
        ->call('submit');

    expect(DocumentRequest::query()->firstOrFail()->other_document_name)->toBeNull();
});

test('an oversized file is rejected', function () {
    $this->actingAs(student());

    Livewire::test('pages::student.create-request')
        ->set('document_type_id', $this->documentType->id)
        ->set('purpose', 'Employment requirement')
        ->set('attachments', [UploadedFile::fake()->create('huge.pdf', 6000, 'application/pdf')])
        ->call('submit')
        ->assertHasErrors('attachments.0');
});

test('a disallowed file type is rejected', function () {
    $this->actingAs(student());

    Livewire::test('pages::student.create-request')
        ->set('document_type_id', $this->documentType->id)
        ->set('purpose', 'Employment requirement')
        ->set('attachments', [UploadedFile::fake()->create('script.exe', 10)])
        ->call('submit')
        ->assertHasErrors('attachments.0');
});

test('more files than allowed are rejected', function () {
    $this->actingAs(student());

    $files = collect(range(1, 6))->map(
        fn (int $i) => UploadedFile::fake()->create("file-{$i}.pdf", 10, 'application/pdf'),
    )->all();

    Livewire::test('pages::student.create-request')
        ->set('document_type_id', $this->documentType->id)
        ->set('purpose', 'Employment requirement')
        ->set('attachments', $files)
        ->call('submit')
        ->assertHasErrors('attachments');

    expect(DocumentRequest::query()->count())->toBe(0);
});

test('a student can drop a file before submitting', function () {
    $this->actingAs(student());

    Livewire::test('pages::student.create-request')
        ->set('attachments', [
            UploadedFile::fake()->create('one.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->create('two.pdf', 10, 'application/pdf'),
        ])
        ->call('removeAttachment', 0)
        ->assertCount('attachments', 1);
});

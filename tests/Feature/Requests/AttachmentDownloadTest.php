<?php

use App\Models\DocumentRequest;
use App\Models\RequestAttachment;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('requirements');
});

/**
 * Create an attachment with a real file behind it on the fake disk.
 */
function attachmentFor(DocumentRequest $request): RequestAttachment
{
    $path = 'requests/'.$request->id.'/valid-id.pdf';

    Storage::disk('requirements')->put($path, 'file contents');

    return RequestAttachment::factory()->for($request)->create([
        'disk' => 'requirements',
        'path' => $path,
        'original_name' => 'valid-id.pdf',
    ]);
}

test('the owning student can download their requirement', function () {
    $user = student();
    $attachment = attachmentFor(DocumentRequest::factory()->for($user->student)->create());

    $this->actingAs($user);

    $this->get(route('attachments.download', $attachment))
        ->assertOk()
        ->assertDownload('valid-id.pdf');
});

test('another student cannot download the requirement', function () {
    $attachment = attachmentFor(DocumentRequest::factory()->for(student()->student)->create());

    $this->actingAs(student());

    $this->get(route('attachments.download', $attachment))->assertForbidden();
});

test('staff can download any requirement', function (string $helper) {
    $attachment = attachmentFor(DocumentRequest::factory()->create());

    $this->actingAs($helper());

    $this->get(route('attachments.download', $attachment))->assertOk();
})->with(['registrarStaff', 'administrator']);

test('guests cannot download requirements', function () {
    $attachment = attachmentFor(DocumentRequest::factory()->create());

    $this->get(route('attachments.download', $attachment))->assertRedirect(route('login'));
});

test('a missing file reports not found rather than erroring', function () {
    $user = student();
    $attachment = attachmentFor(DocumentRequest::factory()->for($user->student)->create());

    Storage::disk('requirements')->delete($attachment->path);

    $this->actingAs($user);

    $this->get(route('attachments.download', $attachment))->assertNotFound();
});

test('deleting an attachment removes the stored file', function () {
    $attachment = attachmentFor(DocumentRequest::factory()->create());

    $attachment->delete();

    Storage::disk('requirements')->assertMissing($attachment->path);
});

test('deleting a request removes its files from disk, not just the rows', function () {
    $request = DocumentRequest::factory()->create();
    $attachment = attachmentFor($request);

    $request->delete();

    Storage::disk('requirements')->assertMissing($attachment->path);
    expect(RequestAttachment::query()->count())->toBe(0);
});

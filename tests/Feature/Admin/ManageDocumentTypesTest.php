<?php

use App\Models\DocumentRequest;
use App\Models\DocumentType;
use Livewire\Livewire;

test('only administrators can open the document type screen', function () {
    $this->actingAs(administrator());
    $this->get(route('admin.document-types.index'))->assertOk();

    $this->actingAs(registrarStaff());
    $this->get(route('admin.document-types.index'))->assertForbidden();

    $this->actingAs(student());
    $this->get(route('admin.document-types.index'))->assertForbidden();
});

test('an administrator can define a new document type', function () {
    $this->actingAs(administrator());

    Livewire::test('pages::admin.document-types')
        ->call('createType')
        ->set('name', 'Certificate of Grades')
        ->set('description', 'A summary of grades for a term.')
        ->set('processingDays', 4)
        ->call('saveType')
        ->assertHasNoErrors();

    $type = DocumentType::query()->where('name', 'Certificate of Grades')->firstOrFail();

    expect($type->slug)->toBe('certificate-of-grades')
        ->and($type->processing_days)->toBe(4)
        ->and($type->is_active)->toBeTrue()
        ->and($type->requires_custom_name)->toBeFalse();
});

test('a new type appears in the student request form', function () {
    $this->actingAs(administrator());

    Livewire::test('pages::admin.document-types')
        ->call('createType')
        ->set('name', 'Certificate of Grades')
        ->set('processingDays', 4)
        ->call('saveType');

    $this->actingAs(student());

    $this->get(route('student.requests.create'))
        ->assertOk()
        ->assertSee('Certificate of Grades');
});

test('document type names must be unique', function () {
    DocumentType::factory()->create(['name' => 'Transcript of Records']);

    $this->actingAs(administrator());

    Livewire::test('pages::admin.document-types')
        ->call('createType')
        ->set('name', 'Transcript of Records')
        ->set('processingDays', 3)
        ->call('saveType')
        ->assertHasErrors('name');
});

test('processing days must be at least one', function () {
    $this->actingAs(administrator());

    Livewire::test('pages::admin.document-types')
        ->call('createType')
        ->set('name', 'Instant Document')
        ->set('processingDays', 0)
        ->call('saveType')
        ->assertHasErrors('processingDays');
});

test('an administrator can change the processing window', function () {
    $type = DocumentType::factory()->create(['processing_days' => 3]);

    $this->actingAs(administrator());

    Livewire::test('pages::admin.document-types')
        ->call('editType', $type->id)
        ->set('processingDays', 10)
        ->call('saveType')
        ->assertHasNoErrors();

    expect($type->fresh()->processing_days)->toBe(10);
});

test('a retired type disappears from the student request form', function () {
    $type = DocumentType::factory()->create(['name' => 'Retired Document']);

    $this->actingAs(administrator());
    Livewire::test('pages::admin.document-types')->call('toggleActive', $type->id);

    expect($type->fresh()->is_active)->toBeFalse();

    $this->actingAs(student());

    $this->get(route('student.requests.create'))
        ->assertOk()
        ->assertDontSee('Retired Document');
});

test('retiring a type leaves historical requests intact', function () {
    $type = DocumentType::factory()->create(['name' => 'Retired Document']);
    $request = DocumentRequest::factory()->for($type)->released()->create();

    $this->actingAs(administrator());
    Livewire::test('pages::admin.document-types')->call('toggleActive', $type->id);

    expect($request->fresh()->documentType->name)->toBe('Retired Document')
        ->and($request->fresh()->display_name)->toBe('Retired Document');

    // The registrar can still open the request it belongs to.
    $this->actingAs(registrarStaff());
    $this->get(route('registrar.requests.show', $request))->assertOk();
});

test('a retired type can be reinstated', function () {
    $type = DocumentType::factory()->inactive()->create();

    $this->actingAs(administrator());

    Livewire::test('pages::admin.document-types')->call('toggleActive', $type->id);

    expect($type->fresh()->is_active)->toBeTrue();
});

test('a type in use cannot be hard deleted', function () {
    $type = DocumentType::factory()->create();
    DocumentRequest::factory()->for($type)->create();

    expect(administrator()->can('delete', $type))->toBeFalse();
});

test('the screen shows how many requests each type has', function () {
    $type = DocumentType::factory()->create(['name' => 'Popular Document']);
    DocumentRequest::factory()->count(3)->for($type)->create();

    $this->actingAs(administrator());

    $counts = Livewire::test('pages::admin.document-types')
        ->instance()
        ->documentTypes
        ->firstWhere('name', 'Popular Document');

    expect($counts->document_requests_count)->toBe(3);
});

test('a catch-all type can be flagged to take a free-text name', function () {
    $this->actingAs(administrator());

    Livewire::test('pages::admin.document-types')
        ->call('createType')
        ->set('name', 'Other Academic Record')
        ->set('processingDays', 5)
        ->set('requiresCustomName', true)
        ->call('saveType')
        ->assertHasNoErrors();

    expect(DocumentType::query()->where('name', 'Other Academic Record')->firstOrFail()->requires_custom_name)
        ->toBeTrue();
});

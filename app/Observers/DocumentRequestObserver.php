<?php

namespace App\Observers;

use App\Actions\Requests\GenerateReferenceNumber;
use App\Models\DocumentRequest;
use Illuminate\Support\Facades\Storage;

class DocumentRequestObserver
{
    /**
     * Handle the DocumentRequest "creating" event.
     *
     * Assigning the reference number here guarantees one exists no matter the
     * call site - the submission form, a factory, a seeder, or tinker - so no
     * request can ever be persisted without a way to track it.
     */
    public function creating(DocumentRequest $documentRequest): void
    {
        if (blank($documentRequest->reference_no)) {
            $documentRequest->reference_no = app(GenerateReferenceNumber::class)();
        }
    }

    /**
     * Handle the DocumentRequest "deleting" event.
     *
     * The foreign key cascade removes attachment rows but never touches the
     * stored files, so they are deleted explicitly here to avoid orphaning
     * uploaded requirements on disk.
     */
    public function deleting(DocumentRequest $documentRequest): void
    {
        $documentRequest->attachments()->each(function ($attachment): void {
            Storage::disk($attachment->disk)->delete($attachment->path);
        });
    }
}

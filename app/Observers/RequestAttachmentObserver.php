<?php

namespace App\Observers;

use App\Models\RequestAttachment;
use Illuminate\Support\Facades\Storage;

class RequestAttachmentObserver
{
    /**
     * Handle the RequestAttachment "deleted" event.
     *
     * Removing the row must also remove the file, otherwise deleted
     * requirements linger on disk as unreferenced personal documents.
     */
    public function deleted(RequestAttachment $requestAttachment): void
    {
        Storage::disk($requestAttachment->disk)->delete($requestAttachment->path);
    }
}

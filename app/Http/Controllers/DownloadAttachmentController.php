<?php

namespace App\Http\Controllers;

use App\Models\RequestAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadAttachmentController extends Controller
{
    /**
     * Stream a supporting requirement back to an authorized viewer.
     *
     * Requirements live on a private disk, so this route is the only way to
     * reach them. A plain route rather than a Livewire action gives staff a
     * stable URL they can open in a new tab.
     */
    public function __invoke(RequestAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment->documentRequest);

        abort_unless(
            Storage::disk($attachment->disk)->exists($attachment->path),
            404,
        );

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name,
        );
    }
}

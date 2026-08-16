<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\RequestAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

/**
 * @property int $id
 * @property int $document_request_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read string $human_size
 * @property-read DocumentRequest $documentRequest
 */
#[Fillable(['document_request_id', 'disk', 'path', 'original_name', 'mime_type', 'size'])]
class RequestAttachment extends Model
{
    /** @use HasFactory<RequestAttachmentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * Get the request the supporting requirement was uploaded for.
     *
     * @return BelongsTo<DocumentRequest, $this>
     */
    public function documentRequest(): BelongsTo
    {
        return $this->belongsTo(DocumentRequest::class);
    }

    /**
     * Get the file size formatted for display.
     *
     * @return Attribute<string, never>
     */
    protected function humanSize(): Attribute
    {
        return Attribute::get(fn (): string => (string) Number::fileSize($this->size, precision: 1));
    }
}

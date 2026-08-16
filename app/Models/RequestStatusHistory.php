<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Carbon\CarbonImmutable;
use Database\Factories\RequestStatusHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $document_request_id
 * @property RequestStatus|null $from_status
 * @property RequestStatus $to_status
 * @property int|null $changed_by_user_id
 * @property string|null $remarks
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read DocumentRequest $documentRequest
 * @property-read User|null $changedBy
 */
#[Fillable(['document_request_id', 'from_status', 'to_status', 'changed_by_user_id', 'remarks'])]
class RequestStatusHistory extends Model
{
    /** @use HasFactory<RequestStatusHistoryFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => RequestStatus::class,
            'to_status' => RequestStatus::class,
        ];
    }

    /**
     * Get the request this history entry belongs to.
     *
     * @return BelongsTo<DocumentRequest, $this>
     */
    public function documentRequest(): BelongsTo
    {
        return $this->belongsTo(DocumentRequest::class);
    }

    /**
     * Get the user who made the status change.
     *
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}

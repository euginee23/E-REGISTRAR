<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Observers\DocumentRequestObserver;
use Carbon\CarbonImmutable;
use Database\Factories\DocumentRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $reference_no
 * @property int $student_id
 * @property int $document_type_id
 * @property string|null $other_document_name
 * @property string $purpose
 * @property int $copies
 * @property RequestStatus $status
 * @property string|null $remarks
 * @property int|null $processed_by_user_id
 * @property CarbonImmutable|null $ready_at
 * @property CarbonImmutable|null $released_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read string $display_name
 * @property-read Student $student
 * @property-read DocumentType $documentType
 * @property-read User|null $processedBy
 * @property-read Appointment|null $appointment
 * @property-read Collection<int, RequestAttachment> $attachments
 * @property-read Collection<int, RequestStatusHistory> $statusHistories
 */
#[Fillable([
    'reference_no',
    'student_id',
    'document_type_id',
    'other_document_name',
    'purpose',
    'copies',
    'status',
    'remarks',
    'processed_by_user_id',
    'ready_at',
    'released_at',
])]
#[ObservedBy(DocumentRequestObserver::class)]
class DocumentRequest extends Model
{
    /** @use HasFactory<DocumentRequestFactory> */
    use HasFactory;

    /**
     * The model's default attribute values.
     *
     * @var array<string, int|string>
     */
    protected $attributes = [
        'copies' => 1,
        'status' => RequestStatus::Pending->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'copies' => 'integer',
            'status' => RequestStatus::class,
            'ready_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
        ];
    }

    /**
     * Get the student who submitted the request.
     *
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the type of document being requested.
     *
     * @return BelongsTo<DocumentType, $this>
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    /**
     * Get the registrar personnel who last processed the request.
     *
     * @return BelongsTo<User, $this>
     */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }

    /**
     * Get the supporting requirements uploaded with the request.
     *
     * @return HasMany<RequestAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(RequestAttachment::class);
    }

    /**
     * Get the claiming appointment booked for the request.
     *
     * @return HasOne<Appointment, $this>
     */
    public function appointment(): HasOne
    {
        return $this->hasOne(Appointment::class);
    }

    /**
     * Get the audit trail of status changes for the request.
     *
     * @return HasMany<RequestStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(RequestStatusHistory::class)->oldest();
    }

    /**
     * Get the document name shown to users.
     *
     * Falls back to the free-text name captured for document types that
     * require one, such as "Other Academic Document".
     *
     * @return Attribute<string, never>
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn (): string => $this->other_document_name ?: $this->documentType->name);
    }

    /**
     * Get the earliest date the document could realistically be claimed.
     *
     * Processing days are counted as weekdays because the registrar's office
     * is closed on weekends.
     */
    public function earliestClaimDate(): CarbonImmutable
    {
        $submittedAt = $this->created_at ?? CarbonImmutable::now();

        return $submittedAt->addWeekdays($this->documentType->processing_days)->startOfDay();
    }

    /**
     * Scope the query to requests belonging to the given student.
     *
     * @param  Builder<DocumentRequest>  $query
     */
    #[Scope]
    protected function forStudent(Builder $query, Student $student): void
    {
        $query->whereBelongsTo($student);
    }

    /**
     * Scope the query to requests in the given status.
     *
     * @param  Builder<DocumentRequest>  $query
     */
    #[Scope]
    protected function withStatus(Builder $query, RequestStatus $status): void
    {
        $query->where('status', $status);
    }

    /**
     * Scope the query to requests still awaiting release.
     *
     * @param  Builder<DocumentRequest>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereIn('status', RequestStatus::open());
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'reference_no';
    }
}

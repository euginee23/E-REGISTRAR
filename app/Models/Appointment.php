<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Carbon\CarbonImmutable;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $document_request_id
 * @property int $time_slot_id
 * @property AppointmentStatus $status
 * @property int|null $confirmed_by_user_id
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $completed_at
 * @property string|null $cancellation_reason
 * @property CarbonImmutable|null $reminder_sent_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read DocumentRequest $documentRequest
 * @property-read TimeSlot $timeSlot
 * @property-read User|null $confirmedBy
 */
#[Fillable([
    'document_request_id',
    'time_slot_id',
    'status',
    'confirmed_by_user_id',
    'confirmed_at',
    'cancelled_at',
    'completed_at',
    'cancellation_reason',
    'reminder_sent_at',
])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    /**
     * The model's default attribute values.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => AppointmentStatus::Scheduled->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AppointmentStatus::class,
            'confirmed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'reminder_sent_at' => 'immutable_datetime',
        ];
    }

    /**
     * Get the document request being claimed at this appointment.
     *
     * @return BelongsTo<DocumentRequest, $this>
     */
    public function documentRequest(): BelongsTo
    {
        return $this->belongsTo(DocumentRequest::class);
    }

    /**
     * Get the time slot the appointment occupies.
     *
     * @return BelongsTo<TimeSlot, $this>
     */
    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class);
    }

    /**
     * Get the registrar personnel who confirmed the appointment.
     *
     * @return BelongsTo<User, $this>
     */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    /**
     * Determine whether the appointment currently occupies a slot seat.
     */
    public function occupiesCapacity(): bool
    {
        return $this->status->occupiesCapacity();
    }

    /**
     * Scope the query to appointments falling on the given date.
     *
     * @param  Builder<Appointment>  $query
     */
    #[Scope]
    protected function onDate(Builder $query, CarbonImmutable $date): void
    {
        $query->whereHas('timeSlot', fn (Builder $slot) => $slot->whereDate('slot_date', $date));
    }

    /**
     * Scope the query to appointments that still consume a seat.
     *
     * @param  Builder<Appointment>  $query
     */
    #[Scope]
    protected function occupying(Builder $query): void
    {
        $query->whereIn('status', AppointmentStatus::occupying());
    }
}

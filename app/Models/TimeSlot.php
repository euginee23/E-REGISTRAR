<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Carbon\CarbonImmutable;
use Database\Factories\TimeSlotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property CarbonImmutable $slot_date
 * @property string $start_time
 * @property string $end_time
 * @property int $capacity
 * @property int $booked_count
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read int $remaining_capacity
 * @property-read string $label
 * @property-read Collection<int, Appointment> $appointments
 */
#[Fillable(['slot_date', 'start_time', 'end_time', 'capacity', 'booked_count', 'is_active'])]
class TimeSlot extends Model
{
    /** @use HasFactory<TimeSlotFactory> */
    use HasFactory;

    /**
     * The model's default attribute values.
     *
     * @var array<string, bool|int>
     */
    protected $attributes = [
        'capacity' => 5,
        'booked_count' => 0,
        'is_active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slot_date' => 'immutable_date',
            'capacity' => 'integer',
            'booked_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the appointments booked into this slot.
     *
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Get the number of seats still available in the slot.
     *
     * @return Attribute<int<0, max>, never>
     */
    protected function remainingCapacity(): Attribute
    {
        return Attribute::get(fn (): int => max(0, $this->capacity - $this->booked_count));
    }

    /**
     * Get the slot's display label, such as "8:00 AM - 9:00 AM".
     *
     * @return Attribute<non-falsy-string, never>
     */
    protected function label(): Attribute
    {
        return Attribute::get(fn (): string => $this->startsAt()->format('g:i A').' - '.$this->endsAt()->format('g:i A'));
    }

    /**
     * Determine whether every seat in the slot is taken.
     */
    public function isFull(): bool
    {
        return $this->booked_count >= $this->capacity;
    }

    /**
     * Get the moment the slot begins, composed from its date and start time.
     */
    public function startsAt(): CarbonImmutable
    {
        return $this->composeMoment($this->start_time);
    }

    /**
     * Get the moment the slot ends, composed from its date and end time.
     */
    public function endsAt(): CarbonImmutable
    {
        return $this->composeMoment($this->end_time);
    }

    /**
     * Determine whether a student may still book into this slot.
     */
    public function isBookable(): bool
    {
        return $this->is_active && ! $this->isFull() && $this->startsAt()->isFuture();
    }

    /**
     * Recalculate the denormalised booking counter from the appointments table.
     */
    public function recountBookings(): int
    {
        return $this->appointments()
            ->whereIn('status', AppointmentStatus::occupying())
            ->count();
    }

    /**
     * Scope the query to active slots on the given date.
     *
     * @param  Builder<TimeSlot>  $query
     */
    #[Scope]
    protected function onDate(Builder $query, CarbonImmutable $date): void
    {
        $query->whereDate('slot_date', $date)->where('is_active', true);
    }

    /**
     * Scope the query to slots that still have seats available.
     *
     * @param  Builder<TimeSlot>  $query
     */
    #[Scope]
    protected function available(Builder $query): void
    {
        $query->where('is_active', true)->whereColumn('booked_count', '<', 'capacity');
    }

    /**
     * Compose a moment from the slot's date and the given wall-clock time.
     */
    private function composeMoment(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse(
            $this->slot_date->format('Y-m-d').' '.$time,
            config('app.timezone'),
        );
    }
}

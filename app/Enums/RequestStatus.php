<?php

namespace App\Enums;

enum RequestStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case ReadyForRelease = 'ready_for_release';
    case Released = 'released';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /**
     * Get the human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Processing => __('Processing'),
            self::ReadyForRelease => __('Ready for release'),
            self::Released => __('Released'),
            self::Rejected => __('Rejected'),
            self::Cancelled => __('Cancelled'),
        };
    }

    /**
     * Get the Flux badge colour representing the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Processing => 'blue',
            self::ReadyForRelease => 'teal',
            self::Released => 'green',
            self::Rejected => 'red',
            self::Cancelled => 'zinc',
        };
    }

    /**
     * Get the statuses this status may legally move to.
     *
     * This is the authoritative state machine for a document request. The
     * status modal, the policy, and every action consult it, so an illegal
     * transition cannot be reached from the UI or from a crafted payload.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Processing, self::Rejected, self::Cancelled],
            self::Processing => [self::ReadyForRelease, self::Rejected, self::Cancelled],
            self::ReadyForRelease => [self::Released, self::Cancelled],
            self::Released, self::Rejected, self::Cancelled => [],
        };
    }

    /**
     * Determine whether this status may move to the given status.
     */
    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), strict: true);
    }

    /**
     * Determine whether the request has reached a final state.
     */
    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * Determine whether the requesting student may still cancel the request.
     */
    public function isCancellableByStudent(): bool
    {
        return $this === self::Pending || $this === self::Processing;
    }

    /**
     * Determine whether a claiming appointment may be booked at this status.
     *
     * Booking opens once processing begins so students can plan ahead, while
     * the earliest selectable date still respects the document's processing
     * time. See DocumentRequest::earliestClaimDate().
     */
    public function isBookable(): bool
    {
        return $this === self::Processing || $this === self::ReadyForRelease;
    }

    /**
     * Get the statuses that represent work still in the registrar's queue.
     *
     * @return array<int, self>
     */
    public static function open(): array
    {
        return [self::Pending, self::Processing, self::ReadyForRelease];
    }
}

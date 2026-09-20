<?php

namespace App\Enums;

enum UserStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';

    /**
     * Get the human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending approval'),
            self::Active => __('Active'),
            self::Inactive => __('Inactive'),
            self::Suspended => __('Suspended'),
        };
    }

    /**
     * Get the Flux badge colour representing the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Active => 'green',
            self::Inactive => 'zinc',
            self::Suspended => 'red',
        };
    }

    /**
     * Determine whether an account with this status may sign in.
     */
    public function canSignIn(): bool
    {
        return $this === self::Active;
    }

    /**
     * Determine whether the account is waiting on the registrar's review.
     */
    public function awaitsApproval(): bool
    {
        return $this === self::Pending;
    }

    /**
     * Get the message shown when an account with this status tries to sign in.
     *
     * A pending account is told to wait rather than to seek help, because
     * nothing is wrong with it - the registrar simply has not reviewed it yet.
     */
    public function signInMessage(): string
    {
        return match ($this) {
            self::Pending => __('Your account is waiting for the registrar to approve it. You will be emailed once it has been reviewed.'),
            default => __('Your account is not active. Please contact the registrar\'s office.'),
        };
    }
}

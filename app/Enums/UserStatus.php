<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';

    /**
     * Get the human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
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
}

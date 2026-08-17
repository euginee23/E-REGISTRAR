<?php

namespace App\Exceptions;

use RuntimeException;

class SlotNotBookableException extends RuntimeException
{
    /**
     * Create an exception for a slot that is closed or already in the past.
     */
    public static function make(): self
    {
        return new self(__('That time slot is no longer available for booking.'));
    }
}

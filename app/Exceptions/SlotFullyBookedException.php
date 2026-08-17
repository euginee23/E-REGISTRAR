<?php

namespace App\Exceptions;

use RuntimeException;

class SlotFullyBookedException extends RuntimeException
{
    /**
     * Create an exception for a slot whose seats have all been taken.
     */
    public static function make(): self
    {
        return new self(__('That time slot has just been fully booked. Please choose another.'));
    }
}

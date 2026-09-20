<?php

namespace App\Exceptions;

use RuntimeException;

class StudentRegistryMismatchException extends RuntimeException
{
    /**
     * Create an exception for a student number the school has no record of.
     */
    public static function unknownNumber(): self
    {
        return new self(__('That student number is not on the registrar\'s records. Please check your student ID, or visit the registrar\'s office.'));
    }

    /**
     * Create an exception for a number whose name does not match the roster.
     *
     * The message deliberately does not reveal the name the roster holds,
     * which would turn a rejection into a way of reading other people's
     * records out of the registry.
     */
    public static function nameMismatch(): self
    {
        return new self(__('The name given does not match the registrar\'s record for that student number.'));
    }

    /**
     * Create an exception for a number that already has an account.
     */
    public static function alreadyClaimed(): self
    {
        return new self(__('An account has already been registered with that student number. Please log in instead, or contact the registrar\'s office.'));
    }
}

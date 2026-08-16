<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case Enrolled = 'enrolled';
    case Alumnus = 'alumnus';

    /**
     * Get the human-readable label for the enrollment status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Enrolled => __('Currently enrolled'),
            self::Alumnus => __('Alumnus'),
        };
    }

    /**
     * Get the Flux badge colour representing the enrollment status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Enrolled => 'green',
            self::Alumnus => 'amber',
        };
    }

    /**
     * Determine whether a graduation year is expected for this status.
     */
    public function requiresYearGraduated(): bool
    {
        return $this === self::Alumnus;
    }
}

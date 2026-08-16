<?php

namespace App\Enums;

enum UserRole: string
{
    case Administrator = 'administrator';
    case RegistrarStaff = 'registrar_staff';
    case Student = 'student';

    /**
     * Get the human-readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Administrator => __('Administrator'),
            self::RegistrarStaff => __('Registrar Staff'),
            self::Student => __('Student / Alumni'),
        };
    }

    /**
     * Get the Flux badge colour representing the role.
     */
    public function color(): string
    {
        return match ($this) {
            self::Administrator => 'purple',
            self::RegistrarStaff => 'blue',
            self::Student => 'zinc',
        };
    }

    /**
     * Determine whether the role works inside the registrar's office.
     */
    public function isStaff(): bool
    {
        return $this === self::Administrator || $this === self::RegistrarStaff;
    }

    /**
     * Get the roles an administrator may assign when creating an account.
     *
     * @return array<int, self>
     */
    public static function assignable(): array
    {
        return [self::Administrator, self::RegistrarStaff, self::Student];
    }
}

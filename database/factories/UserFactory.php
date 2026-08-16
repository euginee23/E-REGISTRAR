<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::Student,
            'status' => UserStatus::Active,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user administers the system.
     */
    public function administrator(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Administrator,
        ]);
    }

    /**
     * Indicate that the user is a member of the registrar's staff.
     */
    public function registrarStaff(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::RegistrarStaff,
        ]);
    }

    /**
     * Indicate that the user is a student, complete with a student profile.
     */
    public function student(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Student,
        ])->has(Student::factory());
    }

    /**
     * Indicate that the user is an alumnus, complete with a student profile.
     */
    public function alumnus(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Student,
        ])->has(Student::factory()->alumnus());
    }

    /**
     * Indicate that the account has been suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::Suspended,
        ]);
    }

    /**
     * Indicate that the account has been deactivated.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::Inactive,
        ]);
    }
}

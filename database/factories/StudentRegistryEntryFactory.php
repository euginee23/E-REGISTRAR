<?php

namespace Database\Factories;

use App\Models\StudentRegistryEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentRegistryEntry>
 */
class StudentRegistryEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_number' => fake()->unique()->numerify('20##-#####'),
            'name' => fake()->name(),
            'course' => 'BS '.fake()->randomElement(['Information Technology', 'Nursing', 'Education', 'Accountancy']),
            'year_graduated' => null,
            'claimed_by_user_id' => null,
            'claimed_at' => null,
        ];
    }

    /**
     * Indicate that an account has already been opened against the entry.
     */
    public function claimed(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'claimed_by_user_id' => $user !== null ? $user->id : User::factory(),
            'claimed_at' => CarbonImmutable::now(),
        ]);
    }
}

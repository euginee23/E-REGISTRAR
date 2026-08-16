<?php

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'student_number' => fake()->unique()->numerify('####-#####'),
            'course' => fake()->randomElement([
                'BS Information Technology',
                'BS Computer Science',
                'BS Business Administration',
                'BS Education',
                'BS Criminology',
            ]),
            'enrollment_status' => EnrollmentStatus::Enrolled,
            'year_graduated' => null,
            'contact_number' => fake()->numerify('09#########'),
        ];
    }

    /**
     * Indicate that the student has already graduated.
     */
    public function alumnus(): static
    {
        return $this->state(fn (array $attributes) => [
            'enrollment_status' => EnrollmentStatus::Alumnus,
            'year_graduated' => fake()->numberBetween(2010, (int) date('Y') - 1),
        ]);
    }
}

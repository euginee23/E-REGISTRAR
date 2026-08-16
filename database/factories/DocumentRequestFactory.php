<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentRequest>
 */
class DocumentRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The reference number is intentionally omitted; the observer on
     * DocumentRequest assigns it so factories and the application share one
     * generator.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'document_type_id' => DocumentType::factory(),
            'other_document_name' => null,
            'purpose' => fake()->randomElement([
                'Employment requirement',
                'Transfer to another school',
                'Board examination application',
                'Scholarship application',
                'Further studies',
            ]),
            'copies' => fake()->numberBetween(1, 3),
            'status' => RequestStatus::Pending,
        ];
    }

    /**
     * Indicate that the request is awaiting review.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RequestStatus::Pending,
        ]);
    }

    /**
     * Indicate that the request has been approved and is being processed.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RequestStatus::Processing,
            'processed_by_user_id' => User::factory()->registrarStaff(),
        ]);
    }

    /**
     * Indicate that the document is ready to be claimed.
     */
    public function readyForRelease(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RequestStatus::ReadyForRelease,
            'processed_by_user_id' => User::factory()->registrarStaff(),
            'ready_at' => now(),
        ]);
    }

    /**
     * Indicate that the document has been handed over to the student.
     */
    public function released(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RequestStatus::Released,
            'processed_by_user_id' => User::factory()->registrarStaff(),
            'ready_at' => now()->subDay(),
            'released_at' => now(),
        ]);
    }

    /**
     * Indicate that the request was turned down.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RequestStatus::Rejected,
            'processed_by_user_id' => User::factory()->registrarStaff(),
            'remarks' => fake()->sentence(),
        ]);
    }

    /**
     * Indicate that the student withdrew the request.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RequestStatus::Cancelled,
        ]);
    }
}

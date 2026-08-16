<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\DocumentRequest;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_request_id' => DocumentRequest::factory()->processing(),
            'time_slot_id' => TimeSlot::factory(),
            'status' => AppointmentStatus::Scheduled,
        ];
    }

    /**
     * Indicate that the registrar has confirmed the appointment.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Confirmed,
            'confirmed_by_user_id' => User::factory()->registrarStaff(),
            'confirmed_at' => now(),
        ]);
    }

    /**
     * Indicate that the student attended and claimed the document.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the appointment was called off.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => fake()->sentence(),
        ]);
    }

    /**
     * Indicate that the student did not turn up.
     */
    public function noShow(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::NoShow,
        ]);
    }
}

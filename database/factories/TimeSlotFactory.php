<?php

namespace Database\Factories;

use App\Models\TimeSlot;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeSlot>
 */
class TimeSlotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startHour = fake()->numberBetween(8, 16);

        return [
            'slot_date' => CarbonImmutable::now()->addWeekday(),
            'start_time' => sprintf('%02d:00:00', $startHour),
            'end_time' => sprintf('%02d:00:00', $startHour + 1),
            'capacity' => (int) config('registrar.office.default_capacity'),
            'booked_count' => 0,
            'is_active' => true,
        ];
    }

    /**
     * Place the slot on a specific date.
     */
    public function onDate(CarbonImmutable $date): static
    {
        return $this->state(fn (array $attributes) => [
            'slot_date' => $date,
        ]);
    }

    /**
     * Start the slot at a specific hour of the day.
     */
    public function startingAt(int $hour): static
    {
        return $this->state(fn (array $attributes) => [
            'start_time' => sprintf('%02d:00:00', $hour),
            'end_time' => sprintf('%02d:00:00', $hour + 1),
        ]);
    }

    /**
     * Indicate that every seat in the slot is taken.
     */
    public function full(): static
    {
        return $this->state(fn (array $attributes) => [
            'booked_count' => $attributes['capacity'] ?? (int) config('registrar.office.default_capacity'),
        ]);
    }

    /**
     * Indicate that the slot has already passed.
     */
    public function past(): static
    {
        return $this->state(fn (array $attributes) => [
            'slot_date' => CarbonImmutable::now()->subWeek(),
        ]);
    }

    /**
     * Indicate that the slot has been closed by the registrar.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}

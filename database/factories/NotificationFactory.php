<?php

namespace Database\Factories;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
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
            'type' => fake()->randomElement(NotificationType::cases()),
            'message' => fake()->sentence(),
            'url' => null,
            'is_read' => false,
        ];
    }

    /**
     * Indicate that the recipient has already opened the notification.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => true,
        ]);
    }

    /**
     * Set the notification's type.
     */
    public function ofType(NotificationType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
        ]);
    }
}

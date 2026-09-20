<?php

namespace Database\Factories;

use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DocumentType>
 */
class DocumentTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->word()).' Certificate';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'processing_days' => fake()->numberBetween(1, 7),
            // Free by default, so the payment gate never surprises a test
            // that is not about payment.
            'fee' => 0,
            'requires_payment' => false,
            'requires_custom_name' => false,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the document must be paid for before it is processed.
     */
    public function chargeable(float $fee = 150): static
    {
        return $this->state(fn (array $attributes) => [
            'fee' => $fee,
            'requires_payment' => true,
        ]);
    }

    /**
     * Indicate that the type captures a free-text document name.
     */
    public function customNamed(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_custom_name' => true,
        ]);
    }

    /**
     * Indicate that the type is retired and can no longer be requested.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}

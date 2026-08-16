<?php

namespace Database\Factories;

use App\Models\DocumentRequest;
use App\Models\RequestAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RequestAttachment>
 */
class RequestAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_request_id' => DocumentRequest::factory(),
            'disk' => (string) config('registrar.attachments.disk'),
            'path' => 'requests/'.fake()->numberBetween(1, 500).'/'.Str::random(40).'.pdf',
            'original_name' => fake()->randomElement([
                'valid-id.pdf',
                'authorization-letter.pdf',
                'clearance.pdf',
            ]),
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(20_000, 2_000_000),
        ];
    }

    /**
     * Indicate that the uploaded requirement is a photograph.
     */
    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'path' => 'requests/'.fake()->numberBetween(1, 500).'/'.Str::random(40).'.jpg',
            'original_name' => 'valid-id.jpg',
            'mime_type' => 'image/jpeg',
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use App\Models\RequestStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestStatusHistory>
 */
class RequestStatusHistoryFactory extends Factory
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
            'from_status' => null,
            'to_status' => RequestStatus::Pending,
            'changed_by_user_id' => null,
            'remarks' => null,
        ];
    }

    /**
     * Record a move between two specific statuses.
     */
    public function transition(?RequestStatus $from, RequestStatus $to): static
    {
        return $this->state(fn (array $attributes) => [
            'from_status' => $from,
            'to_status' => $to,
        ]);
    }
}

<?php

namespace App\Enums;

enum PaymentTiming: string
{
    case BeforeProcessing = 'before_processing';
    case OnRelease = 'on_release';

    /**
     * Get the human-readable label for the payment timing.
     */
    public function label(): string
    {
        return match ($this) {
            self::BeforeProcessing => __('Before processing'),
            self::OnRelease => __('On release'),
        };
    }

    /**
     * Get a longer explanation of the payment timing for the registrar.
     */
    public function description(): string
    {
        return match ($this) {
            self::BeforeProcessing => __('The registrar records the cashier\'s receipt before the request can be started.'),
            self::OnRelease => __('The student pays on the day they claim; the document is not released until payment is recorded.'),
        };
    }

    /**
     * Get the status an unpaid request is not allowed to move into.
     *
     * This is the one place the two timings differ: the fee either has to be
     * in before work starts, or before the document is handed over.
     */
    public function gatedStatus(): RequestStatus
    {
        return match ($this) {
            self::BeforeProcessing => RequestStatus::Processing,
            self::OnRelease => RequestStatus::Released,
        };
    }
}

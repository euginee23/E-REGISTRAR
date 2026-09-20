<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case NotRequired = 'not_required';
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Waived = 'waived';

    /**
     * Get the human-readable label for the payment status.
     */
    public function label(): string
    {
        return match ($this) {
            self::NotRequired => __('No fee'),
            self::Unpaid => __('Awaiting payment'),
            self::Paid => __('Paid'),
            self::Waived => __('Waived'),
        };
    }

    /**
     * Get the Flux badge colour representing the payment status.
     */
    public function color(): string
    {
        return match ($this) {
            self::NotRequired => 'zinc',
            self::Unpaid => 'amber',
            self::Paid => 'green',
            self::Waived => 'blue',
        };
    }

    /**
     * Determine whether the money side of the request is settled.
     *
     * Everything except an outstanding balance counts as settled: a free
     * document has nothing to collect, and a waived fee was settled by the
     * registrar deciding not to charge it.
     */
    public function isSettled(): bool
    {
        return $this !== self::Unpaid;
    }

    /**
     * Determine whether a payment is still owed before the office may start.
     */
    public function isOutstanding(): bool
    {
        return $this === self::Unpaid;
    }
}

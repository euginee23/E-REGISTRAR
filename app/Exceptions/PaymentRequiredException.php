<?php

namespace App\Exceptions;

use App\Models\DocumentRequest;
use RuntimeException;

class PaymentRequiredException extends RuntimeException
{
    /**
     * Create an exception for a request whose fee has not reached the cashier.
     *
     * The message names the amount and the reference so the person at the
     * desk can act on it, rather than being told only that they may not.
     */
    public static function make(DocumentRequest $documentRequest): self
    {
        return new self(__('Record the :amount payment for :reference before processing it.', [
            'amount' => '₱'.number_format((float) $documentRequest->fee_amount, 2),
            'reference' => $documentRequest->reference_no,
        ]));
    }
}

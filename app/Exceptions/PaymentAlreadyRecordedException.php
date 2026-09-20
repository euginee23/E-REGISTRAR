<?php

namespace App\Exceptions;

use App\Models\DocumentRequest;
use RuntimeException;

class PaymentAlreadyRecordedException extends RuntimeException
{
    /**
     * Create an exception for a request whose fee is already settled.
     */
    public static function make(DocumentRequest $documentRequest): self
    {
        return new self(__('The payment for :reference has already been recorded.', [
            'reference' => $documentRequest->reference_no,
        ]));
    }
}

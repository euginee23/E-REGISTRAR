<?php

namespace App\Actions\Requests;

use App\Actions\Notifications\SendNotification;
use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentAlreadyRecordedException;
use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\UnauthorizedException;

class RecordPayment
{
    public function __construct(private SendNotification $sendNotification) {}

    /**
     * Record an over-the-counter payment against a request.
     *
     * The office collects money at the cashier, not here, so this is the act
     * of writing down what was already handed over. Waiving instead records
     * that the registrar decided not to charge, which frees the request to be
     * processed without inventing a receipt number for it.
     *
     * @throws PaymentAlreadyRecordedException
     */
    public function __invoke(
        DocumentRequest $documentRequest,
        User $actor,
        float $amount,
        ?string $orNumber = null,
        bool $waive = false,
    ): DocumentRequest {
        if (! $actor->can('recordPayment', $documentRequest)) {
            throw new UnauthorizedException(
                "Cannot record a payment against request {$documentRequest->reference_no}.",
            );
        }

        if ($documentRequest->isPaymentSettled()) {
            throw PaymentAlreadyRecordedException::make($documentRequest);
        }

        DB::transaction(function () use ($documentRequest, $actor, $amount, $orNumber, $waive): void {
            $documentRequest->forceFill([
                'payment_status' => $waive ? PaymentStatus::Waived : PaymentStatus::Paid,
                'amount_paid' => $waive ? 0 : $amount,
                'or_number' => $waive ? null : $orNumber,
                'paid_at' => now(),
                'recorded_by_user_id' => $actor->id,
            ])->save();
        });

        $this->notify($documentRequest, $waive);

        return $documentRequest;
    }

    /**
     * Tell the student the money side of their request is settled.
     */
    private function notify(DocumentRequest $documentRequest, bool $waived): void
    {
        $message = $waived
            ? __('The fee for :document (:reference) has been waived. Your request will now be processed.', [
                'document' => $documentRequest->display_name,
                'reference' => $documentRequest->reference_no,
            ])
            : __('Your payment of :amount for :document (:reference) has been recorded. Your request will now be processed.', [
                'amount' => '₱'.number_format((float) $documentRequest->amount_paid, 2),
                'document' => $documentRequest->display_name,
                'reference' => $documentRequest->reference_no,
            ]);

        ($this->sendNotification)(
            $documentRequest->student->user,
            NotificationType::PaymentRecorded,
            $message,
            route('student.requests.show', $documentRequest),
        );
    }
}

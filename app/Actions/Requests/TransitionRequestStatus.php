<?php

namespace App\Actions\Requests;

use App\Actions\Notifications\SendNotification;
use App\Enums\NotificationType;
use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use App\Models\RequestStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\UnauthorizedException;

class TransitionRequestStatus
{
    public function __construct(private SendNotification $sendNotification) {}

    /**
     * Move a document request to a new status.
     *
     * Every status change in the system funnels through here - staff
     * processing, student cancellation, and any future console command - so
     * the audit trail and the resulting notification can never be skipped.
     */
    public function __invoke(
        DocumentRequest $documentRequest,
        RequestStatus $to,
        User $actor,
        ?string $remarks = null,
    ): DocumentRequest {
        if (! $actor->can('transition', [$documentRequest, $to])) {
            throw new UnauthorizedException(
                "Cannot move request {$documentRequest->reference_no} to {$to->value}.",
            );
        }

        $from = $documentRequest->status;

        DB::transaction(function () use ($documentRequest, $from, $to, $actor, $remarks): void {
            $documentRequest->status = $to;
            $documentRequest->remarks = $remarks ?? $documentRequest->remarks;

            if ($actor->isStaff()) {
                $documentRequest->processed_by_user_id = $actor->id;
            }

            if ($to === RequestStatus::ReadyForRelease) {
                $documentRequest->ready_at = now();
            }

            if ($to === RequestStatus::Released) {
                $documentRequest->released_at = now();
            }

            $documentRequest->save();

            RequestStatusHistory::create([
                'document_request_id' => $documentRequest->id,
                'from_status' => $from,
                'to_status' => $to,
                'changed_by_user_id' => $actor->id,
                'remarks' => $remarks,
            ]);
        });

        $this->notifyStudent($documentRequest, $to, $actor);

        return $documentRequest;
    }

    /**
     * Tell the requesting student their request has moved on.
     *
     * A student cancelling their own request is not told about it - they just
     * did it themselves.
     */
    private function notifyStudent(DocumentRequest $documentRequest, RequestStatus $to, User $actor): void
    {
        $student = $documentRequest->student->user;

        if ($student->is($actor)) {
            return;
        }

        ($this->sendNotification)(
            $student,
            $to === RequestStatus::Rejected
                ? NotificationType::RequestRejected
                : NotificationType::RequestStatusChanged,
            $this->messageFor($documentRequest, $to),
            route('student.requests.show', $documentRequest),
        );
    }

    /**
     * Build the notification wording for a status change.
     */
    private function messageFor(DocumentRequest $documentRequest, RequestStatus $to): string
    {
        $reference = $documentRequest->reference_no;
        $document = $documentRequest->display_name;

        return match ($to) {
            RequestStatus::Processing => __('Your request :reference for :document has been approved and is now being processed.', [
                'reference' => $reference,
                'document' => $document,
            ]),
            RequestStatus::ReadyForRelease => __('Your :document (:reference) is ready for release. Book an appointment to claim it.', [
                'reference' => $reference,
                'document' => $document,
            ]),
            RequestStatus::Released => __('Your :document (:reference) has been released. Thank you.', [
                'reference' => $reference,
                'document' => $document,
            ]),
            RequestStatus::Rejected => __('Your request :reference for :document was not approved. Please check the remarks.', [
                'reference' => $reference,
                'document' => $document,
            ]),
            default => __('Your request :reference is now :status.', [
                'reference' => $reference,
                'status' => $to->label(),
            ]),
        };
    }
}

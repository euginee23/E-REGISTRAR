<?php

namespace App\Policies;

use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use App\Models\User;

class DocumentRequestPolicy
{
    /**
     * Determine whether the user may list requests.
     *
     * Every screen scopes its own query, so students see only their own.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user may view the request.
     */
    public function view(User $user, DocumentRequest $documentRequest): bool
    {
        return $user->isStaff() || $this->owns($user, $documentRequest);
    }

    /**
     * Determine whether the user may submit a new request.
     *
     * Requests hang off a student profile, so staff accounts - which have
     * none - cannot submit on their own behalf.
     */
    public function create(User $user): bool
    {
        return $user->isStudent() && $user->student !== null;
    }

    /**
     * Determine whether the user may edit the request's registrar-side fields.
     */
    public function update(User $user, DocumentRequest $documentRequest): bool
    {
        return $user->isStaff();
    }

    /**
     * Determine whether the user may move the request to the given status.
     *
     * The enum owns the state machine, so an illegal move is refused here no
     * matter which screen or command asked for it.
     */
    public function transition(User $user, DocumentRequest $documentRequest, RequestStatus $to): bool
    {
        if (! $documentRequest->status->canTransitionTo($to)) {
            return false;
        }

        if ($to === RequestStatus::Cancelled) {
            return $this->cancel($user, $documentRequest);
        }

        return $user->isStaff();
    }

    /**
     * Determine whether the user may cancel the request.
     */
    public function cancel(User $user, DocumentRequest $documentRequest): bool
    {
        if ($user->isStaff()) {
            return ! $documentRequest->status->isTerminal();
        }

        return $this->owns($user, $documentRequest)
            && $documentRequest->status->isCancellableByStudent();
    }

    /**
     * Determine whether the user may attach supporting requirements.
     *
     * Students may only add files while the request is still untouched;
     * afterwards the registrar is already working from what was submitted.
     */
    public function uploadAttachment(User $user, DocumentRequest $documentRequest): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $this->owns($user, $documentRequest)
            && $documentRequest->status === RequestStatus::Pending;
    }

    /**
     * Determine whether the user may remove a supporting requirement.
     */
    public function deleteAttachment(User $user, DocumentRequest $documentRequest): bool
    {
        return $this->uploadAttachment($user, $documentRequest);
    }

    /**
     * Determine whether the user may book the claiming appointment.
     */
    public function book(User $user, DocumentRequest $documentRequest): bool
    {
        return $this->owns($user, $documentRequest)
            && $documentRequest->status->isBookable()
            && $documentRequest->appointment === null;
    }

    /**
     * Determine whether the request belongs to the user.
     */
    private function owns(User $user, DocumentRequest $documentRequest): bool
    {
        return $user->student !== null
            && $user->student->id === $documentRequest->student_id;
    }
}

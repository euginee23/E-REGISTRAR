<?php

namespace App\Policies;

use App\Models\DocumentType;
use App\Models\User;

class DocumentTypePolicy
{
    /**
     * Determine whether the user may list document types.
     *
     * Students need the list in order to choose what to request.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user may view the document type.
     */
    public function view(User $user, DocumentType $documentType): bool
    {
        return true;
    }

    /**
     * Determine whether the user may define a new document type.
     *
     * Defining what the office issues is an administrator decision.
     */
    public function create(User $user): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user may edit the document type.
     */
    public function update(User $user, DocumentType $documentType): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user may retire the document type.
     *
     * Types with history are deactivated rather than deleted; the foreign key
     * is restricted so a hard delete would fail anyway.
     */
    public function delete(User $user, DocumentType $documentType): bool
    {
        return $user->isAdministrator() && $documentType->documentRequests()->doesntExist();
    }
}

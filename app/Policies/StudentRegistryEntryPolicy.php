<?php

namespace App\Policies;

use App\Models\StudentRegistryEntry;
use App\Models\User;

class StudentRegistryEntryPolicy
{
    /**
     * Determine whether the user may browse the roster.
     *
     * The roster carries the details registration is checked against, so it
     * is administrator territory rather than something the desk edits.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user may view the roster entry.
     */
    public function view(User $user, StudentRegistryEntry $entry): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user may add a student to the roster.
     */
    public function create(User $user): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user may correct a roster entry.
     */
    public function update(User $user, StudentRegistryEntry $entry): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user may remove a student from the roster.
     *
     * A claimed entry is the audit trail for an existing account, so it is
     * kept; freeing it is the job of rejecting that account.
     */
    public function delete(User $user, StudentRegistryEntry $entry): bool
    {
        return $user->isAdministrator() && ! $entry->isClaimed();
    }
}

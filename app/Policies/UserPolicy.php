<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user may manage accounts.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user may view the account.
     */
    public function view(User $user, User $model): bool
    {
        return $user->isAdministrator() || $user->is($model);
    }

    /**
     * Determine whether the user may create accounts.
     *
     * This is the only route to a staff or administrator account; public
     * registration always produces a student.
     */
    public function create(User $user): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user may edit the account.
     */
    public function update(User $user, User $model): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user may deactivate or suspend the account.
     *
     * Administrators cannot lock themselves out, and the last remaining
     * active administrator cannot be disabled at all.
     */
    public function changeStatus(User $user, User $model): bool
    {
        if (! $user->isAdministrator() || $user->is($model)) {
            return false;
        }

        // An account still awaiting review is let in by approving it, not by
        // flipping its status here: going through this path would skip the
        // reviewer record and the email telling the student they may log in.
        if ($model->status === UserStatus::Pending) {
            return false;
        }

        return ! $this->isLastActiveAdministrator($model);
    }

    /**
     * Determine whether the user may change the account's role.
     */
    public function changeRole(User $user, User $model): bool
    {
        if (! $user->isAdministrator() || $user->is($model)) {
            return false;
        }

        return ! $this->isLastActiveAdministrator($model);
    }

    /**
     * Determine whether the user may send the account a password reset link.
     *
     * The link only ever goes to the account's own address, so this hands
     * over no access - but it is still administrator-only, because sending it
     * on request is how someone with counter access would start an
     * impersonation attempt.
     */
    public function resetPassword(User $user, User $model): bool
    {
        return $user->isAdministrator() && ! $user->is($model);
    }

    /**
     * Determine whether the user may let a pending account in.
     *
     * Reviewing registrations is desk work rather than an administrator's
     * job, so registrar staff may do it - but only for an account that is
     * actually waiting, which keeps this away from existing accounts.
     */
    public function approve(User $user, User $model): bool
    {
        return $user->isStaff()
            && ! $user->is($model)
            && $model->status === UserStatus::Pending;
    }

    /**
     * Determine whether the user may turn away a pending account.
     */
    public function reject(User $user, User $model): bool
    {
        return $this->approve($user, $model);
    }

    /**
     * Determine whether the user may delete the account.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->isAdministrator()
            && ! $user->is($model)
            && ! $this->isLastActiveAdministrator($model);
    }

    /**
     * Determine whether the account is the only active administrator left.
     */
    private function isLastActiveAdministrator(User $model): bool
    {
        if (! $model->isAdministrator() || $model->status !== UserStatus::Active) {
            return false;
        }

        return User::query()
            ->where('role', UserRole::Administrator)
            ->where('status', UserStatus::Active)
            ->whereKeyNot($model->getKey())
            ->doesntExist();
    }
}

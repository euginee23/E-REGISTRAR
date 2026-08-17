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

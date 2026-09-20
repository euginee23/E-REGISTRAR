<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\UnauthorizedException;

class SendPasswordResetLink
{
    /**
     * Email a password reset link to an account on its owner's behalf.
     *
     * A student at the counter who cannot get into their mailbox, or who
     * never received the self-service link, is the usual case. The link goes
     * through the same broker Fortify uses, so it lands on the existing reset
     * screen and obeys the same expiry and throttling - an administrator
     * cannot use this to set a password themselves.
     *
     * @return string The broker status, e.g. Password::RESET_LINK_SENT.
     */
    public function __invoke(User $user, User $actor): string
    {
        if (! $actor->can('resetPassword', $user)) {
            throw new UnauthorizedException("Cannot send a reset link to {$user->email}.");
        }

        return Password::broker(config('fortify.passwords'))
            ->sendResetLink(['email' => $user->email]);
    }
}

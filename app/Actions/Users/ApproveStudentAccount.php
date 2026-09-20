<?php

namespace App\Actions\Users;

use App\Enums\UserStatus;
use App\Mail\AccountApprovedMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\UnauthorizedException;

class ApproveStudentAccount
{
    /**
     * Let a registered account into the system.
     *
     * The address is marked verified here because the reviewer has just
     * vouched for the account against the registrar's own roster, matching
     * how an administrator-created account is treated.
     *
     * The email is sent whatever the notification settings say: a pending
     * user cannot sign in to read an in-app notice, so this mail is the only
     * way they learn they may now log in.
     */
    public function __invoke(User $user, User $actor): User
    {
        if (! $actor->can('approve', $user)) {
            throw new UnauthorizedException("Cannot approve account {$user->email}.");
        }

        $user->forceFill([
            'status' => UserStatus::Active,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'approved_by_user_id' => $actor->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ])->save();

        Mail::to($user->email)->queue(new AccountApprovedMail($user));

        return $user;
    }
}

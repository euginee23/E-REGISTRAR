<?php

namespace App\Actions\Users;

use App\Enums\UserStatus;
use App\Mail\AccountRejectedMail;
use App\Models\StudentRegistryEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\UnauthorizedException;

class RejectStudentAccount
{
    /**
     * Turn away a registered account, keeping the record of why.
     *
     * The roster entry the registration claimed is released in the same
     * transaction. Without that, a student wrongly turned away - a mistyped
     * name, say - could never register again, because their own student
     * number would stay locked to the rejected account.
     */
    public function __invoke(User $user, User $actor, string $reason): User
    {
        if (! $actor->can('reject', $user)) {
            throw new UnauthorizedException("Cannot reject account {$user->email}.");
        }

        DB::transaction(function () use ($user, $actor, $reason): void {
            $user->forceFill([
                'status' => UserStatus::Inactive,
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            StudentRegistryEntry::query()
                ->where('claimed_by_user_id', $user->id)
                ->update([
                    'claimed_by_user_id' => null,
                    'claimed_at' => null,
                ]);
        });

        $user->unsetRelation('registryEntry');

        Mail::to($user->email)->queue(new AccountRejectedMail($user, $reason));

        return $user;
    }
}

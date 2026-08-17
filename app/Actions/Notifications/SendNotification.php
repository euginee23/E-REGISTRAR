<?php

namespace App\Actions\Notifications;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Collection;

class SendNotification
{
    /**
     * Record an in-app notification for one or more recipients.
     *
     * Notifications are stored rather than sent: the system is deliberately
     * in-app only, so there is no mail or queue involved.
     *
     * @param  User|iterable<int, User>  $recipients
     * @return Collection<int, Notification>
     */
    public function __invoke(
        User|iterable $recipients,
        NotificationType $type,
        string $message,
        ?string $url = null,
    ): Collection {
        $recipients = $recipients instanceof User ? [$recipients] : $recipients;

        return Collection::make($recipients)->map(
            fn (User $recipient): Notification => Notification::create([
                'user_id' => $recipient->id,
                'type' => $type,
                'message' => $message,
                'url' => $url,
            ]),
        );
    }
}

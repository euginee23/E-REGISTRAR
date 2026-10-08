<?php

namespace App\Actions\Notifications;

use App\Actions\Mail\DeliverMail;
use App\Enums\NotificationType;
use App\Mail\RegistrarNotificationMail;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Collection;

class SendNotification
{
    public function __construct(private DeliverMail $deliverMail) {}

    /**
     * Notify one or more recipients, in the app and by email.
     *
     * The stored notification is the record of truth: it is written first,
     * so the bell is correct even when mail is switched off or the mail
     * server is unreachable. The email mirrors it.
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
        // Materialised once: the recipients may arrive as a generator, which
        // would be exhausted by the first pass and leave the mail pass with
        // nothing to send.
        $recipients = Collection::make($recipients instanceof User ? [$recipients] : $recipients);

        $notifications = $recipients->map(
            fn (User $recipient): Notification => Notification::create([
                'user_id' => $recipient->id,
                'type' => $type,
                'message' => $message,
                'url' => $url,
            ]),
        );

        $this->mail($recipients, $type, $message, $url);

        return $notifications;
    }

    /**
     * Send the email mirroring the in-app notification.
     *
     * @param  Collection<int, User>  $recipients
     */
    private function mail(Collection $recipients, NotificationType $type, string $message, ?string $url): void
    {
        if (! config('registrar.notifications.mail')) {
            return;
        }

        foreach ($recipients as $recipient) {
            ($this->deliverMail)(
                $recipient->email,
                new RegistrarNotificationMail($type, $message, $url, $recipient->name),
            );
        }
    }
}

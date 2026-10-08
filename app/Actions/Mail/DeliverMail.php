<?php

namespace App\Actions\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class DeliverMail
{
    /**
     * Send a mailable through the delivery mode the deployment has chosen.
     *
     * Mail goes out immediately by default, so it leaves even on a server
     * with no queue worker - a queued email with no worker to send it waits
     * in the jobs table forever without a word. A deployment that runs a
     * worker can opt into queueing so a slow mail server never delays the
     * request that triggered the email.
     *
     * A failed immediate send is reported rather than thrown: the email
     * accompanies an action - an approval, a status change - that has
     * already happened, and must not undo or break it.
     *
     * @return bool Whether the email was handed off without error.
     */
    public function __invoke(string $recipient, Mailable $mailable): bool
    {
        if (config('registrar.mail.queued')) {
            Mail::queue($mailable->to($recipient), (string) config('registrar.mail.queue'));

            return true;
        }

        try {
            Mail::to($recipient)->send($mailable);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }

        return true;
    }
}

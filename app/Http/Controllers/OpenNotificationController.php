<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OpenNotificationController extends Controller
{
    /**
     * Mark a notification read and take its recipient to what it is about.
     *
     * Both the bell and the email button link here rather than straight to
     * the destination, so opening a notification from either place clears it.
     * A link opened while signed in to someone else's account is refused, and
     * the 403 page offers to switch accounts and come straight back here.
     */
    public function __invoke(Request $request, Notification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()?->id, 403);

        $notification->markAsRead();

        if ($notification->url === null) {
            return redirect()->route('notifications.index');
        }

        return redirect()->to($this->pathOf($notification->url));
    }

    /**
     * Reduce a stored link to its path and query string.
     *
     * Links are stored as absolute URLs, and one built by a scheduled command
     * carries whatever host APP_URL names. Redirecting by path keeps the
     * visitor on the host they actually came in on, and can never send them
     * off-site.
     */
    private function pathOf(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);

        return $query ? $path.'?'.$query : $path;
    }
}

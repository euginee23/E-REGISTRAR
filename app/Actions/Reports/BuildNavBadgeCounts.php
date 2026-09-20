<?php

namespace App\Actions\Reports;

use App\Enums\RequestStatus;
use App\Models\Appointment;
use App\Models\DocumentRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class BuildNavBadgeCounts
{
    /**
     * Count what the signed-in user's sidebar should draw attention to.
     *
     * Every count is a plain aggregate and the whole payload is cached for a
     * short spell, because the sidebar renders on every page and polls on top
     * of that. A badge that lags by a few seconds costs nothing; a handful of
     * extra queries on every request would.
     *
     * @return array{pendingRequests: int, appointmentsToday: int, pendingAccounts: int, openRequests: int, unreadNotifications: int}
     */
    public function __invoke(User $user): array
    {
        $ttl = (int) config('registrar.nav.badge_ttl');

        return Cache::remember(
            'nav-badges:'.$user->id,
            now()->addSeconds($ttl),
            fn (): array => $user->isStaff()
                ? $this->forStaff($user)
                : $this->forStudent($user),
        );
    }

    /**
     * Count the work waiting on the registrar's desk.
     *
     * @return array{pendingRequests: int, appointmentsToday: int, pendingAccounts: int, openRequests: int, unreadNotifications: int}
     */
    private function forStaff(User $user): array
    {
        return [
            'pendingRequests' => DocumentRequest::query()
                ->where('status', RequestStatus::Pending)
                ->count(),
            'appointmentsToday' => Appointment::query()
                ->onDate(CarbonImmutable::today())
                ->occupying()
                ->count(),
            'pendingAccounts' => User::query()->pending()->count(),
            'openRequests' => 0,
            'unreadNotifications' => $user->unreadNotificationsCount(),
        ];
    }

    /**
     * Count what the student still has in flight.
     *
     * @return array{pendingRequests: int, appointmentsToday: int, pendingAccounts: int, openRequests: int, unreadNotifications: int}
     */
    private function forStudent(User $user): array
    {
        $openRequests = $user->student === null
            ? 0
            : DocumentRequest::query()->forStudent($user->student)->open()->count();

        return [
            'pendingRequests' => 0,
            'appointmentsToday' => 0,
            'pendingAccounts' => 0,
            'openRequests' => $openRequests,
            'unreadNotifications' => $user->unreadNotificationsCount(),
        ];
    }
}

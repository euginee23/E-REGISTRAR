<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudentProfileExists
{
    /**
     * Send students without a profile to fill one in first.
     *
     * Registration always creates a profile, but an administrator can create a
     * student account - or switch an existing account to the student role -
     * without one. Every screen in the student area needs a profile to query
     * against, so redirect instead of letting the pages fail.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null, 403);

        if ($user->student === null) {
            return redirect()->route('student-profile.edit')
                ->with('status', 'student-profile-required');
        }

        return $next($request);
    }
}

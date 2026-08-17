<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Restrict the route to users holding one of the given roles.
     *
     * Administrators are deliberately not granted a blanket pass here. The
     * Gate::before hook covers policy checks, but route access stays explicit
     * so an administrator-only area cannot be reached by a staff member.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_if($user === null, 403);
        abort_unless(in_array($user->role->value, $roles, strict: true), 403);

        return $next($request);
    }
}

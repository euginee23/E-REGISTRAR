<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    /**
     * Turn away users whose account is no longer active.
     *
     * Checking on every request - rather than only at sign-in - means
     * suspending an account takes effect immediately instead of waiting for
     * the offending user's session to expire.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->status->canSignIn()) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => __('Your account is not active. Please contact the registrar\'s office.'),
            ]);
        }

        return $next($request);
    }
}

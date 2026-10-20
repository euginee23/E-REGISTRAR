<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SwitchAccountController extends Controller
{
    /**
     * Sign out and send the visitor to the login page, then back to a link.
     *
     * Used from the 403 page when a link meant for one account - typically an
     * email button - is opened in a browser signed in to another, such as a
     * student's email opened where the registrar is logged in. After signing
     * in as the right account the visitor lands on the link they followed.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $continue = (string) $request->input('continue', '');

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($this->isLocalPath($continue)) {
            $request->session()->put('url.intended', url($continue));
        }

        return redirect()->route('login');
    }

    /**
     * Determine whether the path stays on this site.
     *
     * Only a plain absolute path is accepted, so a crafted form can never use
     * the login redirect to bounce someone to another host.
     */
    private function isLocalPath(string $path): bool
    {
        return Str::startsWith($path, '/')
            && ! Str::startsWith($path, ['//', '/\\']);
    }
}

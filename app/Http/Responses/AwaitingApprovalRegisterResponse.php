<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\RegisterResponse;
use Symfony\Component\HttpFoundation\Response;

class AwaitingApprovalRegisterResponse implements RegisterResponse
{
    /**
     * Send a newly registered user back to the login screen.
     *
     * Fortify signs a new user in as part of registering. A registration now
     * only produces an account waiting for the registrar's review, so the
     * session is discarded immediately and the student is told what happens
     * next, rather than being dropped on a dashboard they cannot use.
     */
    public function toResponse($request): Response
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $message = __('Your account has been created and is waiting for the registrar to approve it. You will be emailed once it has been reviewed.');

        if ($request->wantsJson()) {
            return new JsonResponse(['message' => $message], 201);
        }

        return $this->redirect($message);
    }

    /**
     * Build the redirect back to the login screen.
     */
    private function redirect(string $message): RedirectResponse
    {
        return redirect()->route('login')->with('status', $message);
    }
}

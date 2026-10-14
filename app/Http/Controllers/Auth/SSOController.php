<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\SSOUnavailableException;
use App\Http\Controllers\Controller;
use App\Services\SSOIdentityService;
use App\Services\SSOService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SSOController extends Controller
{
    public function __construct(
        private SSOService $ssoService,
        private SSOIdentityService $identities,
    ) {}

    public function showLogin(Request $request): Response
    {
        return Inertia::render('Auth/Login', [
            'ssoError' => session('sso_error'),
        ]);
    }

    public function redirect(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put('sso_state', $state);

        return redirect()->away($this->ssoService->getLoginUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        // Validate state
        $sessionState = $request->session()->pull('sso_state');
        if (! $sessionState || $sessionState !== $request->query('state')) {
            return redirect()->route('login')
                ->with('sso_error', 'Invalid authentication state. Please try again.');
        }

        $code = $request->query('code');
        $token = is_string($code) ? $this->ssoService->exchangeCode($code) : null;
        if (! $token) {
            return redirect()->route('login')
                ->with('sso_error', 'The authentication code is invalid or expired. Please try again.');
        }

        try {
            $authorization = $this->ssoService->authorize($token);
            $user = $authorization ? $this->identities->synchronize($authorization) : null;
        } catch (SSOUnavailableException $exception) {
            return redirect()->route('login')
                ->with('sso_error', 'SSO is temporarily unavailable. Please try signing in again.');
        }

        if (! $user) {
            return redirect()->route('login')
                ->with('sso_error', 'Your SSO account is not eligible to access this application.');
        }

        // Update last SSO login timestamp
        $user->update(['last_sso_login_at' => now()]);

        // Store JWT in session for logout
        $request->session()->put('sso_token', $token);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        // Best-effort SSO logout
        $ssoToken = $request->session()->get('sso_token');
        if ($ssoToken) {
            $this->ssoService->logout($ssoToken);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

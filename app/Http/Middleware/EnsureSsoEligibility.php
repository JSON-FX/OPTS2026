<?php

namespace App\Http\Middleware;

use App\Exceptions\SSOUnavailableException;
use App\Services\SSOIdentityService;
use App\Services\SSOOfficeService;
use App\Services\SSOService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSsoEligibility
{
    public function __construct(private SSOService $sso, private SSOIdentityService $identities, private SSOOfficeService $offices) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $request->routeIs('logout')) {
            return $next($request);
        }

        $token = $request->session()->get('sso_token');
        try {
            $authorization = is_string($token) && $token !== '' ? $this->sso->authorize($token) : null;
            $identity = null;
            if (($authorization['authorized'] ?? false) === true) {
                if ($this->requiresFreshOfficeSnapshot($request)) {
                    $this->offices->sync();
                } else {
                    $this->offices->refreshIfStale();
                }
                $identity = $this->identities->synchronize($authorization, $user, refreshOffice: false);
            }
        } catch (SSOUnavailableException $exception) {
            abort(503, 'SSO is temporarily unavailable. Please retry.');
        }

        if (! $identity) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('sso_error', 'Your SSO session or application access is no longer valid. Please sign in again.');
        }

        if (! $identity->canProcessTransactions()
            && $this->isBusinessMutation($request)) {
            abort(403, 'An Endorser or Administrator role and an active SSO office assignment are required to manage procurement records.');
        }

        return $next($request);
    }

    private function requiresFreshOfficeSnapshot(Request $request): bool
    {
        return ! $request->isMethodSafe()
            && ($this->isBusinessMutation($request) || $request->routeIs('admin.workflows.store', 'admin.workflows.update'));
    }

    private function isBusinessMutation(Request $request): bool
    {
        return $request->routeIs('procurements.*', 'purchase-requests.*', 'purchase-orders.*', 'vouchers.*', 'transactions.*')
            && (! $request->isMethodSafe() || $request->routeIs('*.create', '*.edit'));
    }
}

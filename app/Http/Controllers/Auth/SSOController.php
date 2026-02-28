<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
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

        $token = $request->query('token');
        if (! $token) {
            return redirect()->route('login')
                ->with('sso_error', 'No authentication token received.');
        }

        // Authorize — check app access and get SSO role
        $authData = $this->ssoService->authorize($token);
        if (! $authData || ! ($authData['authorized'] ?? false)) {
            return redirect()->route('login')
                ->with('sso_error', $authData['message'] ?? 'You are not authorized to access this application.');
        }

        // Extract employee data from authorize response
        $authEmployee = $authData['employee'] ?? [];
        $ssoUuid = $authEmployee['uuid'] ?? null;

        if (! $ssoUuid) {
            return redirect()->route('login')
                ->with('sso_error', 'Failed to verify your identity. Please try again.');
        }

        // Try to get full employee data from validate endpoint (best-effort)
        $employee = $authEmployee;
        $userData = $this->ssoService->validateToken($token);
        if ($userData && isset($userData['data'])) {
            $fullEmployee = $userData['data'];
            $employee = array_merge($employee, [
                'name' => $fullEmployee['name'] ?? $authEmployee['full_name'] ?? $authEmployee['uuid'],
                'email' => $fullEmployee['email'] ?? $authEmployee['email'] ?? null,
                'position' => $fullEmployee['position'] ?? null,
                'office' => $fullEmployee['office'] ?? null,
            ]);
        } else {
            // Fallback to authorize response data
            $employee['name'] = $authEmployee['full_name'] ?? $authEmployee['uuid'];
            $employee['email'] = $authEmployee['email'] ?? null;
            $employee['position'] = null;
            $employee['office'] = null;
        }

        // Find or create user (JIT provisioning)
        $user = User::where('sso_uuid', $ssoUuid)->first();
        $isNewUser = ! $user;

        if ($isNewUser) {
            $user = User::create([
                'sso_uuid' => $ssoUuid,
                'name' => $employee['name'],
                'email' => $employee['email'],
                'sso_position' => $employee['position'] ?? null,
                'office_id' => $this->resolveOfficeId($employee),
                'is_active' => true,
                'password' => null,
            ]);

            // Assign initial role from SSO
            $ssoRole = $authData['role'] ?? 'standard';
            $optsRole = $this->ssoService->mapSsoRoleToOptsRole($ssoRole);
            $user->assignRole($optsRole);
        } else {
            // Sync user data from SSO
            $user->update([
                'name' => $employee['name'],
                'email' => $employee['email'],
                'sso_position' => $employee['position'] ?? null,
            ]);
        }

        // Check if user is active
        if (! $user->is_active) {
            return redirect()->route('login')
                ->with('sso_error', 'Your account has been deactivated. Please contact an administrator.');
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

    private function resolveOfficeId(array $employee): ?int
    {
        if (! isset($employee['office']) || ! isset($employee['office']['name'])) {
            return null;
        }

        $office = \App\Models\Office::where('name', $employee['office']['name'])
            ->orWhere('abbreviation', $employee['office']['abbreviation'] ?? '')
            ->first();

        return $office?->id;
    }
}

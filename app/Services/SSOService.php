<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SSOService
{
    private string $apiUrl;

    private string $uiUrl;

    private string $clientId;

    private string $clientSecret;

    private string $redirectUri;

    public function __construct()
    {
        $this->apiUrl = config('services.lgu_sso.api_url', '');
        $this->uiUrl = config('services.lgu_sso.ui_url', '');
        $this->clientId = config('services.lgu_sso.client_id') ?? '';
        $this->clientSecret = config('services.lgu_sso.client_secret') ?? '';
        $this->redirectUri = config('services.lgu_sso.redirect_uri', '');
    }

    public function getLoginUrl(string $state): string
    {
        return $this->uiUrl.'/sso/login?'.http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'state' => $state,
        ]);
    }

    public function validateToken(string $token): ?array
    {
        try {
            $response = Http::withHeaders([
                'X-Client-ID' => $this->clientId,
                'X-Client-Secret' => $this->clientSecret,
            ])->post($this->apiUrl.'/api/v1/sso/validate', [
                'token' => $token,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('SSO token validation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('SSO token validation error', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function authorize(string $token): ?array
    {
        try {
            $response = Http::withHeaders([
                'X-Client-ID' => $this->clientId,
                'X-Client-Secret' => $this->clientSecret,
            ])->post($this->apiUrl.'/api/v1/sso/authorize', [
                'token' => $token,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('SSO authorization failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('SSO authorization error', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function logout(string $token): bool
    {
        try {
            $response = Http::withToken($token)
                ->post($this->apiUrl.'/api/v1/auth/logout');

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('SSO logout failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function mapSsoRoleToOptsRole(string $ssoRole): string
    {
        return match ($ssoRole) {
            'administrator', 'super_administrator' => 'Administrator',
            default => 'Viewer',
        };
    }
}

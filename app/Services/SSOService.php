<?php

namespace App\Services;

use App\Exceptions\SSOUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SSOService
{
    public const DIRECTORY_ROLES = ['guest', 'standard', 'administrator', 'super_administrator'];

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

    public function exchangeCode(string $code): ?string
    {
        try {
            $response = Http::withHeaders([
                'X-Client-ID' => $this->clientId,
                'X-Client-Secret' => $this->clientSecret,
            ])->post($this->apiUrl.'/api/v1/sso/exchange', [
                'code' => $code,
                'redirect_uri' => $this->redirectUri,
            ]);

            return $response->successful() ? $response->json('access_token') : null;
        } catch (\Exception $e) {
            Log::warning('SSO code exchange failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function authorize(string $token): ?array
    {
        try {
            $response = Http::acceptJson()->connectTimeout(3)->timeout(10)->withHeaders([
                'X-Client-ID' => $this->clientId,
                'X-Client-Secret' => $this->clientSecret,
            ])->post($this->apiUrl.'/api/v1/sso/authorize', ['token' => $token]);
        } catch (ConnectionException $e) {
            Log::warning('SSO upstream request failed.', ['endpoint' => '/sso/authorize', 'status' => 'connection_error']);
            throw new SSOUnavailableException('SSO authorization is unavailable. Please retry.', 0, $e);
        }

        if (! $response->successful()) {
            Log::warning('SSO upstream request failed.', [
                'endpoint' => '/sso/authorize',
                'status' => $response->status(),
                'retry_after' => $response->header('Retry-After'),
            ]);
        }
        if (in_array($response->status(), [401, 403], true)) {
            return null;
        }
        if (! $response->successful() || ! is_array($response->json()) || ! is_bool($response->json('authorized'))) {
            throw new SSOUnavailableException('SSO authorization is unavailable. Please retry.');
        }

        return $response->json();
    }

    public function offices(int $page = 1): array
    {
        return $this->referencePage('/sso/offices', $page);
    }

    public function office(int $id): ?array
    {
        $payload = $this->directoryRequest('/sso/offices/'.$id, [], true);
        if ($payload === null) {
            return null;
        }
        $office = $this->validatedOffice($payload['data'] ?? null);
        if ($office['id'] !== $id) {
            throw new SSOUnavailableException('SSO returned an unexpected office identity.');
        }

        return $office;
    }

    public function directory(array $query = []): array
    {
        $payload = $this->directoryRequest('/sso/directory', [
            ...Arr::only($query, ['search', 'page', 'per_page']),
            'roles' => self::DIRECTORY_ROLES,
        ]);
        $this->validatePage($payload);
        $payload['data'] = array_map(fn ($person) => $this->validatedPerson($person), $payload['data']);

        return Arr::only($payload, ['data', 'meta']);
    }

    public function directoryUser(string $uuid): ?array
    {
        if (! Str::isUuid($uuid)) {
            return null;
        }
        $payload = $this->directoryRequest('/sso/directory/'.$uuid, ['roles' => self::DIRECTORY_ROLES], true);
        if ($payload === null) {
            return null;
        }
        $person = $this->validatedPerson($payload['data'] ?? null);
        if ($person['uuid'] !== $uuid) {
            throw new SSOUnavailableException('SSO returned an unexpected user identity.');
        }

        return $person;
    }

    private function directoryRequest(string $path, array $query = [], bool $allowMissing = false): ?array
    {
        try {
            $response = Http::acceptJson()->connectTimeout(3)->timeout(10)->withHeaders([
                'X-Client-ID' => $this->clientId,
                'X-Client-Secret' => $this->clientSecret,
            ])->get($this->apiUrl.'/api/v1'.$path, $query);
        } catch (ConnectionException $e) {
            Log::warning('SSO upstream request failed.', ['endpoint' => $this->safeEndpoint($path), 'status' => 'connection_error']);
            throw new SSOUnavailableException('SSO directory is unavailable. Please retry.', 0, $e);
        }
        if (! $response->successful()) {
            Log::warning('SSO upstream request failed.', [
                'endpoint' => $this->safeEndpoint($path),
                'status' => $response->status(),
                'retry_after' => $response->header('Retry-After'),
            ]);
        }
        if ($allowMissing && $response->status() === 404) {
            return null;
        }
        if (! $response->successful() || ! is_array($response->json())) {
            throw new SSOUnavailableException('SSO directory is unavailable. Please retry.');
        }

        return $response->json();
    }

    private function safeEndpoint(string $path): string
    {
        return preg_replace('#^(/sso/(?:directory|offices))/[^/]+$#', '$1/{id}', $path);
    }

    private function referencePage(string $path, int $page): array
    {
        $payload = $this->directoryRequest($path, ['page' => $page, 'per_page' => 100]);
        $this->validatePage($payload);
        if ($payload['meta']['page'] !== $page) {
            throw new SSOUnavailableException('SSO returned an unexpected directory page.');
        }
        $payload['data'] = array_map(fn ($office) => $this->validatedOffice($office), $payload['data']);

        return Arr::only($payload, ['data', 'meta']);
    }

    private function validatePage(array $payload): void
    {
        $this->validatePayload($payload, [
            'data' => ['present', 'array'],
            'meta.page' => ['required', 'integer', 'min:1'],
            'meta.per_page' => ['required', 'integer', 'min:1'],
            'meta.has_more' => ['required', 'boolean'],
        ]);
        if (! array_is_list($payload['data']) || ! is_bool($payload['meta']['has_more'])) {
            throw new SSOUnavailableException('SSO returned an invalid directory page.');
        }
    }

    private function validatedOffice(mixed $office): array
    {
        $this->validatePayload($office, [
            'id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'abbreviation' => ['required', 'string', 'max:50'],
            'is_active' => ['required', 'boolean'],
        ]);

        return Arr::only($office, ['id', 'name', 'abbreviation', 'is_active']);
    }

    private function validatedPerson(mixed $person): array
    {
        $rules = [
            'uuid' => ['required', 'uuid'],
            'full_name' => ['required', 'string', 'max:255'],
            'initials' => ['present', 'nullable', 'string'],
            'role' => ['required', 'in:'.implode(',', self::DIRECTORY_ROLES)],
            'office_id' => ['present', 'nullable', 'integer', 'min:1'],
            'office_name' => ['present', 'nullable', 'string'],
            'office_abbreviation' => ['present', 'nullable', 'string'],
            'position_id' => ['present', 'nullable', 'integer', 'min:1'],
            'position_title' => ['present', 'nullable', 'string', 'max:255'],
        ];
        $this->validatePayload($person, $rules);

        return Arr::only($person, array_keys($rules));
    }

    private function validatePayload(mixed $payload, array $rules): void
    {
        if (! is_array($payload) || Validator::make($payload, $rules)->fails()) {
            throw new SSOUnavailableException('SSO returned an invalid directory response.');
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
            'guest' => 'Viewer',
            'standard' => 'Endorser',
            'administrator', 'super_administrator' => 'Administrator',
            default => throw new \InvalidArgumentException('Unknown SSO role.'),
        };
    }
}

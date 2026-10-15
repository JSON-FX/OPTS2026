<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ConnectPilotHost
{
    private const LAUNCH_PATH = '/api/widget/host-launches';

    private const REVOCATION_PATH = '/api/widget/host-revocations';

    private const INTROSPECTION_PATH = '/connect/pilot/introspect';

    public function configured(): bool
    {
        return config('connect_pilot.enabled') === true
            && $this->origin(config('app.url'))
            && $this->origin(config('connect_pilot.connect_origin'))
            && $this->identifier(config('connect_pilot.host_id'))
            && $this->identifier(config('connect_pilot.key_id'))
            && is_string(config('connect_pilot.secret'))
            && strlen(config('connect_pilot.secret')) >= 32
            && is_string(config('services.lgu_sso.client_id'))
            && config('services.lgu_sso.client_id') !== ''
            && is_string(config('services.lgu_sso.client_secret'))
            && config('services.lgu_sso.client_secret') !== ''
            && ! hash_equals(config('services.lgu_sso.client_secret'), config('connect_pilot.secret'))
            && config('session.driver') === 'database'
            && config('session.encrypt') === true
            && config('session.secure') === true
            && config('session.http_only') === true
            && in_array(config('cache.default'), ['database', 'redis'], true);
    }

    /** @return array{launch_ref: string, expires_in: int} */
    public function launch(Request $request, SSOService $sso): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('Connect pilot is disabled.');
        }
        $user = $request->user();
        $token = $request->session()->get('sso_token');
        if (! $user instanceof User || ! $user->is_active || ! $this->uuid($user->sso_uuid)
            || ! is_string($token) || $token === '') {
            $this->revoke($request);
            throw new RuntimeException('Current SSO authorization is required.');
        }
        $status = $this->authorizationStatus($request, $sso, $token, $user->sso_uuid);
        if ($status === 'denied') {
            $this->revoke($request);
        }
        if ($status !== 'granted') {
            throw new RuntimeException('Current SSO authorization is required.');
        }

        $binding = $request->session()->get('connect_pilot_binding');
        if (! $this->reference($binding) || $request->session()->get('connect_pilot_uuid') !== $user->sso_uuid
            || $this->revoked($binding) || ! is_array(Cache::get($this->bindingKey($binding)))) {
            $this->revoke($request);
            $binding = $this->randomReference();
            $request->session()->put('connect_pilot_binding', $binding);
            $request->session()->put('connect_pilot_uuid', $user->sso_uuid);
        }
        // Connect can introspect before this request finishes.
        $request->session()->save();
        if (! DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('id', $request->session()->getId())
            ->where('user_id', $user->id)->exists()) {
            throw new RuntimeException('Host session was not persisted.');
        }
        $ttl = (int) config('session.lifetime') * 60;
        if ($ttl < 1 || ! Cache::put($this->bindingKey($binding), [
            'session_id' => $request->session()->getId(),
            'user_id' => $user->id,
            'employee_uuid' => $user->sso_uuid,
        ], $ttl)) {
            throw new RuntimeException('Host binding was not persisted.');
        }

        $body = json_encode([
            'employee_uuid' => $user->sso_uuid,
            'host_session_ref' => $binding,
            'parent_origin' => config('app.url'),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $response = $this->post(self::LAUNCH_PATH, $body, 8);
        if ($response->status() !== 201 || strlen($response->body()) > 2048) {
            throw new RuntimeException('Connect launch was denied.');
        }
        $data = $response->json();
        if (! is_array($data)
            || ! $this->reference($data['launch_ref'] ?? null)
            || ($data['expires_in'] ?? null) !== 30) {
            throw new RuntimeException('Connect launch was denied.');
        }

        return ['launch_ref' => $data['launch_ref'], 'expires_in' => 30];
    }

    public function revoke(Request $request): void
    {
        $binding = $request->session()->pull('connect_pilot_binding');
        $uuid = $request->session()->pull('connect_pilot_uuid');
        if (! $this->reference($binding)) {
            return;
        }
        try {
            $this->markRevoked($binding);
        } catch (Throwable) {
            Log::warning('Connect pilot durable revocation unavailable.');
        }
        try {
            Cache::forget($this->bindingKey($binding));
        } catch (Throwable) {
            Log::warning('Connect pilot binding cleanup unavailable.');
        }
        if (! $this->configured() || ! $this->uuid($uuid)) {
            return;
        }
        $body = json_encode(['employee_uuid' => $uuid, 'host_session_ref' => $binding], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        try {
            $response = $this->post(self::REVOCATION_PATH, $body, 5);
            if (! $response->ok() || $response->json('status') !== 'revoked') {
                Log::warning('Connect pilot revocation unconfirmed.', ['host_id' => config('connect_pilot.host_id')]);
            }
        } catch (Throwable) {
            Log::warning('Connect pilot revocation unavailable.', ['host_id' => config('connect_pilot.host_id')]);
        }
    }

    /** @return array{active: true, host_id: string, employee_uuid: string, host_session_digest: string} */
    public function introspect(Request $request, SSOService $sso): array
    {
        $body = $request->getContent();
        $timestamp = $request->header('X-Connect-Timestamp');
        $nonce = $request->header('X-Connect-Nonce');
        $signature = $request->header('X-Connect-Signature');
        if (! $this->configured() || $request->getPathInfo() !== self::INTROSPECTION_PATH
            || $request->query() !== [] || strlen($body) > 2048
            || strtolower(trim(explode(';', (string) $request->header('Content-Type'), 2)[0])) !== 'application/json'
            || $request->header('X-Connect-Host') !== config('connect_pilot.host_id')
            || $request->header('X-Connect-Key') !== config('connect_pilot.key_id')
            || ! is_string($timestamp) || ! preg_match('/\A[0-9]{10}\z/', $timestamp)
            || abs(now()->timestamp - (int) $timestamp) > 30
            || ! $this->reference($nonce)
            || ! is_string($signature) || ! preg_match('/\A[a-f0-9]{64}\z/', $signature)
            || ! hash_equals($this->signature(self::INTROSPECTION_PATH, $timestamp, $nonce, $body), $signature)) {
            throw new HttpException(403, 'Invalid Connect introspection signature.');
        }
        if (! Cache::add('connect_pilot:nonce:'.hash('sha256', $nonce), true, 70)) {
            throw new HttpException(403, 'Replayed Connect introspection request.');
        }
        $input = json_decode($body, true);
        $binding = $input['host_session_ref'] ?? null;
        $uuid = $input['employee_uuid'] ?? null;
        if (! is_array($input) || array_is_list($input) || count($input) !== 2
            || ! $this->reference($binding) || ! $this->uuid($uuid)) {
            throw new HttpException(403, 'Invalid Connect introspection body.');
        }
        // Only an authenticated Connect request can spend a binding's budget.
        $limitKey = 'connect_pilot:introspection:'.hash('sha256', config('connect_pilot.host_id').':'.$binding);
        if (RateLimiter::hit($limitKey, 60) > (int) config('connect_pilot.introspection_binding_per_minute', 120)) {
            throw new HttpException(429, 'Host introspection is temporarily rate limited.', headers: [
                'Retry-After' => (string) max(1, RateLimiter::availableIn($limitKey)),
            ]);
        }
        if ($this->revoked($binding)) {
            throw new HttpException(403, 'Host binding was revoked.');
        }
        $record = Cache::get($this->bindingKey($binding));
        if (! is_array($record) || ($record['employee_uuid'] ?? null) !== $uuid
            || ! is_string($record['session_id'] ?? null)
            || ! is_int($record['user_id'] ?? null)) {
            throw new HttpException(403, 'Host session is inactive.');
        }
        $token = $this->sessionToken($record, $binding, $uuid);
        $user = User::find($record['user_id']);
        if (! $user?->is_active || $user->sso_uuid !== $uuid) {
            $this->markRevoked($binding);
            throw new HttpException(403, 'Host user is inactive.');
        }
        $status = $this->authorizationStatus($request, $sso, $token, $uuid);
        if ($status === 'denied') {
            $this->markRevoked($binding);
            try {
                Cache::forget($this->bindingKey($binding));
            } catch (Throwable) {
                Log::warning('Connect pilot binding cleanup unavailable.');
            }
            throw new HttpException(403, 'Host SSO grant is inactive.');
        }

        return [
            'active' => true,
            'host_id' => config('connect_pilot.host_id'),
            'employee_uuid' => $uuid,
            'host_session_digest' => hash('sha256', $binding),
        ];
    }

    /** @return 'granted'|'denied' */
    private function authorizationStatus(Request $request, SSOService $sso, string $token, string $uuid): string
    {
        $data = $sso->authorizeForRequest($request, $token);

        return ($data['authorized'] ?? false) === true
            && ($data['employee']['uuid'] ?? null) === $uuid ? 'granted' : 'denied';
    }

    private function sessionToken(array $record, string $binding, string $uuid): string
    {
        $session = DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
            ->where('id', $record['session_id'])->where('user_id', $record['user_id'])
            ->where('last_activity', '>=', now()->timestamp - (int) config('session.lifetime') * 60)->first();
        try {
            $serialized = Crypt::decrypt(base64_decode($session?->payload ?? '', true));
            $data = config('session.serialization', 'php') === 'json'
                ? json_decode($serialized, true, 512, JSON_THROW_ON_ERROR)
                : unserialize($serialized, ['allowed_classes' => false]);
        } catch (Throwable) {
            throw new HttpException(403, 'Host session is inactive.');
        }
        $token = is_array($data) ? ($data['sso_token'] ?? null) : null;
        if (! is_array($data) || ($data['connect_pilot_binding'] ?? null) !== $binding
            || ($data['connect_pilot_uuid'] ?? null) !== $uuid
            || (string) ($data[Auth::guard('web')->getName()] ?? '') !== (string) $record['user_id']
            || ! is_string($token) || $token === '') {
            throw new HttpException(403, 'Host session binding is inactive.');
        }

        return $token;
    }

    private function post(string $path, string $body, int $timeout): \Illuminate\Http\Client\Response
    {
        try {
            return Http::acceptJson()->withoutRedirecting()->connectTimeout(3)->timeout($timeout)
                ->withHeaders($this->signedHeaders($path, $body))
                ->withBody($body, 'application/json')
                ->post(config('connect_pilot.connect_origin').$path);
        } catch (ConnectionException) {
            throw new RuntimeException('Connect is unavailable.');
        }
    }

    private function signedHeaders(string $path, string $body): array
    {
        $timestamp = (string) now()->timestamp;
        $nonce = $this->randomReference();

        return [
            'X-Connect-Host' => config('connect_pilot.host_id'),
            'X-Connect-Key' => config('connect_pilot.key_id'),
            'X-Connect-Timestamp' => $timestamp,
            'X-Connect-Nonce' => $nonce,
            'X-Connect-Signature' => $this->signature($path, $timestamp, $nonce, $body),
        ];
    }

    private function signature(string $path, string $timestamp, string $nonce, string $body): string
    {
        return hash_hmac('sha256', "POST\n$path\n$timestamp\n$nonce\n".hash('sha256', $body), config('connect_pilot.secret'));
    }

    private function origin(mixed $value): bool
    {
        $parts = is_string($value) && filter_var($value, FILTER_VALIDATE_URL) ? parse_url($value) : false;

        return is_array($parts) && ($parts['scheme'] ?? null) === 'https' && ! empty($parts['host'])
            && $parts['host'] === strtolower($parts['host']) && ($parts['port'] ?? null) !== 443
            && ! isset($parts['user']) && ! isset($parts['pass'])
            && ! isset($parts['query']) && ! isset($parts['fragment'])
            && $value === 'https://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    private function identifier(mixed $value): bool
    {
        return is_string($value) && (bool) preg_match('/\A[a-z0-9][a-z0-9_-]{0,63}\z/', $value);
    }

    private function uuid(mixed $value): bool
    {
        return is_string($value) && Str::isUuid($value) && strtolower($value) === $value;
    }

    private function reference(mixed $value): bool
    {
        if (! is_string($value) || ! preg_match('/\A[A-Za-z0-9_-]{43}\z/', $value)) {
            return false;
        }
        $bytes = base64_decode(strtr($value, '-_', '+/').'=', true);

        return $bytes !== false && strlen($bytes) === 32 && $this->encode($bytes) === $value;
    }

    private function bindingKey(string $binding): string
    {
        return 'connect_pilot:binding:'.hash('sha256', $binding);
    }

    private function revoked(string $binding): bool
    {
        return DB::table('connect_pilot_revocations')
            ->where('host_session_digest', hash('sha256', $binding))->exists();
    }

    private function markRevoked(string $binding): void
    {
        DB::table('connect_pilot_revocations')->insertOrIgnore([
            'host_session_digest' => hash('sha256', $binding),
            'revoked_at' => now(),
        ]);
    }

    private function randomReference(): string
    {
        return $this->encode(random_bytes(32));
    }

    private function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}

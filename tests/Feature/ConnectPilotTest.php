<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ConnectPilotTest extends TestCase
{
    use RefreshDatabase;

    private const UUID = '00000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    private function enablePilot(): void
    {
        config([
            'app.url' => 'https://opts.example.invalid',
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'session.driver' => 'database',
            'session.encrypt' => true,
            'session.secure' => true,
            'session.http_only' => true,
            'cache.default' => 'database',
            'connect_pilot.enabled' => true,
            'connect_pilot.connect_origin' => 'https://connect.example.invalid',
            'connect_pilot.host_id' => 'opts2026',
            'connect_pilot.key_id' => 'opts2026-key',
            'connect_pilot.secret' => str_repeat('b', 32),
            'services.lgu_sso.api_url' => 'https://sso.example.invalid',
            'services.lgu_sso.client_id' => 'opts2026-client',
            'services.lgu_sso.client_secret' => 'synthetic-sso-secret',
        ]);
    }

    private function signIn(): User
    {
        $user = User::factory()->create(['sso_uuid' => self::UUID, 'is_active' => true]);
        $this->actingAs($user)->withSession(['sso_token' => 'synthetic-sso-token']);

        return $user;
    }

    private function fakeUpstream(int $authorizeStatus = 200, int $revokeStatus = 200): void
    {
        Http::fake([
            'https://sso.example.invalid/api/v1/sso/authorize' => Http::response([
                'authorized' => true, 'employee' => ['uuid' => self::UUID],
            ], $authorizeStatus),
            'https://connect.example.invalid/api/widget/host-launches' => Http::response([
                'launch_ref' => rtrim(strtr(base64_encode(str_repeat('c', 32)), '+/', '-_'), '='),
                'expires_in' => 30,
            ], 201),
            'https://connect.example.invalid/api/widget/host-revocations' => Http::response(['status' => 'revoked'], $revokeStatus),
        ]);
    }

    /** @return array{host_session_ref: string, employee_uuid: string} */
    private function launchBinding(): array
    {
        $sent = Http::recorded(fn ($request) => $request->url() === 'https://connect.example.invalid/api/widget/host-launches')->last()[0];
        $body = $sent->body();
        $fields = json_decode($body, true);
        $this->assertSame(self::UUID, $fields['employee_uuid']);
        $this->assertSame('https://opts.example.invalid', $fields['parent_origin']);
        $this->assertSame('opts2026', $sent->header('X-Connect-Host')[0]);
        $this->assertSame('opts2026-key', $sent->header('X-Connect-Key')[0]);
        $this->assertSame(hash_hmac('sha256', "POST\n/api/widget/host-launches\n".$sent->header('X-Connect-Timestamp')[0]
            ."\n".$sent->header('X-Connect-Nonce')[0]."\n".hash('sha256', $body), str_repeat('b', 32)),
            $sent->header('X-Connect-Signature')[0]);

        return ['host_session_ref' => $fields['host_session_ref'], 'employee_uuid' => self::UUID];
    }

    private function signedHeaders(array $body, string $nonce): array
    {
        $timestamp = (string) now()->timestamp;
        $json = json_encode($body);

        return [
            'X-Connect-Host' => 'opts2026',
            'X-Connect-Key' => 'opts2026-key',
            'X-Connect-Timestamp' => $timestamp,
            'X-Connect-Nonce' => $nonce,
            'X-Connect-Signature' => hash_hmac('sha256', "POST\n/connect/pilot/introspect\n$timestamp\n$nonce\n".hash('sha256', $json), str_repeat('b', 32)),
        ];
    }

    public function test_disabled_or_insecure_pilot_never_calls_connect(): void
    {
        $this->signIn();
        $this->fakeUpstream();
        $this->assertStringContainsString('no-store', $this->post('/connect/pilot/launch')
            ->assertServiceUnavailable()->headers->get('Cache-Control'));
        Http::assertNothingSent();

        $this->enablePilot();
        config(['session.encrypt' => false]);
        $this->post('/connect/pilot/launch')->assertServiceUnavailable();
        config(['session.encrypt' => true]);
        config(['session.secure' => false]);
        $this->post('/connect/pilot/launch')->assertServiceUnavailable();
        config(['session.secure' => true, 'connect_pilot.connect_origin' => 'https://connect.example.invalid/path']);
        $this->post('/connect/pilot/launch')->assertServiceUnavailable();
        config(['connect_pilot.connect_origin' => 'https://connect.example.invalid', 'app.env' => 'production']);
        $this->post('/connect/pilot/launch')->assertServiceUnavailable();
        Http::assertNothingSent();
    }

    public function test_loader_is_advertised_only_with_safe_pilot_configuration(): void
    {
        $this->signIn();
        $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')->where('connectPilot', null)->etc());

        $this->enablePilot();
        $this->withSession(['sso_token' => 'synthetic-sso-token']);
        $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('connectPilot.origin', 'https://connect.example.invalid')
            ->where('connectPilot.launchUrl', '/connect/pilot/launch')->etc());
        $this->get('/dashboard')->assertDontSee('synthetic-sso-token')
            ->assertDontSee('synthetic-sso-secret')->assertDontSee(str_repeat('b', 32));
    }

    public function test_launch_requires_current_matching_sso_grant_and_active_local_user(): void
    {
        $this->enablePilot();
        $user = $this->signIn();
        $this->fakeUpstream(403);
        $this->post('/connect/pilot/launch')->assertServiceUnavailable();
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'connect.example.invalid'));

        Http::swap(new Factory);
        Http::fake(['https://sso.example.invalid/api/v1/sso/authorize' => Http::response([
            'authorized' => true, 'employee' => ['uuid' => '00000000-0000-4000-8000-000000000002'],
        ])]);
        $this->post('/connect/pilot/launch')->assertServiceUnavailable();

        $user->update(['is_active' => false]);
        Http::swap(new Factory);
        $this->fakeUpstream();
        $this->post('/connect/pilot/launch')->assertServiceUnavailable();
        Http::assertNothingSent();
    }

    public function test_signed_launch_introspection_replay_and_logout_revocation(): void
    {
        $this->enablePilot();
        $this->signIn();
        $this->fakeUpstream();
        $this->post('/connect/pilot/launch')->assertOk()->assertJsonStructure(['launch_ref', 'expires_in']);
        $binding = $this->launchBinding();

        $nonce = rtrim(strtr(base64_encode(str_repeat('d', 32)), '+/', '-_'), '=');
        $headers = $this->signedHeaders($binding, $nonce);
        $this->withHeaders([...$headers, 'X-Connect-Signature' => str_repeat('0', 64)])
            ->postJson('/connect/pilot/introspect', $binding)->assertForbidden();
        $this->withHeaders($headers)->postJson('/connect/pilot/introspect', $binding)
            ->assertOk()->assertJson([
                'active' => true, 'host_id' => 'opts2026', 'employee_uuid' => self::UUID,
                'host_session_digest' => hash('sha256', $binding['host_session_ref']),
            ]);
        $this->withHeaders($headers)->postJson('/connect/pilot/introspect', $binding)->assertForbidden();

        $this->post('/logout')->assertRedirect('/');
        $revocation = Http::recorded(fn ($request) => $request->url() === 'https://connect.example.invalid/api/widget/host-revocations')->first()[0];
        $this->assertEqualsCanonicalizing($binding, json_decode($revocation->body(), true));
        $this->assertSame(hash_hmac('sha256', "POST\n/api/widget/host-revocations\n".$revocation->header('X-Connect-Timestamp')[0]
            ."\n".$revocation->header('X-Connect-Nonce')[0]."\n".hash('sha256', $revocation->body()), str_repeat('b', 32)),
            $revocation->header('X-Connect-Signature')[0]);
        $nextNonce = rtrim(strtr(base64_encode(str_repeat('e', 32)), '+/', '-_'), '=');
        $this->withHeaders($this->signedHeaders($binding, $nextNonce))
            ->postJson('/connect/pilot/introspect', $binding)->assertForbidden();
    }

    public function test_introspection_denies_revoked_grant_and_logout_survives_connect_outage(): void
    {
        $this->enablePilot();
        $this->signIn();
        $this->fakeUpstream();
        $launch = $this->post('/connect/pilot/launch')->assertOk();
        $hostCookie = collect($launch->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'))?->getValue();
        $binding = $this->launchBinding();
        $bindingKey = 'connect_pilot:binding:'.hash('sha256', $binding['host_session_ref']);
        $oldRecord = Cache::get($bindingKey);

        Http::swap(new Factory);
        $this->fakeUpstream(403, 503);
        $nonce = rtrim(strtr(base64_encode(str_repeat('f', 32)), '+/', '-_'), '=');
        $this->withHeaders($this->signedHeaders($binding, $nonce))
            ->postJson('/connect/pilot/introspect', $binding)->assertForbidden();

        Http::swap(new Factory);
        $this->fakeUpstream(200, 503);
        // A stale cache value must not undo a confirmed revocation.
        Cache::put($bindingKey, $oldRecord, 120);
        $nextNonce = rtrim(strtr(base64_encode(str_repeat('k', 32)), '+/', '-_'), '=');
        $this->withHeaders($this->signedHeaders($binding, $nextNonce))
            ->postJson('/connect/pilot/introspect', $binding)->assertForbidden();
        $this->withUnencryptedCookie(config('session.cookie'), $hostCookie);
        $this->post('/connect/pilot/launch')->assertOk();
        $replacement = $this->launchBinding();
        $this->assertNotSame($binding['host_session_ref'], $replacement['host_session_ref']);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_introspection_denies_inactive_local_user(): void
    {
        $this->enablePilot();
        $user = $this->signIn();
        $this->fakeUpstream();
        $this->post('/connect/pilot/launch')->assertOk();
        $binding = $this->launchBinding();

        $user->update(['is_active' => false]);
        $nonce = rtrim(strtr(base64_encode(str_repeat('g', 32)), '+/', '-_'), '=');
        $this->withHeaders($this->signedHeaders($binding, $nonce))
            ->postJson('/connect/pilot/introspect', $binding)->assertForbidden();

        $user->update(['is_active' => true]);
        $nonce = rtrim(strtr(base64_encode(str_repeat('h', 32)), '+/', '-_'), '=');
        $this->withHeaders($this->signedHeaders($binding, $nonce))
            ->postJson('/connect/pilot/introspect', $binding)->assertForbidden();
    }

    public function test_introspection_denies_changed_local_identity(): void
    {
        $this->enablePilot();
        $user = $this->signIn();
        $this->fakeUpstream();
        $this->post('/connect/pilot/launch')->assertOk();
        $binding = $this->launchBinding();

        $user->update(['sso_uuid' => '00000000-0000-4000-8000-000000000002']);
        $nonce = rtrim(strtr(base64_encode(str_repeat('i', 32)), '+/', '-_'), '=');
        $this->withHeaders($this->signedHeaders($binding, $nonce))
            ->postJson('/connect/pilot/introspect', $binding)->assertForbidden();

        $user->update(['sso_uuid' => self::UUID]);
        $nonce = rtrim(strtr(base64_encode(str_repeat('n', 32)), '+/', '-_'), '=');
        $this->withHeaders($this->signedHeaders($binding, $nonce))
            ->postJson('/connect/pilot/introspect', $binding)->assertForbidden();
    }

    public function test_introspection_denies_missing_host_session(): void
    {
        $this->enablePilot();
        $this->signIn();
        $this->fakeUpstream();
        $this->post('/connect/pilot/launch')->assertOk();
        $binding = $this->launchBinding();

        DB::table('sessions')->delete();
        $nonce = rtrim(strtr(base64_encode(str_repeat('o', 32)), '+/', '-_'), '=');
        $this->withHeaders($this->signedHeaders($binding, $nonce))
            ->postJson('/connect/pilot/introspect', $binding)->assertForbidden();
    }

    public function test_sso_and_connect_connection_failures_leave_the_host_usable(): void
    {
        $this->enablePilot();
        $this->signIn();
        $this->fakeUpstream();
        $launch = $this->post('/connect/pilot/launch')->assertOk();
        $hostCookie = collect($launch->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'))?->getValue();
        $binding = $this->launchBinding();

        Http::swap(new Factory);
        Http::fake([
            'https://sso.example.invalid/api/v1/sso/authorize' => fn () => throw new ConnectionException('Synthetic SSO outage'),
            'https://connect.example.invalid/api/widget/host-revocations' => fn () => throw new ConnectionException('Synthetic Connect outage'),
        ]);
        $nonce = rtrim(strtr(base64_encode(str_repeat('j', 32)), '+/', '-_'), '=');
        $this->withHeaders($this->signedHeaders($binding, $nonce))
            ->postJson('/connect/pilot/introspect', $binding)->assertServiceUnavailable();
        $this->assertAuthenticated();

        Http::swap(new Factory);
        $this->fakeUpstream();
        $nextNonce = rtrim(strtr(base64_encode(str_repeat('l', 32)), '+/', '-_'), '=');
        $this->withHeaders($this->signedHeaders($binding, $nextNonce))
            ->postJson('/connect/pilot/introspect', $binding)->assertOk();

        Http::swap(new Factory);
        $revocationAttempted = false;
        Http::fake([
            'https://connect.example.invalid/api/widget/host-revocations' => function () use (&$revocationAttempted) {
                $revocationAttempted = true;
                throw new ConnectionException('Synthetic Connect outage');
            },
        ]);
        $this->withUnencryptedCookie(config('session.cookie'), $hostCookie);
        $this->post('/logout')->assertRedirect('/');
        $this->assertTrue($revocationAttempted);
        $this->assertGuest();
    }

    public function test_confirmed_launch_denial_invalidates_the_old_binding_but_keeps_opts_signed_in(): void
    {
        $this->enablePilot();
        $this->signIn();
        $this->fakeUpstream();
        $launch = $this->post('/connect/pilot/launch')->assertOk();
        $binding = $this->launchBinding();
        $hostCookie = collect($launch->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'))?->getValue();

        Http::swap(new Factory);
        $this->fakeUpstream(403);
        $this->withUnencryptedCookie(config('session.cookie'), $hostCookie);
        $denied = $this->post('/connect/pilot/launch')->assertServiceUnavailable();
        $this->assertAuthenticated();
        $revocation = Http::recorded(fn ($request) => $request->url() === 'https://connect.example.invalid/api/widget/host-revocations')->first()[0];
        $this->assertEqualsCanonicalizing($binding, json_decode($revocation->body(), true));

        Http::swap(new Factory);
        $this->fakeUpstream();
        $nextCookie = collect($denied->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'))?->getValue();
        $this->withUnencryptedCookie(config('session.cookie'), $nextCookie);
        $this->post('/connect/pilot/launch')->assertOk();
        $replacement = $this->launchBinding();
        $this->assertNotSame($binding['host_session_ref'], $replacement['host_session_ref']);
        $nonce = rtrim(strtr(base64_encode(str_repeat('m', 32)), '+/', '-_'), '=');
        $this->withHeaders($this->signedHeaders($binding, $nonce))
            ->postJson('/connect/pilot/introspect', $binding)->assertForbidden();
    }
}

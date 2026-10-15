<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSsoEligibility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
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
        $this->actingAs($user)->withSession(['sso_token' => 'synthetic-sso-token', Auth::guard('web')->getName() => $user->id]);

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
        config(['connect_pilot.connect_origin' => 'https://connect.example.invalid', 'app.env' => 'production', 'connect_pilot.enabled' => false]);
        $this->post('/connect/pilot/launch')->assertServiceUnavailable();
        Http::assertNothingSent();
    }

    public function test_disabled_production_stays_inert_with_complete_registration(): void
    {
        $this->enablePilot();
        config([
            'app.env' => 'production',
            'app.url' => 'https://opts.lguquezon.local',
            'connect_pilot.connect_origin' => 'https://connect.lguquezon.local',
            'connect_pilot.host_id' => 'opts-production',
            'connect_pilot.key_id' => 'opts-production-v1',
            'connect_pilot.enabled' => false,
        ]);
        $this->app->instance('env', 'production');
        $this->signIn();
        Http::fake();

        $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('connectWidget', null)->etc());
        $this->withSession(['_token' => 'production-csrf'])
            ->withHeader('X-CSRF-TOKEN', 'production-csrf')
            ->postJson('/connect/pilot/launch')->assertServiceUnavailable();
        $this->withHeader('X-CSRF-TOKEN', '')
            ->postJson('/connect/pilot/introspect', ['employee_uuid' => self::UUID])
            ->assertForbidden()->assertJson(['active' => false]);
        $this->assertDatabaseCount('connect_pilot_revocations', 0);
        $this->assertNull(session('connect_pilot_binding'));
        Http::assertNothingSent();
    }

    public function test_connector_secret_cannot_reuse_the_sso_client_secret(): void
    {
        $this->enablePilot();
        config(['services.lgu_sso.client_secret' => config('connect_pilot.secret')]);
        $this->signIn();
        Http::fake();

        $this->assertFalse(app(\App\Services\ConnectPilotHost::class)->configured());
        $this->postJson('/connect/pilot/launch')->assertServiceUnavailable();
        Http::assertNothingSent();
    }

    public function test_production_browser_launch_requires_csrf(): void
    {
        $this->enablePilot();
        $this->app->instance('env', 'production');
        $this->signIn();
        Http::fake();

        $this->postJson('/connect/pilot/launch')->assertStatus(419);
        Http::assertNothingSent();
    }

    public function test_guest_cannot_launch_connect_in_production(): void
    {
        $this->enablePilot();
        $this->app->instance('env', 'production');
        Http::fake();

        $this->withSession(['_token' => 'guest-csrf'])
            ->withHeader('X-CSRF-TOKEN', 'guest-csrf')
            ->postJson('/connect/pilot/launch')->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_exact_production_registration_uses_only_production_upstreams(): void
    {
        $this->enablePilot();
        config([
            'app.env' => 'production',
            'app.url' => 'https://opts.lguquezon.local',
            'connect_pilot.connect_origin' => 'https://connect.lguquezon.local',
            'connect_pilot.host_id' => 'opts-production',
            'connect_pilot.key_id' => 'opts-production-v1',
            'services.lgu_sso.api_url' => 'https://api.sso.lguquezon.local',
        ]);
        $this->app->instance('env', 'production');
        $this->signIn();
        Http::preventStrayRequests();
        Http::fake([
            'https://api.sso.lguquezon.local/api/v1/sso/authorize' => Http::response([
                'authorized' => true, 'employee' => ['uuid' => self::UUID],
            ]),
            'https://connect.lguquezon.local/api/widget/host-launches' => Http::response([
                'launch_ref' => rtrim(strtr(base64_encode(str_repeat('p', 32)), '+/', '-_'), '='),
                'expires_in' => 30,
            ], 201),
        ]);
        $this->withSession(['_token' => 'production-csrf'])
            ->withHeader('X-CSRF-TOKEN', 'production-csrf')
            ->postJson('/connect/pilot/launch')->assertOk()->assertJsonPath('expires_in', 30);
        $launch = Http::recorded(fn ($request) => $request->url() === 'https://connect.lguquezon.local/api/widget/host-launches')->first()[0];
        $this->assertSame('opts-production', $launch->header('X-Connect-Host')[0]);
        $this->assertSame('opts-production-v1', $launch->header('X-Connect-Key')[0]);
        $fields = json_decode($launch->body(), true);
        $this->assertSame('https://opts.lguquezon.local', $fields['parent_origin']);
        $binding = ['host_session_ref' => $fields['host_session_ref'], 'employee_uuid' => self::UUID];
        $nonce = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $headers = $this->signedHeaders($binding, $nonce);
        $headers['X-Connect-Host'] = 'opts-production';
        $headers['X-Connect-Key'] = 'opts-production-v1';
        $this->withHeaders($headers)->postJson('/connect/pilot/introspect', $binding)
            ->assertOk()->assertJsonPath('host_id', 'opts-production');
        Http::assertSentCount(3);
    }

    public function test_loader_is_advertised_only_with_safe_pilot_configuration(): void
    {
        $this->signIn();
        $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')->where('connectWidget', null)->etc());

        $this->enablePilot();
        $this->withSession(['sso_token' => 'synthetic-sso-token']);
        $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('connectWidget.origin', 'https://connect.example.invalid')
            ->where('connectWidget.launchUrl', '/connect/pilot/launch')->where('connectWidget.identity', self::UUID)->etc());
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

    public function test_environments_require_explicit_safe_configuration(): void
    {
        foreach (['local', 'staging', 'production'] as $environment) {
            $this->enablePilot();
            config(['app.env' => $environment, 'connect_pilot.enabled' => false]);
            $this->assertFalse(app(\App\Services\ConnectPilotHost::class)->configured());
            config(['connect_pilot.enabled' => true]);
            $this->assertTrue(app(\App\Services\ConnectPilotHost::class)->configured());
        }
    }

    public function test_introspection_checks_encrypted_session_payload_and_expiry(): void
    {
        $this->enablePilot();
        $this->signIn();
        $this->fakeUpstream();
        $this->post('/connect/pilot/launch')->assertOk();
        $binding = $this->launchBinding();
        $record = Cache::get('connect_pilot:binding:'.hash('sha256', $binding['host_session_ref']));
        $row = DB::table('sessions')->where('id', $record['session_id'])->first();
        $this->assertStringNotContainsString('synthetic-sso-token', base64_decode($row->payload));
        $data = unserialize(Crypt::decrypt(base64_decode($row->payload)), ['allowed_classes' => false]);
        foreach (['connect_pilot_binding', 'connect_pilot_uuid', Auth::guard('web')->getName(), 'sso_token'] as $field) {
            $changed = $data;
            unset($changed[$field]);
            DB::table('sessions')->where('id', $row->id)->update(['payload' => base64_encode(Crypt::encrypt(serialize($changed)))]);
            $nonce = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $this->withHeaders($this->signedHeaders($binding, $nonce))
                ->postJson('/connect/pilot/introspect', $binding)->assertForbidden();
        }
        DB::table('sessions')->where('id', $row->id)->update([
            'payload' => $row->payload,
            'last_activity' => now()->timestamp - (int) config('session.lifetime') * 60 - 1,
        ]);
        $nonce = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->withHeaders($this->signedHeaders($binding, $nonce))
            ->postJson('/connect/pilot/introspect', $binding)->assertForbidden();
    }

    public function test_introspection_uses_current_session_token_without_caching_bearer(): void
    {
        $this->enablePilot();
        $this->signIn();
        $this->fakeUpstream();
        $this->post('/connect/pilot/launch')->assertOk();
        $binding = $this->launchBinding();
        $record = Cache::get('connect_pilot:binding:'.hash('sha256', $binding['host_session_ref']));
        $this->assertArrayNotHasKey('token', $record);
        $row = DB::table('sessions')->where('id', $record['session_id'])->first();
        $data = unserialize(Crypt::decrypt(base64_decode($row->payload)), ['allowed_classes' => false]);
        $data['sso_token'] = 'replacement-sso-token';
        DB::table('sessions')->where('id', $row->id)->update(['payload' => base64_encode(Crypt::encrypt(serialize($data)))]);
        Http::swap(new Factory);
        $this->fakeUpstream();
        $nonce = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->withHeaders($this->signedHeaders($binding, $nonce))
            ->postJson('/connect/pilot/introspect', $binding)->assertOk();
        Http::assertSent(fn ($request) => $request->url() === 'https://sso.example.invalid/api/v1/sso/authorize'
            && $request['token'] === 'replacement-sso-token');
    }

    public function test_confirmed_host_middleware_denial_revokes_before_invalidating_session(): void
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
        $this->withMiddleware(EnsureSsoEligibility::class);
        $this->withUnencryptedCookie(config('session.cookie'), $hostCookie);
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseHas('connect_pilot_revocations', ['host_session_digest' => hash('sha256', $binding['host_session_ref'])]);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/widget/host-revocations'));
    }

    public function test_successful_sso_callback_replaces_old_binding_before_new_identity(): void
    {
        $this->enablePilot();
        $this->signIn();
        $this->fakeUpstream();
        $launch = $this->post('/connect/pilot/launch')->assertOk();
        $binding = $this->launchBinding();
        $hostCookie = collect($launch->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'))?->getValue();
        $replacement = User::factory()->create(['sso_uuid' => '00000000-0000-4000-8000-000000000002']);
        $this->mock(\App\Services\SSOService::class, function ($mock) use ($replacement) {
            $mock->shouldReceive('exchangeCode')->once()->with('replacement-code')->andReturn('replacement-token');
            $mock->shouldReceive('authorize')->once()->with('replacement-token')->andReturn([
                'authorized' => true, 'employee' => ['uuid' => $replacement->sso_uuid],
            ]);
        });
        $this->mock(\App\Services\SSOIdentityService::class, function ($mock) use ($replacement) {
            $mock->shouldReceive('synchronize')->once()->andReturn($replacement);
        });
        // Exercise callback replacement with an existing binding, independently of the guest redirect guard.
        $this->withoutMiddleware(\Illuminate\Auth\Middleware\RedirectIfAuthenticated::class);
        $this->withUnencryptedCookie(config('session.cookie'), $hostCookie)
            ->withSession(['sso_state' => 'replacement-state'])
            ->get('/auth/sso/callback?state=replacement-state&code=replacement-code')
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($replacement);
        $this->assertDatabaseHas('connect_pilot_revocations', ['host_session_digest' => hash('sha256', $binding['host_session_ref'])]);
        $this->assertNull(session('connect_pilot_binding'));
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/widget/host-revocations'));
    }

    public function test_introspection_budget_is_per_verified_binding_and_recovers_without_revocation(): void
    {
        $this->freezeTime();
        $this->enablePilot();
        $this->signIn();
        $this->fakeUpstream();
        $this->post('/connect/pilot/launch')->assertOk();
        $original = $this->launchBinding();
        $record = Cache::get('connect_pilot:binding:'.hash('sha256', $original['host_session_ref']));
        $row = DB::table('sessions')->where('id', $record['session_id'])->first();
        $data = unserialize(Crypt::decrypt(base64_decode($row->payload)), ['allowed_classes' => false]);
        $bindings = [];
        // Independent, persisted browser sessions behind the same Connect source IP.
        for ($i = 0; $i < 3; $i++) {
            $binding = ['host_session_ref' => rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='), 'employee_uuid' => self::UUID];
            $sessionId = str_repeat((string) ($i + 1), 40);
            $payload = [...$data, 'connect_pilot_binding' => $binding['host_session_ref']];
            DB::table('sessions')->insert([
                ...((array) $row), 'id' => $sessionId,
                'payload' => base64_encode(Crypt::encrypt(serialize($payload))),
            ]);
            Cache::put('connect_pilot:binding:'.hash('sha256', $binding['host_session_ref']), [...$record, 'session_id' => $sessionId], 600);
            $bindings[] = $binding;
        }
        $this->assertSame(6000, config('connect_pilot.introspection_source_per_minute'));
        $this->assertSame(120, config('connect_pilot.introspection_binding_per_minute'));
        $request = function (array $binding, bool $valid = true) {
            $nonce = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $headers = $this->signedHeaders($binding, $nonce);
            if (! $valid) {
                $headers['X-Connect-Signature'] = str_repeat('0', 64);
            }

            return $this->withHeaders($headers)->postJson('/connect/pilot/introspect', $binding);
        };
        $request($bindings[0], false)->assertForbidden();
        foreach ($bindings as $binding) {
            for ($i = 0; $i < 120; $i++) {
                $request($binding)->assertOk();
            }
        }
        // 360 authorized requests work; only the exhausted binding receives a temporary limit.
        $request($bindings[0])->assertStatus(429)->assertHeader('Retry-After', '60')
            ->assertJson(['active' => false]);
        $request($original)->assertOk();
        $this->assertDatabaseMissing('connect_pilot_revocations', ['host_session_digest' => hash('sha256', $bindings[0]['host_session_ref'])]);
        $this->travel(61)->seconds();
        $request($bindings[0])->assertOk();
    }

    public static function supportedWidgetEnvironments(): array
    {
        return [
            'local' => ['local'],
            'staging' => ['staging'],
            'production' => ['production'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('supportedWidgetEnvironments')]
    public function test_safe_configuration_launches_and_introspects_in_each_environment(string $environment): void
    {
        $this->enablePilot();
        config(['app.env' => $environment]);
        $this->app->instance('env', $environment);
        $this->assertTrue(app()->environment($environment));
        $this->signIn();
        $this->fakeUpstream();
        // A real environment enables CSRF enforcement, unlike the testing environment.
        $this->withSession(['_token' => 'synthetic-widget-csrf'])
            ->withHeader('X-CSRF-TOKEN', 'synthetic-widget-csrf');

        $this->post('/connect/pilot/launch')->assertOk()
            ->assertJsonPath('expires_in', 30)->assertJsonStructure(['launch_ref']);
        $binding = $this->launchBinding();
        $nonce = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->withHeaders($this->signedHeaders($binding, $nonce))
            ->postJson('/connect/pilot/introspect', $binding)->assertOk()
            ->assertJsonPath('active', true)
            ->assertJsonPath('employee_uuid', self::UUID)
            ->assertJsonPath('host_session_digest', hash('sha256', $binding['host_session_ref']));
    }
}

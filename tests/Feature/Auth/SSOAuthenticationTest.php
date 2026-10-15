<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Http\Middleware\EnsureSsoEligibility;
use App\Models\Office;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SSOOfficeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SSOAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private string $uuid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withMiddleware(EnsureSsoEligibility::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->uuid = (string) Str::uuid();
        config(['services.lgu_sso.api_url' => 'https://sso.test']);
        Http::preventStrayRequests();
        $this->mock(SSOOfficeService::class, function ($mock) {
            $mock->shouldReceive('resolve')->andReturnUsing(fn (?int $id) => $id === null ? null : Office::where('sso_id', $id)->first());
            $mock->shouldReceive('refreshIfStale')->andReturnNull();
            $mock->shouldReceive('sync')->andReturn([]);
        });
    }

    private function fakeSso(string $role = 'standard', ?Office $office = null, array $person = [], int $directoryStatus = 200): void
    {
        Http::fake([
            '*/api/v1/sso/exchange' => Http::response(['access_token' => 'valid-token']),
            '*/api/v1/sso/authorize' => Http::response([
                'authorized' => true,
                'role' => $role,
                'employee' => ['uuid' => $this->uuid, 'full_name' => 'Demo Person', 'email' => 'demo@example.test'],
            ]),
            '*/api/v1/sso/directory/*' => Http::response(['data' => array_merge([
                'uuid' => $this->uuid,
                'full_name' => 'Demo Person',
                'initials' => 'D.P',
                'role' => $role,
                'office_id' => $office?->sso_id,
                'office_name' => $office?->name,
                'office_abbreviation' => $office?->abbreviation,
                'position_id' => 22,
                'position_title' => 'Municipal Mayor',
            ], $person)], $directoryStatus),
            '*/api/v1/auth/logout' => Http::response(['message' => 'ok']),
        ]);
    }

    private function localUser(string $role = 'Administrator', ?Office $office = null): User
    {
        $user = User::factory()->create(['sso_uuid' => $this->uuid, 'office_id' => $office?->id]);
        $user->assignRole($role);

        return $user;
    }

    private function performCallback()
    {
        return $this->withSession(['sso_state' => 'state'])->get('/auth/sso/callback?state=state&code=valid-code');
    }

    public function test_public_homepage_offers_access_without_starting_an_sso_exchange(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Welcome')
            ->where('auth.user', null));
        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_upstream_retry_delay_preserves_login_and_recovery_checks_the_current_role(): void
    {
        $user = $this->localUser();
        $this->actingAs($user)->withSession(['sso_token' => 'valid-token']);
        Http::fake(['*/api/v1/sso/authorize' => Http::response([], 503, ['Retry-After' => '30'])]);

        $response = $this->get('/dashboard')->assertServiceUnavailable()->assertHeader('Retry-After', '30');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->get('/dashboard')->assertServiceUnavailable()->assertHeader('Retry-After', '30');
        Http::assertSentCount(1);
        $this->assertAuthenticatedAs($user);
        $this->assertSame('valid-token', session('sso_token'));

        $this->travel(30)->seconds();
        Http::swap(new \Illuminate\Http\Client\Factory);
        $this->fakeSso('guest');
        $this->get('/dashboard')->assertOk();
        $this->assertTrue($user->fresh()->hasRole('Viewer'));
        Http::assertSentCount(2);
    }

    public function test_entry_page_preserves_the_callback_error_for_retry(): void
    {
        $this->get('/auth/sso/callback?state=wrong&code=invalid')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Auth/Login')
            ->where('ssoError', 'Invalid authentication state. Please try again.'));
        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_signed_in_homepage_keeps_identity_and_login_redirects_to_workspace(): void
    {
        $user = $this->localUser();
        $this->fakeSso('super_administrator');
        $this->actingAs($user)->withSession(['sso_token' => 'valid-token']);
        $this->get('/')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Welcome')
            ->where('auth.user.id', $user->id));
        $this->get('/login')->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_and_redirect_use_authorization_code_flow(): void
    {
        $this->get('/login')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/Login'));
        $response = $this->get('/auth/sso/redirect');
        $response->assertRedirect();
        $this->assertNotEmpty(session('sso_state'));
        $this->assertStringContainsString('state=', $response->headers->get('Location'));
    }

    public function test_callback_rejects_missing_or_wrong_state_and_legacy_token_parameter(): void
    {
        $this->get('/auth/sso/callback?code=valid-code')->assertRedirect('/login');
        $this->withSession(['sso_state' => 'state'])->get('/auth/sso/callback?state=wrong&code=valid-code')->assertRedirect('/login');
        $this->withSession(['sso_state' => 'state'])->get('/auth/sso/callback?state=state&token=valid-token')->assertRedirect('/login');
        Http::assertNothingSent();
        $this->assertGuest();
    }

    public function test_callback_provisions_standard_endorser_from_directory(): void
    {
        $office = Office::factory()->create();
        $this->fakeSso(office: $office);
        $this->performCallback()->assertRedirect(route('dashboard'));
        $user = User::where('sso_uuid', $this->uuid)->firstOrFail();
        $this->assertSame('Demo Person', $user->name);
        $this->assertSame('demo@example.test', $user->email);
        $this->assertSame('Municipal Mayor', $user->sso_position);
        $this->assertSame($office->id, $user->office_id);
        $this->assertSame('standard', $user->sso_role);
        $this->assertTrue($user->hasExactRoles(['Endorser']));
        $this->assertNotNull($user->last_sso_login_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_callback_replaces_local_roles_and_office_for_existing_uuid(): void
    {
        $old = Office::factory()->create();
        $new = Office::factory()->create();
        $user = $this->localUser(office: $old);
        $user->update(['is_active' => false]);
        $this->fakeSso('guest', $new);
        $this->performCallback()->assertRedirect(route('dashboard'));
        $user->refresh();
        $this->assertTrue($user->hasExactRoles(['Viewer']));
        $this->assertTrue($user->is_active);
        $this->assertSame($new->id, $user->office_id);
    }

    public function test_callback_does_not_adopt_a_different_identity_by_email(): void
    {
        $legacy = User::factory()->create(['email' => 'demo@example.test', 'sso_uuid' => (string) Str::uuid()]);
        $this->fakeSso('guest');
        $this->performCallback()->assertRedirect(route('dashboard'));
        $new = User::where('sso_uuid', $this->uuid)->firstOrFail();
        $this->assertNotSame($legacy->id, $new->id);
        $this->assertNull($new->email);
        $this->assertSame('demo@example.test', $legacy->fresh()->email);
    }

    public function test_callback_rejects_unknown_role_ineligible_identity_and_role_mismatch(): void
    {
        $this->fakeSso('unknown');
        $this->performCallback()->assertRedirect('/login');
        $this->fakeSso(directoryStatus: 404);
        $this->performCallback()->assertRedirect('/login');
        $this->fakeSso(person: ['role' => 'guest']);
        $this->performCallback()->assertRedirect('/login');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_callback_outage_has_retry_message_without_provisioning_user(): void
    {
        $this->fakeSso(directoryStatus: 503);
        $this->performCallback()->assertRedirect('/login')->assertSessionHas('sso_error');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_session_downgrade_is_applied_before_admin_route_authorization(): void
    {
        $user = $this->localUser();
        $this->fakeSso('guest');
        $this->actingAs($user)->withSession(['sso_token' => 'valid-token'])->get('/admin/workflows')->assertForbidden();
        $this->assertTrue($user->fresh()->hasExactRoles(['Viewer']));
    }

    public function test_guest_mayor_can_view_but_cannot_manage_or_process_records(): void
    {
        $office = Office::factory()->create();
        $user = $this->localUser(office: $office);
        $this->fakeSso('guest', $office);
        $this->actingAs($user)->withSession(['sso_token' => 'valid-token']);
        $this->get('/dashboard')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('auth.user.sso_role', 'guest')->where('auth.user.can_process_transactions', false)
            ->where('pendingReceiptsCount', 0)->missing('auth.user.sso_uuid')->missing('auth.user.last_sso_login_at'));
        $this->get('/procurements')->assertOk();
        $this->get('/procurements/create')->assertForbidden();
        $this->post('/procurements', [])->assertForbidden();
        $this->post('/transactions/receive-bulk', ['transaction_ids' => [1]])->assertForbidden();
        $this->get('/transactions/pending')->assertForbidden();
        $transaction = Transaction::factory()->create(['current_office_id' => $office->id]);
        foreach (['endorse', 'receive', 'complete'] as $action) {
            $this->post('/transactions/'.$transaction->id.'/'.$action, [])->assertForbidden();
        }
    }

    public function test_existing_session_receives_current_office_assignment(): void
    {
        $old = Office::factory()->create();
        $new = Office::factory()->create();
        $user = $this->localUser(office: $old);
        $this->fakeSso('standard', $new);
        $this->actingAs($user)->withSession(['sso_token' => 'valid-token'])->get('/profile')->assertOk();
        $this->assertSame($new->id, $user->fresh()->office_id);
        $this->assertTrue($user->fresh()->hasExactRoles(['Endorser']));
    }

    public function test_endorser_without_active_office_can_view_but_cannot_create(): void
    {
        $office = Office::factory()->create(['is_active' => false]);
        $user = $this->localUser(office: $office);
        $this->fakeSso('standard', $office);
        $this->actingAs($user)->withSession(['sso_token' => 'valid-token']);
        $this->get('/procurements')->assertOk();
        $this->get('/procurements/create')->assertForbidden();
        $this->post('/procurements', [])->assertForbidden();
    }

    public function test_administrator_without_office_can_configure(): void
    {
        $user = $this->localUser();
        $this->fakeSso('super_administrator');
        $this->actingAs($user)->withSession(['sso_token' => 'valid-token'])->get('/admin/workflows')->assertOk();
    }

    public function test_revoked_directory_eligibility_ends_the_local_session(): void
    {
        $user = $this->localUser();
        $this->fakeSso(directoryStatus: 404);
        $this->actingAs($user)->withSession(['sso_token' => 'valid-token'])->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_token_identity_mismatch_ends_the_local_session(): void
    {
        $user = $this->localUser();
        $this->uuid = (string) Str::uuid();
        $this->fakeSso();
        $this->actingAs($user)->withSession(['sso_token' => 'valid-token'])->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_missing_token_cannot_use_local_role_as_permission(): void
    {
        $user = $this->localUser();
        $this->actingAs($user)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_directory_outage_blocks_access_without_discarding_session(): void
    {
        $user = $this->localUser();
        $this->fakeSso(directoryStatus: 503);
        $this->actingAs($user)->withSession(['sso_token' => 'valid-token'])->get('/dashboard')->assertStatus(503);
        $this->assertAuthenticatedAs($user);
    }

    public function test_broadcast_subscription_requires_fresh_eligibility(): void
    {
        $user = $this->localUser();
        $this->fakeSso(directoryStatus: 404);
        $this->actingAs($user)->withSession(['sso_token' => 'valid-token'])
            ->post('/broadcasting/auth', ['channel_name' => 'private-App.Models.User.'.$user->id, 'socket_id' => '1.1'])
            ->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_mutation_forces_fresh_office_snapshot_before_validation(): void
    {
        $office = Office::factory()->create();
        $user = $this->localUser(office: $office);
        $this->fakeSso('standard', $office);
        $this->mock(SSOOfficeService::class, function ($mock) {
            $mock->shouldNotReceive('resolve');
            $mock->shouldReceive('sync')->once()->andReturn([]);
            $mock->shouldNotReceive('refreshIfStale');
        });
        $this->actingAs($user)->withSession(['sso_token' => 'valid-token'])
            ->post('/procurements', [])->assertSessionHasErrors('purpose');
    }

    public function test_workflow_mutation_forces_current_offices_before_assignment_validation(): void
    {
        $user = $this->localUser();
        $this->fakeSso('administrator');
        $this->mock(SSOOfficeService::class, function ($mock) {
            $mock->shouldReceive('sync')->once()->andReturn([]);
            $mock->shouldNotReceive('refreshIfStale');
            $mock->shouldNotReceive('resolve');
        });
        $this->actingAs($user)->withSession(['sso_token' => 'valid-token'])
            ->post('/admin/workflows', [])->assertSessionHasErrors('name');
    }

    public function test_read_uses_fresh_identity_but_reuses_mapped_office_metadata(): void
    {
        $office = Office::factory()->create();
        $user = $this->localUser(office: $office);
        $this->fakeSso('standard', $office);
        $this->mock(SSOOfficeService::class, function ($mock) {
            $mock->shouldReceive('refreshIfStale')->once();
            $mock->shouldNotReceive('sync');
            $mock->shouldNotReceive('resolve');
        });
        $this->actingAs($user)->withSession(['sso_token' => 'valid-token'])->get('/profile')->assertOk();
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/sso/authorize'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/sso/directory/'.$this->uuid));
    }

    public function test_preference_write_does_not_force_office_sync(): void
    {
        $office = Office::factory()->create();
        $user = $this->localUser(office: $office);
        $this->fakeSso('guest', $office);
        $this->mock(SSOOfficeService::class, function ($mock) {
            $mock->shouldReceive('refreshIfStale')->once();
            $mock->shouldNotReceive('sync');
            $mock->shouldNotReceive('resolve');
        });
        $this->actingAs($user)->withSession(['sso_token' => 'valid-token'])
            ->put('/user/selected-year', ['year' => now()->year])->assertRedirect();
        Http::assertSentCount(2);
    }

    public function test_mutation_guard_uses_office_state_from_the_just_refreshed_snapshot(): void
    {
        $office = Office::factory()->create();
        $user = $this->localUser(office: $office);
        $user->load('office');
        $this->fakeSso('standard', $office);
        $this->mock(SSOOfficeService::class, function ($mock) use ($office) {
            $mock->shouldReceive('sync')->once()->andReturnUsing(function () use ($office) {
                $office->update(['is_active' => false]);

                return [];
            });
            $mock->shouldNotReceive('resolve');
        });
        $this->actingAs($user)->withSession(['sso_token' => 'valid-token'])
            ->post('/procurements', [])->assertForbidden();
        $this->assertFalse($user->canProcessTransactions());
    }

    public function test_new_directory_office_assignment_resolves_an_unmapped_office_on_read(): void
    {
        $user = $this->localUser();
        $this->fakeSso(person: ['office_id' => 999, 'office_name' => 'New Office', 'office_abbreviation' => 'NEW']);
        $this->mock(SSOOfficeService::class, function ($mock) {
            $mock->shouldReceive('refreshIfStale')->once();
            $mock->shouldReceive('resolve')->once()->with(999)->andReturnUsing(fn () => Office::factory()->create(['sso_id' => 999]));
        });
        $this->actingAs($user)->withSession(['sso_token' => 'valid-token'])->get('/profile')->assertOk();
        $this->assertSame(999, $user->fresh()->office->sso_id);
    }

    public function test_logout_works_even_when_directory_is_unavailable(): void
    {
        $user = $this->localUser();
        $this->fakeSso(directoryStatus: 503);
        $this->actingAs($user)->withSession(['sso_token' => 'valid-token'])->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v1/auth/logout'));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\SSOService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class SSOAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_login_page_renders_with_sso_button(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Auth/Login')
        );
    }

    public function test_sso_redirect_generates_state_and_redirects(): void
    {
        $response = $this->get('/auth/sso/redirect');

        $response->assertRedirect();
        $this->assertNotNull(session('sso_state'));
        $this->assertStringContainsString(
            config('services.lgu_sso.ui_url'),
            $response->headers->get('Location')
        );
    }

    public function test_callback_fails_with_invalid_state(): void
    {
        $this->withSession(['sso_state' => 'valid-state']);

        $response = $this->get('/auth/sso/callback?state=wrong-state&token=some-token');

        $response->assertRedirect('/login');
        $response->assertSessionHas('sso_error');
    }

    public function test_callback_fails_with_missing_state(): void
    {
        $response = $this->get('/auth/sso/callback?token=some-token');

        $response->assertRedirect('/login');
        $response->assertSessionHas('sso_error');
    }

    public function test_callback_fails_with_no_token(): void
    {
        $state = Str::random(40);
        $this->withSession(['sso_state' => $state]);

        $response = $this->get('/auth/sso/callback?state='.$state);

        $response->assertRedirect('/login');
        $response->assertSessionHas('sso_error');
    }

    public function test_callback_provisions_new_user_on_first_login(): void
    {
        $state = Str::random(40);
        $uuid = Str::uuid()->toString();

        Http::fake([
            '*/api/v1/sso/authorize' => Http::response([
                'authorized' => true,
                'role' => 'standard',
            ]),
            '*/api/v1/sso/validate' => Http::response([
                'employee' => [
                    'uuid' => $uuid,
                    'name' => 'John Doe',
                    'email' => 'john@lgu.gov.ph',
                    'position' => 'Staff',
                    'office' => [
                        'name' => 'Main Office',
                        'abbreviation' => 'MAIN',
                    ],
                ],
            ]),
        ]);

        $this->withSession(['sso_state' => $state]);

        $response = $this->get('/auth/sso/callback?state='.$state.'&token=valid-token');

        $response->assertRedirect(route('dashboard'));

        $user = User::where('sso_uuid', $uuid)->first();
        $this->assertNotNull($user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@lgu.gov.ph', $user->email);
        $this->assertEquals('Staff', $user->sso_position);
        $this->assertTrue($user->hasRole('Viewer'));
        $this->assertNotNull($user->last_sso_login_at);
    }

    public function test_callback_syncs_user_data_on_subsequent_login(): void
    {
        $uuid = Str::uuid()->toString();
        $user = User::create([
            'sso_uuid' => $uuid,
            'name' => 'Old Name',
            'email' => 'old@lgu.gov.ph',
            'password' => null,
            'is_active' => true,
        ]);
        $user->assignRole('Viewer');

        $state = Str::random(40);

        Http::fake([
            '*/api/v1/sso/authorize' => Http::response([
                'authorized' => true,
                'role' => 'standard',
            ]),
            '*/api/v1/sso/validate' => Http::response([
                'employee' => [
                    'uuid' => $uuid,
                    'name' => 'New Name',
                    'email' => 'new@lgu.gov.ph',
                    'position' => 'Senior Staff',
                ],
            ]),
        ]);

        $this->withSession(['sso_state' => $state]);

        $response = $this->get('/auth/sso/callback?state='.$state.'&token=valid-token');

        $response->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertEquals('New Name', $user->name);
        $this->assertEquals('new@lgu.gov.ph', $user->email);
        $this->assertEquals('Senior Staff', $user->sso_position);
        // Role should NOT change on subsequent login
        $this->assertTrue($user->hasRole('Viewer'));
    }

    public function test_callback_assigns_viewer_role_for_standard_sso_user(): void
    {
        $state = Str::random(40);
        $uuid = Str::uuid()->toString();

        Http::fake([
            '*/api/v1/sso/authorize' => Http::response([
                'authorized' => true,
                'role' => 'standard',
            ]),
            '*/api/v1/sso/validate' => Http::response([
                'employee' => [
                    'uuid' => $uuid,
                    'name' => 'Standard User',
                    'email' => 'standard@lgu.gov.ph',
                ],
            ]),
        ]);

        $this->withSession(['sso_state' => $state]);
        $this->get('/auth/sso/callback?state='.$state.'&token=valid-token');

        $user = User::where('sso_uuid', $uuid)->first();
        $this->assertTrue($user->hasRole('Viewer'));
    }

    public function test_callback_assigns_administrator_role_for_admin_sso_user(): void
    {
        $state = Str::random(40);
        $uuid = Str::uuid()->toString();

        Http::fake([
            '*/api/v1/sso/authorize' => Http::response([
                'authorized' => true,
                'role' => 'administrator',
            ]),
            '*/api/v1/sso/validate' => Http::response([
                'employee' => [
                    'uuid' => $uuid,
                    'name' => 'Admin User',
                    'email' => 'admin@lgu.gov.ph',
                ],
            ]),
        ]);

        $this->withSession(['sso_state' => $state]);
        $this->get('/auth/sso/callback?state='.$state.'&token=valid-token');

        $user = User::where('sso_uuid', $uuid)->first();
        $this->assertTrue($user->hasRole('Administrator'));
    }

    public function test_callback_rejects_inactive_user(): void
    {
        $uuid = Str::uuid()->toString();
        $user = User::create([
            'sso_uuid' => $uuid,
            'name' => 'Inactive User',
            'email' => 'inactive@lgu.gov.ph',
            'password' => null,
            'is_active' => false,
        ]);
        $user->assignRole('Viewer');

        $state = Str::random(40);

        Http::fake([
            '*/api/v1/sso/authorize' => Http::response([
                'authorized' => true,
                'role' => 'standard',
            ]),
            '*/api/v1/sso/validate' => Http::response([
                'employee' => [
                    'uuid' => $uuid,
                    'name' => 'Inactive User',
                    'email' => 'inactive@lgu.gov.ph',
                ],
            ]),
        ]);

        $this->withSession(['sso_state' => $state]);

        $response = $this->get('/auth/sso/callback?state='.$state.'&token=valid-token');

        $response->assertRedirect('/login');
        $response->assertSessionHas('sso_error');
        $this->assertGuest();
    }

    public function test_logout_invalidates_session_and_calls_sso_logout(): void
    {
        Http::fake([
            '*/api/v1/auth/logout' => Http::response(['message' => 'ok']),
        ]);

        $user = User::create([
            'sso_uuid' => Str::uuid()->toString(),
            'name' => 'Test User',
            'email' => 'test@lgu.gov.ph',
            'password' => null,
            'is_active' => true,
        ]);
        $user->assignRole('Viewer');

        $response = $this->actingAs($user)
            ->withSession(['sso_token' => 'jwt-token'])
            ->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/v1/auth/logout');
        });
    }

    public function test_old_register_route_returns_404(): void
    {
        $response = $this->get('/register');
        $response->assertStatus(404);
    }

    public function test_old_forgot_password_route_returns_404(): void
    {
        $response = $this->get('/forgot-password');
        $response->assertStatus(404);
    }
}

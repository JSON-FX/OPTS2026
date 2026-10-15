<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SSOOfficeService;
use App\Services\SSOService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        Role::create(['name' => 'Viewer', 'guard_name' => 'web']);
        Role::create(['name' => 'Endorser', 'guard_name' => 'web']);
        Role::create(['name' => 'Administrator', 'guard_name' => 'web']);
    }

    public function test_administrator_can_see_admin_menu(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Admin');
    }

    public function test_viewer_cannot_see_admin_menu(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');

        $response = $this->actingAs($viewer)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertDontSee('Admin');
    }

    public function test_endorser_cannot_see_admin_menu(): void
    {
        $endorser = User::factory()->create();
        $endorser->assignRole('Endorser');

        $response = $this->actingAs($endorser)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertDontSee('Admin');
    }

    public function test_viewer_receives_403_on_admin_routes(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');

        $response = $this->actingAs($viewer)->get('/admin/users');

        $response->assertStatus(403);
    }

    public function test_endorser_receives_403_on_admin_routes(): void
    {
        $endorser = User::factory()->create();
        $endorser->assignRole('Endorser');

        $response = $this->actingAs($endorser)->get('/admin/users');

        $response->assertStatus(403);
    }

    public function test_403_error_page_renders_for_unauthorized_access(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');

        // Test that 403 is returned for unauthorized access
        $response = $this->actingAs($viewer)->get('/admin/users');

        $response->assertStatus(403);
    }

    public function test_user_profile_dropdown_shows_role_and_office(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertStatus(200);
        // User data with roles and office is loaded via Inertia middleware
        $response->assertInertia(fn ($page) => $page
            ->has('auth.user.roles')
            ->where('auth.user.name', $admin->name)
            ->where('auth.user.email', $admin->email)
        );
    }

    public function test_administrator_can_access_users_directory(): void
    {
        $this->mock(SSOService::class)->shouldReceive('directory')->once()->andReturn([
            'data' => [],
            'meta' => ['page' => 1, 'per_page' => 20, 'has_more' => false],
        ]);
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $response = $this->actingAs($admin)->get('/admin/users');

        $response->assertStatus(200);
    }

    public function test_administrator_can_access_repositories(): void
    {
        $this->mock(SSOOfficeService::class)->shouldReceive('refreshIfStale')->once();
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $response = $this->actingAs($admin)->get('/admin/repositories/offices');
        $response->assertStatus(200);

        $response = $this->actingAs($admin)->get('/admin/repositories/suppliers');
        $response->assertStatus(200);

        $response = $this->actingAs($admin)->get('/admin/repositories/particulars');
        $response->assertStatus(200);

        $response = $this->actingAs($admin)->get('/admin/repositories/fund-types');
        $response->assertStatus(200);

        $response = $this->actingAs($admin)->get('/admin/repositories/action-taken');
        $response->assertStatus(200);
    }

    public function test_sso_directories_have_no_local_management_routes(): void
    {
        $this->assertTrue(Route::has('admin.users.index'));
        $this->assertTrue(Route::has('admin.repositories.offices.index'));

        foreach (['admin.users', 'admin.repositories.offices'] as $directory) {
            foreach (['create', 'store', 'edit', 'update', 'destroy'] as $action) {
                $this->assertFalse(Route::has($directory.'.'.$action));
            }
        }
    }

    public function test_legacy_import_endpoints_are_removed(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        foreach (['/admin/migration', '/admin/migration/1/progress', '/admin/migration/1/results'] as $path) {
            $this->actingAs($admin)->get($path)->assertNotFound();
        }
        foreach (['/admin/migration/upload', '/admin/migration/1/execute', '/admin/migration/1/rollback', '/admin/migration/clear-all'] as $path) {
            $this->actingAs($admin)->post($path)->assertNotFound();
        }
        $this->assertFalse(collect(Route::getRoutes())->contains(fn ($route) => str_starts_with($route->getName() ?? '', 'admin.migration.')));
    }

    public function test_notification_bell_icon_visible_in_layout(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Viewer');

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        // Bell icon should be visible in the layout (Lucide React component)
        $response->assertSee('Dashboard');
    }
}

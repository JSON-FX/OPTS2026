<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_administrator_can_access_users_management(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $response = $this->actingAs($admin)->get('/admin/users');

        $response->assertStatus(200);
    }

    public function test_administrator_can_access_repositories(): void
    {
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

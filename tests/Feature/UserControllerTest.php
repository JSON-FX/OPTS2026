<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserControllerTest extends TestCase
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

    public function test_administrator_can_access_user_management(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $response = $this->actingAs($admin)->get('/admin/users');

        $response->assertStatus(200);
    }

    public function test_viewer_cannot_access_user_management(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');

        $response = $this->actingAs($viewer)->get('/admin/users');

        $response->assertStatus(403);
    }

    public function test_endorser_cannot_access_user_management(): void
    {
        $endorser = User::factory()->create();
        $endorser->assignRole('Endorser');

        $response = $this->actingAs($endorser)->get('/admin/users');

        $response->assertStatus(403);
    }

    public function test_create_user_route_no_longer_exists(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $response = $this->actingAs($admin)->get('/admin/users/create');

        $response->assertStatus(404);
    }

    public function test_store_user_route_no_longer_exists(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'Viewer',
        ]);

        $response->assertStatus(405);
    }

    public function test_can_update_user_role(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $user = User::factory()->create();
        $user->assignRole('Viewer');

        $response = $this->actingAs($admin)->put("/admin/users/{$user->id}", [
            'role' => 'Endorser',
        ]);

        $response->assertRedirect('/admin/users');
        $this->assertTrue($user->fresh()->hasRole('Endorser'));
    }

    public function test_can_update_user_office(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $office = Office::create([
            'name' => 'Test Office',
            'type' => 'Administrative',
            'abbreviation' => 'TO',
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $user->assignRole('Viewer');

        $response = $this->actingAs($admin)->put("/admin/users/{$user->id}", [
            'role' => 'Viewer',
            'office_id' => $office->id,
        ]);

        $response->assertRedirect('/admin/users');
        $this->assertEquals($office->id, $user->fresh()->office_id);
    }

    public function test_can_deactivate_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Viewer');

        $response = $this->actingAs($admin)->put("/admin/users/{$user->id}", [
            'role' => 'Viewer',
            'is_active' => false,
        ]);

        $response->assertRedirect('/admin/users');
        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_can_delete_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $user = User::factory()->create();
        $user->assignRole('Viewer');

        $response = $this->actingAs($admin)->delete("/admin/users/{$user->id}");

        $response->assertRedirect('/admin/users');
        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
            'deleted_at' => null,
        ]);
    }

    public function test_cannot_delete_self(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $response = $this->actingAs($admin)->delete("/admin/users/{$admin->id}");

        $response->assertRedirect('/admin/users');
        $this->assertNotNull($admin->fresh());
    }
}

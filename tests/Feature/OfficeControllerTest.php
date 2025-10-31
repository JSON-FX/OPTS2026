<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OfficeControllerTest extends TestCase
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

    public function test_administrator_can_access_office_management(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $response = $this->actingAs($admin)->get('/admin/repositories/offices');

        $response->assertStatus(200);
    }

    public function test_viewer_cannot_access_office_management(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');

        $response = $this->actingAs($viewer)->get('/admin/repositories/offices');

        $response->assertStatus(403);
    }

    public function test_endorser_cannot_access_office_management(): void
    {
        $endorser = User::factory()->create();
        $endorser->assignRole('Endorser');

        $response = $this->actingAs($endorser)->get('/admin/repositories/offices');

        $response->assertStatus(403);
    }

    public function test_can_create_office_with_valid_data(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $response = $this->actingAs($admin)->post('/admin/repositories/offices', [
            'name' => 'Test Office',
            'type' => 'Administrative',
            'abbreviation' => 'TO',
            'is_active' => true,
        ]);

        $response->assertRedirect('/admin/repositories/offices');
        $this->assertDatabaseHas('offices', [
            'name' => 'Test Office',
            'type' => 'Administrative',
            'abbreviation' => 'TO',
        ]);
    }

    public function test_cannot_create_office_with_duplicate_name(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        Office::create([
            'name' => 'Existing Office',
            'type' => 'Administrative',
            'abbreviation' => 'EO',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post('/admin/repositories/offices', [
            'name' => 'Existing Office',
            'type' => 'Financial',
            'abbreviation' => 'EO2',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_cannot_create_office_with_duplicate_abbreviation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        Office::create([
            'name' => 'Existing Office',
            'type' => 'Administrative',
            'abbreviation' => 'EO',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post('/admin/repositories/offices', [
            'name' => 'Different Office',
            'type' => 'Financial',
            'abbreviation' => 'EO',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('abbreviation');
    }

    public function test_can_update_office(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $office = Office::create([
            'name' => 'Original Office',
            'type' => 'Administrative',
            'abbreviation' => 'OO',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->put("/admin/repositories/offices/{$office->id}", [
            'name' => 'Updated Office',
            'type' => 'Financial',
            'abbreviation' => 'UO',
            'is_active' => false,
        ]);

        $response->assertRedirect('/admin/repositories/offices');
        $this->assertDatabaseHas('offices', [
            'id' => $office->id,
            'name' => 'Updated Office',
            'type' => 'Financial',
            'abbreviation' => 'UO',
            'is_active' => false,
        ]);
    }

    public function test_can_delete_office(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $office = Office::create([
            'name' => 'Test Office',
            'type' => 'Administrative',
            'abbreviation' => 'TO',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->delete("/admin/repositories/offices/{$office->id}");

        $response->assertRedirect('/admin/repositories/offices');
        $this->assertSoftDeleted('offices', [
            'id' => $office->id,
        ]);
    }

    public function test_office_list_includes_pagination(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        // Create 55 offices to test pagination (limit is 50 per page)
        for ($i = 1; $i <= 55; $i++) {
            Office::create([
                'name' => "Office {$i}",
                'type' => 'Administrative',
                'abbreviation' => "O{$i}",
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs($admin)->get('/admin/repositories/offices');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('offices.data', 50)
            ->where('offices.total', 55)
        );
    }

    public function test_soft_deleted_offices_not_shown_in_list(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $activeOffice = Office::create([
            'name' => 'Active Office',
            'type' => 'Administrative',
            'abbreviation' => 'AO',
            'is_active' => true,
        ]);

        $deletedOffice = Office::create([
            'name' => 'Deleted Office',
            'type' => 'Administrative',
            'abbreviation' => 'DO',
            'is_active' => true,
        ]);
        $deletedOffice->delete();

        $response = $this->actingAs($admin)->get('/admin/repositories/offices');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('offices.data', 1)
            ->where('offices.data.0.name', 'Active Office')
        );
    }

    public function test_deleting_office_with_users_unassigns_users(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $office = Office::create([
            'name' => 'Test Office',
            'type' => 'Administrative',
            'abbreviation' => 'TO',
            'is_active' => true,
        ]);

        $user1 = User::factory()->create(['office_id' => $office->id]);
        $user2 = User::factory()->create(['office_id' => $office->id]);

        $response = $this->actingAs($admin)->delete("/admin/repositories/offices/{$office->id}");

        $response->assertRedirect('/admin/repositories/offices');

        // Check that users are unassigned
        $this->assertDatabaseHas('users', [
            'id' => $user1->id,
            'office_id' => null,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $user2->id,
            'office_id' => null,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Particular;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ParticularControllerTest extends TestCase
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

    public function test_administrator_can_access_particular_management(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $response = $this->actingAs($admin)->get('/admin/repositories/particulars');

        $response->assertStatus(200);
    }

    public function test_viewer_cannot_access_particular_management(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');

        $response = $this->actingAs($viewer)->get('/admin/repositories/particulars');

        $response->assertStatus(403);
    }

    public function test_endorser_cannot_access_particular_management(): void
    {
        $endorser = User::factory()->create();
        $endorser->assignRole('Endorser');

        $response = $this->actingAs($endorser)->get('/admin/repositories/particulars');

        $response->assertStatus(403);
    }

    public function test_can_create_particular_with_valid_data(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $response = $this->actingAs($admin)->post('/admin/repositories/particulars', [
            'description' => 'Test Particular Description',
            'is_active' => true,
        ]);

        $response->assertRedirect('/admin/repositories/particulars');
        $this->assertDatabaseHas('particulars', [
            'description' => 'Test Particular Description',
        ]);
    }

    public function test_cannot_create_particular_with_duplicate_description(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        Particular::create([
            'description' => 'Existing Particular',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post('/admin/repositories/particulars', [
            'description' => 'Existing Particular',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('description');
    }

    public function test_can_update_particular(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $particular = Particular::create([
            'description' => 'Original Particular',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->put("/admin/repositories/particulars/{$particular->id}", [
            'description' => 'Updated Particular',
            'is_active' => false,
        ]);

        $response->assertRedirect('/admin/repositories/particulars');
        $this->assertDatabaseHas('particulars', [
            'id' => $particular->id,
            'description' => 'Updated Particular',
            'is_active' => false,
        ]);
    }

    public function test_can_delete_particular(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $particular = Particular::create([
            'description' => 'Test Particular',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->delete("/admin/repositories/particulars/{$particular->id}");

        $response->assertRedirect('/admin/repositories/particulars');
        $this->assertSoftDeleted('particulars', [
            'id' => $particular->id,
        ]);
    }

    public function test_particular_list_includes_pagination(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        // Create 55 particulars to test pagination (limit is 50 per page)
        for ($i = 1; $i <= 55; $i++) {
            Particular::create([
                'description' => "Particular {$i}",
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs($admin)->get('/admin/repositories/particulars');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('particulars.data', 50)
            ->where('particulars.total', 55)
        );
    }

    public function test_soft_deleted_particulars_not_shown_in_list(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $activeParticular = Particular::create([
            'description' => 'Active Particular',
            'is_active' => true,
        ]);

        $deletedParticular = Particular::create([
            'description' => 'Deleted Particular',
            'is_active' => true,
        ]);
        $deletedParticular->delete();

        $response = $this->actingAs($admin)->get('/admin/repositories/particulars');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('particulars.data', 1)
            ->where('particulars.data.0.description', 'Active Particular')
        );
    }

    public function test_description_field_accepts_up_to_500_characters(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $longDescription = str_repeat('A', 500);

        $response = $this->actingAs($admin)->post('/admin/repositories/particulars', [
            'description' => $longDescription,
            'is_active' => true,
        ]);

        $response->assertRedirect('/admin/repositories/particulars');
        $this->assertDatabaseHas('particulars', [
            'description' => $longDescription,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\ActionTaken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ActionTakenControllerTest extends TestCase
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

    public function test_administrator_can_access_action_taken_management(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $response = $this->actingAs($admin)->get('/admin/repositories/action-taken');

        $response->assertStatus(200);
    }

    public function test_viewer_cannot_access_action_taken_management(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');

        $response = $this->actingAs($viewer)->get('/admin/repositories/action-taken');

        $response->assertStatus(403);
    }

    public function test_endorser_cannot_access_action_taken_management(): void
    {
        $endorser = User::factory()->create();
        $endorser->assignRole('Endorser');

        $response = $this->actingAs($endorser)->get('/admin/repositories/action-taken');

        $response->assertStatus(403);
    }

    public function test_can_create_action_taken_with_valid_data(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $response = $this->actingAs($admin)->post('/admin/repositories/action-taken', [
            'description' => 'Test Action Taken Description',
            'is_active' => true,
        ]);

        $response->assertRedirect('/admin/repositories/action-taken');
        $this->assertDatabaseHas('action_taken', [
            'description' => 'Test Action Taken Description',
        ]);
    }

    public function test_cannot_create_action_taken_with_duplicate_description(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        ActionTaken::create([
            'description' => 'Existing Action',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post('/admin/repositories/action-taken', [
            'description' => 'Existing Action',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('description');
    }

    public function test_can_update_action_taken(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $actionTaken = ActionTaken::create([
            'description' => 'Original Action',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->put("/admin/repositories/action-taken/{$actionTaken->id}", [
            'description' => 'Updated Action',
            'is_active' => false,
        ]);

        $response->assertRedirect('/admin/repositories/action-taken');
        $this->assertDatabaseHas('action_taken', [
            'id' => $actionTaken->id,
            'description' => 'Updated Action',
            'is_active' => false,
        ]);
    }

    public function test_can_delete_action_taken(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $actionTaken = ActionTaken::create([
            'description' => 'Test Action',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->delete("/admin/repositories/action-taken/{$actionTaken->id}");

        $response->assertRedirect('/admin/repositories/action-taken');
        $this->assertSoftDeleted('action_taken', [
            'id' => $actionTaken->id,
        ]);
    }

    public function test_action_taken_list_includes_pagination(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        // Create 55 action taken records to test pagination (limit is 50 per page)
        for ($i = 1; $i <= 55; $i++) {
            ActionTaken::create([
                'description' => "Action Taken {$i}",
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs($admin)->get('/admin/repositories/action-taken');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('actionTaken.data', 50)
            ->where('actionTaken.total', 55)
        );
    }

    public function test_soft_deleted_action_taken_not_shown_in_list(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $activeAction = ActionTaken::create([
            'description' => 'Active Action',
            'is_active' => true,
        ]);

        $deletedAction = ActionTaken::create([
            'description' => 'Deleted Action',
            'is_active' => true,
        ]);
        $deletedAction->delete();

        $response = $this->actingAs($admin)->get('/admin/repositories/action-taken');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('actionTaken.data', 1)
            ->where('actionTaken.data.0.description', 'Active Action')
        );
    }

    public function test_description_field_accepts_up_to_255_characters(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $longDescription = str_repeat('A', 255);

        $response = $this->actingAs($admin)->post('/admin/repositories/action-taken', [
            'description' => $longDescription,
            'is_active' => true,
        ]);

        $response->assertRedirect('/admin/repositories/action-taken');
        $this->assertDatabaseHas('action_taken', [
            'description' => $longDescription,
        ]);
    }
}

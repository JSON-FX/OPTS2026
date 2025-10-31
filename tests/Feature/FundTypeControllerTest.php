<?php

namespace Tests\Feature;

use App\Models\FundType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FundTypeControllerTest extends TestCase
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

    public function test_administrator_can_access_fund_type_management(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $response = $this->actingAs($admin)->get('/admin/repositories/fund-types');

        $response->assertStatus(200);
    }

    public function test_viewer_cannot_access_fund_type_management(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');

        $response = $this->actingAs($viewer)->get('/admin/repositories/fund-types');

        $response->assertStatus(403);
    }

    public function test_endorser_cannot_access_fund_type_management(): void
    {
        $endorser = User::factory()->create();
        $endorser->assignRole('Endorser');

        $response = $this->actingAs($endorser)->get('/admin/repositories/fund-types');

        $response->assertStatus(403);
    }

    public function test_can_create_fund_type_with_valid_data(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $response = $this->actingAs($admin)->post('/admin/repositories/fund-types', [
            'name' => 'Test Fund',
            'abbreviation' => 'TF',
            'is_active' => true,
        ]);

        $response->assertRedirect('/admin/repositories/fund-types');
        $this->assertDatabaseHas('fund_types', [
            'name' => 'Test Fund',
            'abbreviation' => 'TF',
        ]);
    }

    public function test_cannot_create_fund_type_with_duplicate_name(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        FundType::create([
            'name' => 'Existing Fund',
            'abbreviation' => 'EF',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post('/admin/repositories/fund-types', [
            'name' => 'Existing Fund',
            'abbreviation' => 'EF2',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_cannot_create_fund_type_with_duplicate_abbreviation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        FundType::create([
            'name' => 'Existing Fund',
            'abbreviation' => 'EF',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post('/admin/repositories/fund-types', [
            'name' => 'Different Fund',
            'abbreviation' => 'EF',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('abbreviation');
    }

    public function test_can_update_fund_type(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $fundType = FundType::create([
            'name' => 'Original Fund',
            'abbreviation' => 'OF',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->put("/admin/repositories/fund-types/{$fundType->id}", [
            'name' => 'Updated Fund',
            'abbreviation' => 'UF',
            'is_active' => false,
        ]);

        $response->assertRedirect('/admin/repositories/fund-types');
        $this->assertDatabaseHas('fund_types', [
            'id' => $fundType->id,
            'name' => 'Updated Fund',
            'abbreviation' => 'UF',
            'is_active' => false,
        ]);
    }

    public function test_can_delete_fund_type(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $fundType = FundType::create([
            'name' => 'Test Fund',
            'abbreviation' => 'TF',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->delete("/admin/repositories/fund-types/{$fundType->id}");

        $response->assertRedirect('/admin/repositories/fund-types');
        $this->assertSoftDeleted('fund_types', [
            'id' => $fundType->id,
        ]);
    }

    public function test_fund_type_list_includes_pagination(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        // Create 55 fund types to test pagination (limit is 50 per page)
        for ($i = 1; $i <= 55; $i++) {
            FundType::create([
                'name' => "Fund Type {$i}",
                'abbreviation' => "FT{$i}",
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs($admin)->get('/admin/repositories/fund-types');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('fundTypes.data', 50)
            ->where('fundTypes.total', 55)
        );
    }

    public function test_soft_deleted_fund_types_not_shown_in_list(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $activeFundType = FundType::create([
            'name' => 'Active Fund',
            'abbreviation' => 'AF',
            'is_active' => true,
        ]);

        $deletedFundType = FundType::create([
            'name' => 'Deleted Fund',
            'abbreviation' => 'DF',
            'is_active' => true,
        ]);
        $deletedFundType->delete();

        $response = $this->actingAs($admin)->get('/admin/repositories/fund-types');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('fundTypes.data', 1)
            ->where('fundTypes.data.0.name', 'Active Fund')
        );
    }

    public function test_seeded_fund_types_exist(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        // Run the seeder
        $this->seed(\Database\Seeders\FundTypeSeeder::class);

        // Verify the three expected fund types exist
        $this->assertDatabaseHas('fund_types', [
            'name' => 'General Fund',
            'abbreviation' => 'GF',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('fund_types', [
            'name' => 'Trust Fund',
            'abbreviation' => 'TF',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('fund_types', [
            'name' => 'Special Education Fund',
            'abbreviation' => 'SEF',
            'is_active' => true,
        ]);
    }

    public function test_abbreviation_accepts_up_to_20_characters(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $response = $this->actingAs($admin)->post('/admin/repositories/fund-types', [
            'name' => 'Long Abbreviation Fund',
            'abbreviation' => str_repeat('A', 20),
            'is_active' => true,
        ]);

        $response->assertRedirect('/admin/repositories/fund-types');
        $this->assertDatabaseHas('fund_types', [
            'abbreviation' => str_repeat('A', 20),
        ]);
    }
}

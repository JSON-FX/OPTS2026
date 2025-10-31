<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupplierControllerTest extends TestCase
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

    public function test_administrator_can_access_supplier_management(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $response = $this->actingAs($admin)->get('/admin/repositories/suppliers');

        $response->assertStatus(200);
    }

    public function test_viewer_cannot_access_supplier_management(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');

        $response = $this->actingAs($viewer)->get('/admin/repositories/suppliers');

        $response->assertStatus(403);
    }

    public function test_endorser_cannot_access_supplier_management(): void
    {
        $endorser = User::factory()->create();
        $endorser->assignRole('Endorser');

        $response = $this->actingAs($endorser)->get('/admin/repositories/suppliers');

        $response->assertStatus(403);
    }

    public function test_can_create_supplier_with_valid_data(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $response = $this->actingAs($admin)->post('/admin/repositories/suppliers', [
            'name' => 'Test Supplier',
            'address' => "123 Main Street\nSuite 100\nCity, State 12345",
            'contact_person' => 'John Doe',
            'contact_number' => '555-1234',
            'is_active' => true,
        ]);

        $response->assertRedirect('/admin/repositories/suppliers');
        $this->assertDatabaseHas('suppliers', [
            'name' => 'Test Supplier',
            'address' => "123 Main Street\nSuite 100\nCity, State 12345",
            'contact_person' => 'John Doe',
            'contact_number' => '555-1234',
        ]);
    }

    public function test_cannot_create_supplier_with_duplicate_name(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        Supplier::create([
            'name' => 'Existing Supplier',
            'address' => '123 Main St',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post('/admin/repositories/suppliers', [
            'name' => 'Existing Supplier',
            'address' => '456 Different St',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_can_update_supplier(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $supplier = Supplier::create([
            'name' => 'Original Supplier',
            'address' => 'Original Address',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->put("/admin/repositories/suppliers/{$supplier->id}", [
            'name' => 'Updated Supplier',
            'address' => 'Updated Address',
            'contact_person' => 'Jane Smith',
            'contact_number' => '555-5678',
            'is_active' => false,
        ]);

        $response->assertRedirect('/admin/repositories/suppliers');
        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'Updated Supplier',
            'address' => 'Updated Address',
            'contact_person' => 'Jane Smith',
            'contact_number' => '555-5678',
            'is_active' => false,
        ]);
    }

    public function test_can_delete_supplier(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $supplier = Supplier::create([
            'name' => 'Test Supplier',
            'address' => '123 Main St',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->delete("/admin/repositories/suppliers/{$supplier->id}");

        $response->assertRedirect('/admin/repositories/suppliers');
        $this->assertSoftDeleted('suppliers', [
            'id' => $supplier->id,
        ]);
    }

    public function test_supplier_list_includes_pagination(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        // Create 55 suppliers to test pagination (limit is 50 per page)
        for ($i = 1; $i <= 55; $i++) {
            Supplier::create([
                'name' => "Supplier {$i}",
                'address' => "Address {$i}",
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs($admin)->get('/admin/repositories/suppliers');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('suppliers.data', 50)
            ->where('suppliers.total', 55)
        );
    }

    public function test_soft_deleted_suppliers_not_shown_in_list(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $activeSupplier = Supplier::create([
            'name' => 'Active Supplier',
            'address' => '123 Active St',
            'is_active' => true,
        ]);

        $deletedSupplier = Supplier::create([
            'name' => 'Deleted Supplier',
            'address' => '456 Deleted St',
            'is_active' => true,
        ]);
        $deletedSupplier->delete();

        $response = $this->actingAs($admin)->get('/admin/repositories/suppliers');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('suppliers.data', 1)
            ->where('suppliers.data.0.name', 'Active Supplier')
        );
    }

    public function test_address_field_accepts_multiline_text(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $multilineAddress = "123 Main Street\nBuilding A, Floor 2\nSuite 200\nCity, State 12345";

        $response = $this->actingAs($admin)->post('/admin/repositories/suppliers', [
            'name' => 'Test Supplier',
            'address' => $multilineAddress,
            'is_active' => true,
        ]);

        $response->assertRedirect('/admin/repositories/suppliers');
        $this->assertDatabaseHas('suppliers', [
            'name' => 'Test Supplier',
            'address' => $multilineAddress,
        ]);
    }

    public function test_contact_number_is_optional(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $response = $this->actingAs($admin)->post('/admin/repositories/suppliers', [
            'name' => 'Supplier Without Contact',
            'address' => '123 Main St',
            'is_active' => true,
        ]);

        $response->assertRedirect('/admin/repositories/suppliers');
        $this->assertDatabaseHas('suppliers', [
            'name' => 'Supplier Without Contact',
            'contact_number' => null,
        ]);
    }
}

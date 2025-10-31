<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Particular;
use App\Models\Procurement;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProcurementControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Viewer', 'guard_name' => 'web']);
        Role::create(['name' => 'Endorser', 'guard_name' => 'web']);
        Role::create(['name' => 'Administrator', 'guard_name' => 'web']);
    }

    public function test_viewer_can_view_procurement_index(): void
    {
        $office = Office::factory()->create();
        $particular = Particular::factory()->create();
        $creator = User::factory()->create();
        $creator->assignRole('Endorser');

        Procurement::factory()->create([
            'end_user_id' => $office->id,
            'particular_id' => $particular->id,
            'created_by_user_id' => $creator->id,
        ]);

        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');

        $response = $this->actingAs($viewer)->get(route('procurements.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Procurements/Index')
            ->has('procurements.data', 1)
        );
    }

    public function test_endorser_can_create_procurement(): void
    {
        $endorser = User::factory()->create();
        $endorser->assignRole('Endorser');

        $office = Office::factory()->create();
        $particular = Particular::factory()->create();

        $response = $this->actingAs($endorser)->post(route('procurements.store'), [
            'end_user_id' => $office->id,
            'particular_id' => $particular->id,
            'purpose' => 'Acquire new laptops for IT department',
            'abc_amount' => '150000.00',
            'date_of_entry' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect(route('procurements.index'));

        $this->assertDatabaseHas('procurements', [
            'end_user_id' => $office->id,
            'particular_id' => $particular->id,
            'created_by_user_id' => $endorser->id,
            'status' => Procurement::STATUS_CREATED,
        ]);
    }

    public function test_viewer_cannot_access_create_form(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');

        $response = $this->actingAs($viewer)->get(route('procurements.create'));

        $response->assertStatus(403);
    }

    public function test_endorser_cannot_change_end_user_when_transactions_exist(): void
    {
        $endorser = User::factory()->create();
        $endorser->assignRole('Endorser');

        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $particular = Particular::factory()->create();

        $procurement = Procurement::factory()->create([
            'end_user_id' => $officeA->id,
            'particular_id' => $particular->id,
            'created_by_user_id' => $endorser->id,
        ]);

        Transaction::create([
            'procurement_id' => $procurement->id,
            'category' => Transaction::CATEGORY_PURCHASE_REQUEST,
            'reference_number' => 'PR-2025-000001',
            'status' => 'Created',
            'created_by_user_id' => $endorser->id,
        ]);

        $response = $this->actingAs($endorser)->put(route('procurements.update', $procurement), [
            'end_user_id' => $officeB->id,
            'particular_id' => $particular->id,
            'purpose' => 'Updated purpose',
            'abc_amount' => '250000.00',
            'date_of_entry' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect(route('procurements.index'));

        $procurement->refresh();
        $this->assertEquals($officeA->id, $procurement->end_user_id);
        $this->assertSame('Updated purpose', $procurement->purpose);
    }

    public function test_endorser_can_archive_procurement(): void
    {
        $endorser = User::factory()->create();
        $endorser->assignRole('Endorser');

        $procurement = Procurement::factory()->create([
            'created_by_user_id' => $endorser->id,
        ]);

        $response = $this->actingAs($endorser)->delete(route('procurements.destroy', $procurement));

        $response->assertRedirect(route('procurements.index'));
        $this->assertSoftDeleted('procurements', ['id' => $procurement->id]);
    }
}
